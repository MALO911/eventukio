<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user = getCurrentUser();
$user_id = $user['user_id'];

// Fetch existing jobs (user_event_jobs) where job_status = 'Valid'
$jobs_stmt = $pdo->prepare("SELECT * FROM user_event_jobs WHERE user_id = ? AND job_status = 'Valid'");
$jobs_stmt->execute([$user_id]);
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing assets (user_event_asset) where asset_status = 'Available'
$assets_stmt = $pdo->prepare("SELECT * FROM user_event_asset WHERE owner_id = ? AND asset_status = 'Available'");
$assets_stmt->execute([$user_id]);
$assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing gateways (user_gateway_info) with account_status = 'Active'
$gateways_stmt = $pdo->prepare("SELECT * FROM user_gateway_info WHERE user_id = ? AND account_status = 'Active'");
$gateways_stmt->execute([$user_id]);
$gateways = $gateways_stmt->fetchAll(PDO::FETCH_ASSOC);

// Predefined lists for combos (as per blueprint)
$categories = [
    'Entertainment' => ['Musician', 'Stand-up comedian', 'Actor', 'Dee jay', 'Stripper', 'Magician', 'Painter', 'Drummer'],
    'Technology'    => ['IT expert', 'Device operator', 'Electrician'],
    'Food'          => ['Chef', 'Cook', 'Fruit shredder', 'Organic juice expert', 'Butcher'],
    'Education & Society' => ['Teacher', 'Motivational speaker', 'Politician', 'Professor', 'Minister', 'Business strategist']
];

$sectors = [
    'Entertainment' => ['Dancer crew', 'Choir', 'Band', 'Singers', 'Strippers', 'Comedy crew', 'Acting crew', 'Acrobatics crew'],
    'Security'      => ['Bodyguards', 'Patrol guards', 'Personal guards'],
    'Hospitality'   => ['Customer care team', 'Receptionists'],
    'Technology'    => ['IT staff', 'Electricians', 'Device operators'],
    'Education'     => ['Teachers', 'Librarians', 'Lecturers'],
    'Cleanliness'   => ['Laundry experts', 'Janitors', 'Hedge trimmers'],
    'Religion'      => ['Evangelists', 'Pastors', 'Priests', 'Imams', 'Sheikhs', 'Ushers', 'Nuns', 'Monks'],
    'Food and Drinks' => ['Catering services', 'Drinks and beverages', 'Fruit distribution', 'Hotel staff', 'Restaurant staff', 'Cafeteria staff']
];

$asset_categories = [
    'Venue' => [
        'Hall'   => ['Over 10000 square metres', '2500 square metres to 10000 square metres', '625 square metres to 2500 square metres', 'Under 625 square metres'],
        'Tent'   => ['6 metres by 5 metres', '6 metres by 4 metres', '5 metres by 5 metres', '5 metres by 4 metres'],
        'Pitch'  => ['Over 3 acres', '2 acres to 3 acres', '1 acre to 2 acres', 'Under 1 acre'],
        'Room'   => ['Over 100 square metres', '60 square metres by 100 square metres', '20 square metres to 60 square metres', 'Under 20 square metres']
    ],
    'Furniture' => [
        'Table'  => ['Wooden office table', 'Wooden staff table', 'Wooden sofa table', 'Average plastic table', 'Small plastic table', 'Plastic table with metal support', 'Metal table', 'Wooden table with metal support'],
        'Chair'  => ['Wooden office chair', 'Normal office chair', 'Rotatable office chair', 'Dining wooden chair', 'Wooden staff chair', 'Wooden sofa chair', 'Average plastic chair', 'Small plastic chair', 'Plastic chair with metal support', 'Metal chair', 'Wooden chair with metal support'],
        'Stage'  => ['Metal support stage', 'Wooden support stage']
    ],
    'Musical Instruments' => [
        'Keyboard'   => ['Yamaha', 'Yoshimitsu', 'Sony', 'Other'],
        'Speakers'   => ['Panasonic', 'Sony', 'JBL', 'Other'],
        'Microphones'=> ['Wireless', 'Wired', 'Earpiece'],
        'Mixers'     => ['Powers mixers', 'Studio mixers', 'Other mixers']
    ],
    'Utensils' => [
        'Cooking pans'   => ['Extra large', 'Medium large', 'Large', 'Normal medium', 'Small normal', 'Extra small'],
        'Cooking spoons' => ['Extra large', 'Medium large', 'Large', 'Normal medium', 'Small normal', 'Extra small'],
        'Plates'         => ['Large plastic', 'Medium plastic', 'Small plastic', 'Large clay', 'Medium clay', 'Small clay', 'Large ceramic', 'Medium ceramic', 'Small ceramic'],
        'Spoons'         => ['Dining spoons', 'Serving spoons', 'Golden spoons', 'Wooden spoons']
    ]
];

$gateway_methods = [
    'Mobile Network Operator' => ['M-Pesa', 'Halo Pesa', 'Mixx by Yas', 'Airtel Money'],
    'Bank' => ['CRDB', 'NMB', 'NBC', 'Azania', 'Equity', 'Exim'],
    'Payment Aggregator' => ['Click Pesa', 'Pesa pal', 'Selcom', 'Flutter wave', 'Cellulant', 'Azam Pesa']
];

// Regions and districts
$regions = ['Dar es Salaam', 'Arusha', 'Dodoma', 'Mbeya', 'Mwanza', 'Tanga', 'Morogoro', 'Iringa', 'Lindi', 'Mtwara', 'Shinyanga', 'Mara', 'Kilimanjaro', 'Manyara', 'Singida', 'Tabora', 'Kigoma', 'Katavi', 'Rukwa', 'Ruvuma', 'Pwani', 'Geita', 'Simiyu', 'Njombe', 'Songwe'];
$districts_by_region = [
    "Arusha" => ["Arusha City", "Arusha DC", "Karatu", "Longido", "Meru", "Monduli", "Ngorongoro"],
    "Dodoma" => ["Dodoma City", "Bahi", "Chamwino", "Chemba", "Kondoa", "Kondoa Town", "Kongwa", "Mpwapwa"],
    "Dar es Salaam" => ["Ilala", "Kinondoni", "Temeke", "Kigamboni", "Ubungo"],
    "Tanga" => ["Tanga City", "Handeni", "Handeni Town", "Kilindi", "Korogwe", "Korogwe Town", "Lushoto", "Mkinga", "Muheza", "Pangani", "Bumbuli"],
    "Morogoro" => ["Morogoro MC", "Morogoro DC", "Gairo", "Kilombero", "Kilosa", "Malinyi", "Mlimba", "Ulanga", "Ifakara Town"],
    "Mwanza" => ["Ilemela", "Nyamagana", "Buchosa", "Kwimba", "Magu", "Misungwi", "Sengerema", "Ukerewe"],
    "Kagera" => ["Bukoba MC", "Bukoba DC", "Biharamulo", "Karagwe", "Kyerwa", "Missenyi", "Muleba", "Ngara"],
    "Mbeya" => ["Mbeya City", "Mbeya DC", "Busokelo", "Chunya", "Kyela", "Mbarali", "Rungwe"],
    "Iringa" => ["Iringa MC", "Iringa DC", "Kilolo", "Mafinga Town", "Mufindi"],
    "Lindi" => ["Lindi MC", "Lindi DC", "Kilwa", "Liwale", "Nachingwea", "Ruangwa"],
    "Mtwara" => ["Mtwara MC", "Mtwara DC", "Masasi Town", "Masasi DC", "Nanyamba Town", "Nanyumbu", "Newala Town", "Newala DC", "Tandahimba"],
    "Shinyanga" => ["Shinyanga MC", "Shinyanga DC", "Kahama Town", "Msalala", "Ushetu", "Kishapu"],
    "Mara" => ["Musoma MC", "Musoma DC", "Bunda Town", "Bunda DC", "Butiama", "Serengeti", "Tarime", "Rorya"],
    "Kilimanjaro" => ["Moshi MC", "Moshi DC", "Hai", "Mwanga", "Rombo", "Same", "Siha"],
    "Manyara" => ["Babati Town", "Babati DC", "Hanang", "Kiteto", "Mbulu Town", "Mbulu DC", "Simanjiro"],
    "Singida" => ["Singida MC", "Singida DC", "Ikungi", "Iramba", "Itigi", "Manyoni", "Mkalama"],
    "Tabora" => ["Tabora MC", "Igunga", "Kaliua", "Nzega Town", "Nzega DC", "Sikonge", "Urambo", "Uyui"],
    "Kigoma" => ["Kigoma-Ujiji MC", "Kigoma DC", "Buhigwe", "Kakonko", "Kasulu Town", "Kasulu DC", "Kibondo", "Uvinza"],
    "Katavi" => ["Mpanda MC", "Mpanda DC", "Mpimbwe", "Nsimbo", "Tanganyika"],
    "Rukwa" => ["Sumbawanga MC", "Sumbawanga DC", "Kalambo", "Nkasi"],
    "Ruvuma" => ["Songea MC", "Songea DC", "Madaba", "Mbinga Town", "Mbinga DC", "Namtumbo", "Nyasa", "Tunduru"],
    "Pwani" => ["Chalinze", "Bagamoyo", "Kibaha Town", "Kibaha DC", "Kibiti", "Kisarawe", "Mafia", "Mkuranga", "Rufiji"],
    "Geita" => ["Geita Town", "Geita DC", "Bukombe", "Chato", "Mbogwe", "Nyang'hwale"],
    "Simiyu" => ["Bariadi Town", "Bariadi DC", "Busega", "Itilima", "Maswa", "Meatu"],
    "Njombe" => ["Njombe Town", "Njombe DC", "Ludewa", "Makambako Town", "Makete", "Wanging'ombe"],
    "Songwe" => ["Tunduma Town", "Ileje", "Mbozi", "Momba", "Songwe DC"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.25); }
        .step { visibility: hidden; height: 0; overflow: hidden; }
        .step.active { visibility: visible; height: auto; overflow: visible; }
        .toggle-switch { position: relative; width: 60px; height: 30px; background: #ccc; border-radius: 30px; cursor: pointer; transition: 0.3s; }
        .toggle-switch.active { background: #4F46E5; }
        .toggle-switch .slider { position: absolute; top: 3px; left: 3px; width: 24px; height: 24px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-switch.active .slider { left: 33px; }
        .repeatable-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
        .repeatable-row select, .repeatable-row input { flex: 1; min-width: 120px; padding: 10px; border-radius: 12px; border: 1px solid #ddd; }
        .remove-row { color: red; cursor: pointer; }
        .table-delete-btn { color: red; border: none; background: none; cursor: pointer; }
        .readonly-field { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Update Profile</h1>
        <a href="account.php" class="text-gray-700 hover:text-indigo-700"><i class="fa fa-arrow-left mr-1"></i>Back to Account</a>
    </div>
</header>

<div class="max-w-5xl mx-auto px-4 py-8">
    <!-- Profile Summary -->
    <div class="glass rounded-3xl p-8 text-center mb-10">
        <img src="<?= htmlspecialchars(getProfilePictureUrl($user['user_profile_picture'] ?? '', BASE_URL . 'assets/images/default.png', 'absolute')) ?>" 
             class="w-32 h-32 rounded-full mx-auto border-4 border-white mb-4" alt="">
        <h2 class="text-2xl font-semibold"><?= htmlspecialchars($user['user_full_name']) ?></h2>
        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['user_email']) ?></p>
    </div>

    <form id="update-form" method="POST" action="update-profile_process.php" enctype="multipart/form-data">
        <!-- Hidden fields for step and actions -->
        <input type="hidden" name="current_step" id="current_step" value="1">
        <input type="hidden" name="form_submit_action" id="form_submit_action" value="">

        <!-- Progress -->
        <div class="flex justify-between items-center mb-8">
            <span class="text-sm text-gray-600">Step <span id="step-number">1</span> of 4</span>
            <div class="flex-1 mx-4 h-1 bg-gray-300 rounded">
                <div id="progress-bar" class="h-1 bg-indigo-600 rounded" style="width: 25%;"></div>
            </div>
        </div>

        <!-- STEP 1: Basic Information -->
        <div id="step-1" class="step active">
            <h3 class="text-xl font-semibold mb-6">
                <?= ($user['user_type'] === 'Personal') ? 'Update Personal Basic Information' : 'Update Organization Basic Information' ?>
            </h3>

            <?php if ($user['user_type'] === 'Personal'): ?>
                <!-- Personal -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Full Name (read‑only)</label>
                        <input type="text" name="user_full_name" value="<?= htmlspecialchars($user['user_full_name']) ?>" 
                               class="readonly-field glass w-full rounded-2xl px-5 py-4" readonly>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/*" class="glass w-full rounded-2xl px-5 py-4">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current</p>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Email (read‑only)</label>
                        <input type="email" name="user_email" value="<?= htmlspecialchars($user['user_email']) ?>" 
                               class="readonly-field glass w-full rounded-2xl px-5 py-4" readonly>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Birthdate (read‑only)</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" 
                               class="readonly-field glass w-full rounded-2xl px-5 py-4" readonly>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Gender</label>
                        <select name="user_gender" class="glass w-full rounded-2xl px-5 py-4">
                            <option value="None" <?= ($user['user_gender'] ?? 'None') == 'None' ? 'selected' : '' ?>>None</option>
                            <option value="Male" <?= ($user['user_gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($user['user_gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Phone Number</label>
                        <input type="tel" name="user_phone_number" value="<?= htmlspecialchars($user['user_phone_number'] ?? '') ?>" 
                               class="glass w-full rounded-2xl px-5 py-4">
                    </div>
                </div>
            <?php else: ?>
                <!-- Organization -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Registered Name (read‑only)</label>
                        <input type="text" name="user_full_name" value="<?= htmlspecialchars($user['user_full_name']) ?>" 
                               class="readonly-field glass w-full rounded-2xl px-5 py-4" readonly>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Organization/Business Type (read‑only)</label>
                        <select name="user_gender" class="readonly-field glass w-full rounded-2xl px-5 py-4" disabled>
                            <option value="Profit" <?= ($user['user_gender'] ?? 'Profit') == 'Profit' ? 'selected' : '' ?>>Profit</option>
                            <option value="Non-Profit" <?= ($user['user_gender'] ?? '') == 'Non-Profit' ? 'selected' : '' ?>>Non-Profit</option>
                        </select>
                        <input type="hidden" name="user_gender" value="<?= htmlspecialchars($user['user_gender'] ?? 'Profit') ?>">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Organization Email (read‑only)</label>
                        <input type="email" name="user_email" value="<?= htmlspecialchars($user['user_email']) ?>" 
                               class="readonly-field glass w-full rounded-2xl px-5 py-4" readonly>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Phone Number</label>
                        <input type="tel" name="user_phone_number" value="<?= htmlspecialchars($user['user_phone_number'] ?? '') ?>" 
                               class="glass w-full rounded-2xl px-5 py-4">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Organization Logo / Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/*" class="glass w-full rounded-2xl px-5 py-4">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex justify-between mt-8">
                <div></div>
                <button type="button" onclick="submitStepChanges(1)" class="bg-green-600 text-white px-6 py-3 rounded-full">Submit Changes</button>
                <button type="button" onclick="submitStepAndContinue(1)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Next <i class="fa fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- STEP 2: Job & Residence (Personal) or Field & Site (Organization) -->
        <div id="step-2" class="step">
            <h3 class="text-xl font-semibold mb-6">
                <?= ($user['user_type'] === 'Personal') ? 'Update Job and Residence' : 'Update Field and Site' ?>
            </h3>

            <?php if ($user['user_type'] === 'Personal'): ?>
                <!-- Personal: Job & Residence -->
                <div class="mb-8">
                    <h4 class="font-semibold mb-3">Your Occupation Record</h4>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Job Title</th>
                                <th class="text-left py-2">Events</th>
                                <th class="text-left py-2">Amount Earned (TZS)</th>
                                <th class="text-left py-2">Avg Rating</th>
                                <th class="text-left py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr class="border-b">
                                    <td class="py-2"><?= htmlspecialchars($job['profession_title']) ?></td>
                                    <td class="py-2"><?= htmlspecialchars($job['task_count'] ?? 0) ?></td>
                                    <td class="py-2"><?= number_format($job['job_earning'] ?? 0, 2) ?></td>
                                    <td class="py-2"><?= number_format($job['job_average_rating'] ?? 0, 1) ?></td>
                                    <td class="py-2">
                                        <a href="update-profile_process.php?delete_job=<?= $job['profile_id'] ?>" 
                                           onclick="return confirm('Delete this job?')" class="text-red-600">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add new jobs (repeatable) -->
                <div class="mb-8">
                    <h4 class="font-semibold mb-3">Add New Jobs</h4>
                    <div id="job-rows">
                        <div class="repeatable-row">
                            <select name="job_category[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                                <option value="">Category</option>
                                <?php foreach ($categories as $cat => $titles): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="job_title[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                                <option value="">Title</option>
                                <?php foreach ($categories as $cat => $titles): ?>
                                    <?php foreach ($titles as $title): ?>
                                        <option data-category="<?= htmlspecialchars($cat) ?>" value="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                        </div>
                    </div>
                    <button type="button" onclick="addJobRow()" class="text-indigo-600 text-sm mt-2"><i class="fa fa-plus"></i> Add more</button>
                </div>

                <!-- Place of Residence -->
                <div>
                    <h4 class="font-semibold mb-3">Place of Residence</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Region</label>
                            <select name="home_region" class="glass w-full rounded-2xl px-5 py-4" id="region-select">
                                <option value="">Select Region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= htmlspecialchars($region) ?>" <?= ($user['home_region'] ?? '') == $region ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">District</label>
                            <select name="home_district" class="glass w-full rounded-2xl px-5 py-4" id="district-select">
                                <option value="">Select District</option>
                                <!-- populated by JS -->
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Street/Ward</label>
                            <input type="text" name="home_street" value="<?= htmlspecialchars($user['home_street'] ?? '') ?>" class="glass w-full rounded-2xl px-5 py-4">
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Organization: Field and Site -->
                <!-- Similar to Personal but with 'Sector' and 'Workers available' -->
                <div class="mb-8">
                    <h4 class="font-semibold mb-3">Your Operation Record</h4>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Staff Sector</th>
                                <th class="text-left py-2">Events Participated</th>
                                <th class="text-left py-2">Amount Earned (TZS)</th>
                                <th class="text-left py-2">Avg Rating</th>
                                <th class="text-left py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr class="border-b">
                                    <td class="py-2"><?= htmlspecialchars($job['profession_title']) ?></td>
                                    <td class="py-2"><?= htmlspecialchars($job['task_count'] ?? 0) ?></td>
                                    <td class="py-2"><?= number_format($job['job_earning'] ?? 0, 2) ?></td>
                                    <td class="py-2"><?= number_format($job['job_average_rating'] ?? 0, 1) ?></td>
                                    <td class="py-2">
                                        <a href="update-profile_process.php?delete_job=<?= $job['profile_id'] ?>" 
                                           onclick="return confirm('Delete this job?')" class="text-red-600">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add new staff (repeatable) -->
                <div class="mb-8">
                    <h4 class="font-semibold mb-3">Add New Staff</h4>
                    <div id="job-rows">
                        <div class="repeatable-row">
                            <select name="job_category[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                                <option value="">Sector</option>
                                <?php foreach ($sectors as $sector => $staff): ?>
                                    <option value="<?= htmlspecialchars($sector) ?>"><?= htmlspecialchars($sector) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="job_title[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                                <option value="">Workers available</option>
                                <?php foreach ($sectors as $sector => $staff): ?>
                                    <?php foreach ($staff as $item): ?>
                                        <option data-category="<?= htmlspecialchars($sector) ?>" value="<?= htmlspecialchars($item) ?>"><?= htmlspecialchars($item) ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                        </div>
                    </div>
                    <button type="button" onclick="addJobRow()" class="text-indigo-600 text-sm mt-2"><i class="fa fa-plus"></i> Add more</button>
                </div>

                <!-- Place of Operation / Headquarters -->
                <div>
                    <h4 class="font-semibold mb-3">Place of Operation / Headquarters</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Region</label>
                            <select name="home_region" class="glass w-full rounded-2xl px-5 py-4" id="region-select-org">
                                <option value="">Select Region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= htmlspecialchars($region) ?>" <?= ($user['home_region'] ?? '') == $region ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">District</label>
                            <select name="home_district" class="glass w-full rounded-2xl px-5 py-4" id="district-select-org">
                                <option value="">Select District</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Street/Ward</label>
                            <input type="text" name="home_street" value="<?= htmlspecialchars($user['home_street'] ?? '') ?>" class="glass w-full rounded-2xl px-5 py-4">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(1)" class="bg-gray-300 px-6 py-3 rounded-full"><i class="fa fa-arrow-left mr-2"></i> Back</button>
                <button type="button" onclick="submitStepChanges(2)" class="bg-green-600 text-white px-6 py-3 rounded-full">Submit Changes</button>
                <button type="button" onclick="submitStepAndContinue(2)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Next <i class="fa fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- STEP 3: Owned Assets -->
        <div id="step-3" class="step">
            <h3 class="text-xl font-semibold mb-6">Update Owned Assets</h3>

            <!-- Existing Assets -->
            <div class="mb-8">
                <h4 class="font-semibold mb-3">Your Existing Assets</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Asset Name</th>
                            <th class="text-left py-2">Quality</th>
                            <th class="text-left py-2">Quantity</th>
                            <th class="text-left py-2">Location</th>
                            <th class="text-left py-2">Amount Earned (TZS)</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr class="border-b">
                                <td class="py-2"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($asset['asset_quality']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($asset['asset_quantity']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($asset['asset_region'] . ', ' . $asset['asset_district'] . ', ' . $asset['asset_street']) ?></td>
                                <td class="py-2"><?= number_format($asset['asset_earned_amount'] ?? 0, 2) ?></td>
                                <td class="py-2">
                                    <a href="update-profile_process.php?delete_asset=<?= $asset['asset_id'] ?>" 
                                       onclick="return confirm('Delete this asset?')" class="text-red-600">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add new assets with toggle -->
            <div class="mb-6">
                <p class="text-sm text-gray-600 mb-2">Do you have any asset or property that you can rent for an event?</p>
                <div class="flex items-center gap-4">
                    <span class="text-sm">No</span>
                    <div id="asset-toggle" class="toggle-switch" onclick="toggleAsset()">
                        <div class="slider"></div>
                    </div>
                    <span class="text-sm">Yes</span>
                </div>
            </div>

            <div id="asset-rows-container" style="display: none;">
                <div id="asset-rows">
                    <!-- Repeatable asset rows -->
                    <div class="repeatable-row">
                        <select name="asset_category[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Category</option>
                            <?php foreach ($asset_categories as $cat => $names): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="asset_name[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Name</option>
                            <?php foreach ($asset_categories as $cat => $names): ?>
                                <?php foreach ($names as $name => $qualities): ?>
                                    <option class="asset-name-<?= htmlspecialchars($cat) ?>" value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <select name="asset_quality[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Quality</option>
                            <?php foreach ($asset_categories as $cat => $names): ?>
                                <?php foreach ($names as $name => $qualities): ?>
                                    <?php foreach ($qualities as $quality): ?>
                                        <option class="asset-quality-<?= htmlspecialchars($cat) . '-' . htmlspecialchars($name) ?>" value="<?= htmlspecialchars($quality) ?>"><?= htmlspecialchars($quality) ?></option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="asset_quantity[]" placeholder="Qty" class="glass rounded-xl px-4 py-2" style="flex:0.5;">
                        <select name="asset_region[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Region</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="asset_district[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">District</option>
                        </select>
                        <input type="text" name="asset_street[]" placeholder="Street/Ward" class="glass rounded-xl px-4 py-2" style="flex:1;">
                        <input type="text" name="asset_location_specifics[]" placeholder="Exact location" class="glass rounded-xl px-4 py-2" style="flex:1;">
                        <input type="number" name="asset_price[]" placeholder="Unit rental price" class="glass rounded-xl px-4 py-2" style="flex:0.7;" step="0.01">
                        <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                    </div>
                </div>
                <button type="button" onclick="addAssetRow()" class="text-indigo-600 text-sm mt-2"><i class="fa fa-plus"></i> Add another asset</button>
            </div>

            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(2)" class="bg-gray-300 px-6 py-3 rounded-full"><i class="fa fa-arrow-left mr-2"></i> Back</button>
                <button type="button" onclick="submitStepChanges(3)" class="bg-green-600 text-white px-6 py-3 rounded-full">Submit Changes</button>
                <button type="button" onclick="submitStepAndContinue(3)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Next <i class="fa fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- STEP 4: Digital Wallet -->
        <div id="step-4" class="step">
            <h3 class="text-xl font-semibold mb-6">Update Event Digital Wallet</h3>
            <p class="text-sm text-gray-600 mb-6">
                <?= ($user['user_type'] === 'Personal') ? 'Set up your personal digital wallet for events.' : 'Set up your organization\'s digital wallet for events.' ?>
            </p>

            <!-- Existing gateways -->
            <div class="mb-8">
                <h4 class="font-semibold mb-3">Your Existing Gateways</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Gateway Method</th>
                            <th class="text-left py-2">Finance Method</th>
                            <th class="text-left py-2">Account Number</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gateways as $gateway): ?>
                            <tr class="border-b">
                                <td class="py-2"><?= htmlspecialchars($gateway['gateway_method']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($gateway['gateway_brand']) ?></td>
                                <td class="py-2"><?= htmlspecialchars($gateway['gateway_account_number']) ?></td>
                                <td class="py-2">
                                    <a href="update-profile_process.php?delete_gateway=<?= $gateway['gateway_id'] ?>" 
                                       onclick="return confirm('Delete this gateway?')" class="text-red-600">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add new gateway (repeatable) -->
            <div>
                <h4 class="font-semibold mb-3">Add Gateway</h4>
                <div id="gateway-rows">
                    <div class="repeatable-row">
                        <select name="gateway_method[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Gateway Method</option>
                            <?php foreach ($gateway_methods as $method => $brands): ?>
                                <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="gateway_brand[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                            <option value="">Finance Method</option>
                            <?php foreach ($gateway_methods as $method => $brands): ?>
                                <?php foreach ($brands as $brand): ?>
                                    <option data-method="<?= htmlspecialchars($method) ?>" class="gw-brand-<?= htmlspecialchars($method) ?>" value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="gateway_account_number[]" placeholder="Account Number" class="glass rounded-xl px-4 py-2" style="flex:1;">
                        <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                    </div>
                </div>
                <button type="button" onclick="addGatewayRow()" class="text-indigo-600 text-sm mt-2"><i class="fa fa-plus"></i> Add another gateway</button>
            </div>

            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(3)" class="bg-gray-300 px-6 py-3 rounded-full"><i class="fa fa-arrow-left mr-2"></i> Back</button>
                <button type="button" onclick="submitStepChanges(4)" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-full font-semibold">Submit Changes</button>
            </div>
        </div>
    </form>
</div>

<script>
    // Step management
    let currentStep = 1;
    const totalSteps = 4;

    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        // Basic validation: if going forward, ensure required fields are filled (simple)
        if (step > currentStep) {
            // For step 1, we only validate that the form has basic data (already present)
            // For step 2, we could validate but we'll skip for simplicity.
        }
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        document.getElementById('current_step').value = step;
        document.getElementById('step-number').textContent = step;
        document.getElementById('progress-bar').style.width = ((step / totalSteps) * 100) + '%';
        currentStep = step;
    }

    function submitStepChanges(step) {
        document.getElementById('current_step').value = step;
        document.getElementById('form_submit_action').value = 'submit';
        document.querySelectorAll('.step').forEach(el => {
            el.style.visibility = 'visible';
            el.style.height = 'auto';
            el.style.overflow = 'visible';
        });
        document.getElementById('update-form').submit();
    }

    function submitStepAndContinue(step) {
        document.getElementById('current_step').value = step;
        document.getElementById('form_submit_action').value = 'next';
        document.querySelectorAll('.step').forEach(el => {
            el.style.visibility = 'visible';
            el.style.height = 'auto';
            el.style.overflow = 'visible';
        });
        document.getElementById('update-form').submit();
    }

    // Toggle for assets
    function toggleAsset() {
        const toggle = document.getElementById('asset-toggle');
        toggle.classList.toggle('active');
        const container = document.getElementById('asset-rows-container');
        container.style.display = toggle.classList.contains('active') ? 'block' : 'none';
    }

    // Add job row (for step 2)
    function addJobRow() {
        const container = document.getElementById('job-rows');
        const row = document.createElement('div');
        row.className = 'repeatable-row';
        // Clone the first row's structure, but we'll rebuild to avoid issues
        row.innerHTML = `
            <select name="job_category[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Category</option>
                <?php foreach ($user['user_type'] === 'Personal' ? $categories : $sectors as $cat => $items): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="job_title[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value=""><?= ($user['user_type'] === 'Personal') ? 'Title' : 'Workers available' ?></option>
                <?php foreach ($user['user_type'] === 'Personal' ? $categories : $sectors as $cat => $items): ?>
                    <?php foreach ($items as $item): ?>
                        <option data-category="<?= htmlspecialchars($cat) ?>" value="<?= htmlspecialchars($item) ?>"><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
        `;
        container.appendChild(row);
    }

    function addAssetRow() {
        const container = document.getElementById('asset-rows');
        const row = document.createElement('div');
        row.className = 'repeatable-row';
        row.innerHTML = `
            <select name="asset_category[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Category</option>
                <?php foreach ($asset_categories as $cat => $names): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="asset_name[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Name</option>
                <?php foreach ($asset_categories as $cat => $names): ?>
                    <?php foreach ($names as $name => $qualities): ?>
                        <option class="asset-name-<?= htmlspecialchars($cat) ?>" value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <select name="asset_quality[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Quality</option>
                <?php foreach ($asset_categories as $cat => $names): ?>
                    <?php foreach ($names as $name => $qualities): ?>
                        <?php foreach ($qualities as $quality): ?>
                            <option class="asset-quality-<?= htmlspecialchars($cat) . '-' . htmlspecialchars($name) ?>" value="<?= htmlspecialchars($quality) ?>"><?= htmlspecialchars($quality) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <input type="number" name="asset_quantity[]" placeholder="Qty" class="glass rounded-xl px-4 py-2" style="flex:0.5;">
            <select name="asset_region[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Region</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="asset_district[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">District</option>
            </select>
            <input type="text" name="asset_street[]" placeholder="Street/Ward" class="glass rounded-xl px-4 py-2" style="flex:1;">
            <input type="text" name="asset_location_specifics[]" placeholder="Exact location" class="glass rounded-xl px-4 py-2" style="flex:1;">
            <input type="number" name="asset_price[]" placeholder="Unit rental price" class="glass rounded-xl px-4 py-2" style="flex:0.7;" step="0.01">
            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
        `;
        container.appendChild(row);
    }

    function addGatewayRow() {
        const container = document.getElementById('gateway-rows');
        const row = document.createElement('div');
        row.className = 'repeatable-row';
        row.innerHTML = `
            <select name="gateway_method[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Gateway Method</option>
                <?php foreach ($gateway_methods as $method => $brands): ?>
                    <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="gateway_brand[]" class="glass rounded-xl px-4 py-2" style="flex:1;">
                <option value="">Finance Method</option>
                        <?php foreach ($gateway_methods as $method => $brands): ?>
                    <?php foreach ($brands as $brand): ?>
                        <option data-method="<?= htmlspecialchars($method) ?>" class="gw-brand-<?= htmlspecialchars($method) ?>" value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <input type="text" name="gateway_account_number[]" placeholder="Account Number" class="glass rounded-xl px-4 py-2" style="flex:1;">
            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
        `;
        container.appendChild(row);
    }

    // District population based on region
    function populateDistricts(regionSelectId, districtSelectId) {
        const regionSelect = document.getElementById(regionSelectId);
        const districtSelect = document.getElementById(districtSelectId);
        const districts = <?= json_encode($districts_by_region) ?>;
        const initialDistrict = districtSelect.dataset.selected || '';

        regionSelect.addEventListener('change', function() {
            const selected = this.value;
            districtSelect.innerHTML = '<option value="">Select District</option>';
            if (selected && districts[selected]) {
                districts[selected].forEach(function(d) {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    districtSelect.appendChild(opt);
                });
                if (initialDistrict && districts[selected].includes(initialDistrict)) {
                    districtSelect.value = initialDistrict;
                }
            }
        });
        // Trigger initial if region pre-selected
        if (regionSelect.value) {
            regionSelect.dispatchEvent(new Event('change'));
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // For step 2 region/district
        <?php if ($user['user_type'] === 'Personal'): ?>
            populateDistricts('region-select', 'district-select');
        <?php else: ?>
            populateDistricts('region-select-org', 'district-select-org');
        <?php endif; ?>

        // For asset rows: region/district dependency (they are dynamic, but we can add on change)
        // We'll handle it globally by listening to changes on asset_region selects
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'asset_region[]') {
                const region = e.target.value;
                const districtSelect = e.target.parentElement.querySelector('select[name="asset_district[]"]');
                const districts = <?= json_encode($districts_by_region) ?>;
                districtSelect.innerHTML = '<option value="">District</option>';
                if (region && districts[region]) {
                    districts[region].forEach(function(d) {
                        const opt = document.createElement('option');
                        opt.value = d;
                        opt.textContent = d;
                        districtSelect.appendChild(opt);
                    });
                }
            }
        });

        // Also for job category/title dependency
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'job_category[]') {
                const cat = e.target.value;
                const titleSelect = e.target.parentElement.querySelector('select[name="job_title[]"]');
                titleSelect.querySelectorAll('option').forEach(opt => {
                    if (opt.value === '') return;
                    opt.style.display = (cat && opt.dataset.category === cat) ? '' : 'none';
                });
                titleSelect.value = '';
            }
        });

        // Gateway method/brand dependency
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'gateway_method[]') {
                const method = e.target.value;
                const brandSelect = e.target.parentElement.querySelector('select[name="gateway_brand[]"]');
                brandSelect.querySelectorAll('option').forEach(opt => {
                    if (opt.value === '') return;
                    // Use data-method attribute for reliable matching (handles spaces)
                    opt.style.display = (opt.dataset.method === method) ? '' : 'none';
                });
                brandSelect.value = '';
            }
        });

        // Asset category/name/quality dependency
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'asset_category[]') {
                const cat = e.target.value;
                const nameSelect = e.target.parentElement.querySelector('select[name="asset_name[]"]');
                nameSelect.querySelectorAll('option').forEach(opt => {
                    if (opt.value === '') return;
                    opt.style.display = opt.classList.contains('asset-name-' + cat) ? '' : 'none';
                });
                nameSelect.value = '';
                // Also clear quality
                const qualitySelect = e.target.parentElement.querySelector('select[name="asset_quality[]"]');
                qualitySelect.value = '';
            }
            if (e.target && e.target.name === 'asset_name[]') {
                const name = e.target.value;
                const catSelect = e.target.parentElement.querySelector('select[name="asset_category[]"]');
                const cat = catSelect.value;
                const qualitySelect = e.target.parentElement.querySelector('select[name="asset_quality[]"]');
                qualitySelect.querySelectorAll('option').forEach(opt => {
                    if (opt.value === '') return;
                    opt.style.display = opt.classList.contains('asset-quality-' + cat + '-' + name) ? '' : 'none';
                });
                qualitySelect.value = '';
            }
        });

        // Set initial visibility for job rows
        document.querySelectorAll('select[name="job_category[]"]').forEach(sel => {
            sel.dispatchEvent(new Event('change'));
        });
        document.querySelectorAll('select[name="gateway_method[]"]').forEach(sel => {
            sel.dispatchEvent(new Event('change'));
        });
        document.querySelectorAll('select[name="asset_category[]"]').forEach(sel => {
            sel.dispatchEvent(new Event('change'));
        });

        // Restore step from query string when returning from save-and-continue
        const urlParams = new URLSearchParams(window.location.search);
        const requestedStep = parseInt(urlParams.get('step'), 10);
        if (requestedStep && requestedStep >= 1 && requestedStep <= totalSteps) {
            goToStep(requestedStep);
        }

        const updateForm = document.getElementById('update-form');
        if (updateForm) {
            updateForm.addEventListener('submit', function() {
                document.querySelectorAll('.step').forEach(el => {
                    el.style.visibility = 'visible';
                    el.style.height = 'auto';
                    el.style.overflow = 'visible';
                });
            });
        }
    });

    // Initially activate step 1
    goToStep(1);
</script>
</body>
</html>