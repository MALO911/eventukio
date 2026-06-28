<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Only logged-in users can access
if (!isLoggedIn()) {
    redirect('pages/login.php');
}

// Only verified users can create events (per Blueprint Chapter 6)
$user = getCurrentUser();
if ($user['user_validity'] !== 'Verified') {
    errorMsg("Please verify your account to access this page.");
    redirect('pages/update-profile.php');
}

$user_id = getCurrentUserId();

// ============================================================
// STEP HANDLING
// ============================================================
$current_step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$event_id = clean($_GET['event_id'] ?? '');

// Load existing event data if editing
$event = null;
$ad_images = null;
$ad_video = null;
$fundraise = null;
$fundraise_tags = [];
$existing_rentals = [];

if ($event_id) {
    $stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE event_id = ? AND host_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch();
    
    if ($event) {
        // Load ad images
        $imgStmt = $pdo->prepare("SELECT * FROM event_ad_images WHERE event_id = ? ORDER BY images_upload_date DESC, images_upload_time DESC LIMIT 1");
        $imgStmt->execute([$event_id]);
        $ad_images = $imgStmt->fetch();
        
        // Load ad video
        $vidStmt = $pdo->prepare("SELECT * FROM event_ad_video WHERE event_id = ? ORDER BY video_upload_date DESC, video_upload_time DESC LIMIT 1");
        $vidStmt->execute([$event_id]);
        $ad_video = $vidStmt->fetch();
        
        // Load fundraise info (participation fee)
        $fundStmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE event_id = ? AND fundraise_type = 'Contribution' LIMIT 1");
        $fundStmt->execute([$event_id]);
        $fundraise = $fundStmt->fetch();
        
        if ($fundraise) {
            $tagStmt = $pdo->prepare("SELECT * FROM event_fundraise_tags WHERE event_id = ? AND fundraise_id = ? AND tag_validity = 'Valid'");
            $tagStmt->execute([$event_id, $fundraise['fundraise_id']]);
            $fundraise_tags = $tagStmt->fetchAll();
        }
        
        // Load existing rentals
        $rentStmt = $pdo->prepare("SELECT * FROM event_asset_rentals WHERE event_id = ?");
        $rentStmt->execute([$event_id]);
        $existing_rentals = $rentStmt->fetchAll();
    }
}

// Determine if participation fee is ON
$feeOn = false;
if ($event && $event['participation_fee'] === 'Present') {
    $feeOn = true;
} elseif (!empty($fundraise)) {
    $feeOn = true;
}

// ============================================================
// LOAD DATA FOR DROPDOWNS
// ============================================================

// Event categories (from Blueprint)
$categories = [
    'Anniversary', 'Baby showers', 'Bachelor party', 'Birthday', 'Bridal shower',
    'Carnival', 'Charity gala', 'Concert', 'Conferences', 'Convention',
    'Corporate mixer', 'Dance party', 'Engagement party', 'Exhibition', 'Fair',
    'Family reunion', 'Festival', 'Funeral', 'Game night', 'Graduation',
    'Grand opening', 'Launch party', 'Mass', 'Music festival', 'Opera',
    'Other parties', 'Picnic', 'Prom', 'Potluck', 'Retirement party',
    'School reunion', 'Sporting event', 'Surprise party', 'Trade show',
    'Wedding', 'Workers party'
];

// Participation types (from Blueprint)
$participation_types = ['Single', 'Double', 'Triple', 'Quad', 'Quint', 'Sextuple', 'Septuple', 'Octuple', 'Nonuple', 'Decuple'];
$participation_badges = ['Normal', 'VIP', 'VVIP', 'Special VIP', 'Super VIP'];

// Fetch available venues (asset_category = 'Venue', asset_status = 'Available', owner verified)
$venues_stmt = $pdo->prepare("
    SELECT a.*, u.user_full_name, u.user_id 
    FROM user_event_asset a
    JOIN user_basic_info u ON a.owner_id = u.user_id
    WHERE a.asset_category = 'Venue' 
      AND a.asset_status = 'Available' 
      AND u.user_validity = 'Verified'
");
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct regions for venue filter
$regions_stmt = $pdo->query("SELECT DISTINCT asset_region FROM user_event_asset WHERE asset_category = 'Venue' AND asset_status = 'Available' ORDER BY asset_region");
$regions = $regions_stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { 
            background: rgba(255,255,255,0.12); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255,255,255,0.25); 
        }
        .step { display: none; }
        .step.active { display: block; }
        
        /* Toggle Switch */
        .toggle-switch { 
            position: relative; 
            width: 60px; 
            height: 30px; 
            background: #ccc; 
            border-radius: 30px; 
            cursor: pointer; 
            transition: 0.3s; 
        }
        .toggle-switch.active { background: #4F46E5; }
        .toggle-switch .slider { 
            position: absolute; 
            top: 3px; 
            left: 3px; 
            width: 24px; 
            height: 24px; 
            background: white; 
            border-radius: 50%; 
            transition: 0.3s; 
        }
        .toggle-switch.active .slider { left: 33px; }
        
        /* Repeatable rows */
        .repeatable-row { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            margin-bottom: 10px; 
            flex-wrap: wrap; 
        }
        .repeatable-row select, .repeatable-row input { 
            flex: 1; 
            min-width: 120px; 
            padding: 10px; 
            border-radius: 12px; 
            border: 1px solid #ddd; 
        }
        .remove-row { 
            color: red; 
            cursor: pointer; 
            padding: 0 8px;
            font-size: 18px;
        }
        
        /* Venue cards */
        .venue-card { 
            padding: 15px; 
            border-radius: 20px; 
            margin-bottom: 10px; 
        }
        
        /* Modal overlay */
        .modal-overlay { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(8px); 
            z-index: 1000; 
            justify-content: center; 
            align-items: center; 
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; 
            border-radius: 2rem; 
            padding: 2rem; 
            max-width: 500px; 
            width: 90%; 
            max-height: 80vh; 
            overflow-y: auto; 
        }
        .modal-box label { display: block; margin-bottom: 0.3rem; font-weight: 500; }
        .modal-box input, .modal-box select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 12px; 
            margin-bottom: 1rem; 
        }
        .modal-box .readonly { background: #f3f4f6; }
        
        /* Filter section */
        .filter-group { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            margin-bottom: 20px; 
        }
        .filter-group input, .filter-group select { 
            flex: 1; 
            min-width: 150px; 
            padding: 10px; 
            border-radius: 12px; 
            border: 1px solid #ddd; 
        }
        
        /* File preview */
        .preview-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .preview-item { 
            position: relative; 
            width: 100px; 
            height: 100px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            overflow: hidden; 
        }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item video { width: 100%; height: 100%; object-fit: cover; }
        .preview-item .remove-preview { 
            position: absolute; 
            top: -8px; 
            right: -8px; 
            background: red; 
            color: white; 
            border-radius: 50%; 
            width: 20px; 
            height: 20px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            font-size: 12px; 
        }
        
        /* Progress bar */
        .progress-step {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
        }
        .step-circle.active { background: #4F46E5; color: white; }
        .step-circle.completed { background: #10B981; color: white; }
        .step-circle.inactive { background: #e5e7eb; color: #9ca3af; }
        .step-line {
            width: 40px;
            height: 2px;
            background: #e5e7eb;
        }
        .step-line.completed { background: #10B981; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<!-- Header -->
<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Create New Event</h1>
        <a href="events.php" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-times"></i>
        </a>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
    <form id="create-event-form" method="POST" action="create-event_process.php" enctype="multipart/form-data">
        <!-- Hidden fields -->
        <input type="hidden" name="current_step" id="current_step" value="<?= $current_step ?>">
        <input type="hidden" name="event_id" id="event_id" value="<?= htmlspecialchars($event_id) ?>">
        <input type="hidden" name="participation_fee" id="participation_fee" value="<?= $feeOn ? 'Present' : 'Absent' ?>">
        <input type="hidden" name="venue_rentals" id="venue_rentals" value="">

        <!-- Progress Indicator -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-2">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="step-circle <?= ($i < $current_step) ? 'completed' : (($i == $current_step) ? 'active' : 'inactive') ?>">
                        <?= $i ?>
                    </div>
                    <?php if ($i < 4): ?>
                        <div class="step-line <?= ($i < $current_step) ? 'completed' : '' ?>"></div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <span class="text-sm text-gray-600">Step <?= $current_step ?> of 4</span>
        </div>

        <!-- ============================================================ -->
        <!-- STEP 1: Event Basics (Event Type + Category)                   -->
        <!-- ============================================================ -->
        <div id="step-1" class="step<?= $current_step === 1 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 1: Event Basics</h2>
            <p class="text-sm text-gray-600 mb-6">Choose the type and category of your event.</p>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Event Type *</label>
                    <select name="event_type" required class="glass w-full rounded-2xl px-5 py-4">
                        <option value="Public"<?= (isset($event['event_type']) && $event['event_type'] === 'Public') ? ' selected' : '' ?>>Public</option>
                        <option value="Private"<?= (isset($event['event_type']) && $event['event_type'] === 'Private') ? ' selected' : '' ?>>Private</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Event Category *</label>
                    <select name="event_category" required class="glass w-full rounded-2xl px-5 py-4">
                        <option value="">-- Select category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"<?= (isset($event['event_category']) && $event['event_category'] === $cat) ? ' selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end mt-8">
                <button type="button" onclick="saveAndProceed(1)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-full font-semibold transition">
                    Save & Proceed <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- STEP 2: Event Details                                         -->
        <!-- ============================================================ -->
        <div id="step-2" class="step<?= $current_step === 2 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 2: Event Details</h2>
            <p class="text-sm text-gray-600 mb-6">Fill in the details about your event.</p>
            
            <div class="space-y-4">
                <!-- Event Title -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Event Title *</label>
                    <input type="text" name="event_title" required 
                           class="glass w-full rounded-2xl px-5 py-4" 
                           placeholder="e.g. Birthday ya Mjukuu Wangu"
                           value="<?= htmlspecialchars($event['event_title'] ?? '') ?>">
                </div>
                
                <!-- Extra Details -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Extra Details (optional)</label>
                    <textarea name="event_extra_detail" rows="3" 
                              class="glass w-full rounded-2xl px-5 py-4" 
                              placeholder="You can @mention other users here..."><?= htmlspecialchars($event['event_extra_detail'] ?? '') ?></textarea>
                </div>
                
                <!-- Advertisement Media -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Event Advertisement Media</label>
                    <select name="event_ad_media" id="event_ad_media" class="glass w-full rounded-2xl px-5 py-4 mb-2">
                        <option value="Image"<?= (isset($event['event_ad_media']) && $event['event_ad_media'] === 'Image') ? ' selected' : '' ?>>Images (up to 4 images)</option>
                        <option value="Video"<?= (isset($event['event_ad_media']) && $event['event_ad_media'] === 'Video') ? ' selected' : '' ?>>Video (1 video)</option>
                    </select>
                    
                    <div class="file-input-wrapper">
                        <input type="file" name="ad_media[]" id="ad_media" 
                               accept="image/*,video/*,.mkv" multiple 
                               class="glass w-full rounded-2xl px-5 py-4 cursor-pointer">
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="file-help-text">
                        <?php if (isset($event['event_ad_media']) && $event['event_ad_media'] === 'Video'): ?>
                            Select 1 video file (MP4, MKV, WebM, AVI)
                        <?php else: ?>
                            Select up to 4 images (JPG, PNG, GIF, JFIF, etc.)
                        <?php endif; ?>
                    </p>
                    
                    <!-- Preview container -->
                    <div id="preview-container" class="preview-container">
                        <?php if ($ad_images): ?>
                            <?php foreach (['image_a', 'image_b', 'image_c', 'image_d'] as $col): ?>
                                <?php if (!empty($ad_images[$col])): ?>
                                    <div class="preview-item">
                                        <img src="../<?= htmlspecialchars($ad_images[$col]) ?>" alt="Existing image">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($ad_video): ?>
                            <div class="preview-item" style="width:200px;height:120px;">
                                <video controls>
                                    <source src="../<?= htmlspecialchars($ad_video['video_uploaded']) ?>">
                                </video>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Event Date & Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1 font-medium">Event Date *</label>
                        <input type="date" name="event_date" id="event_date" required 
                               class="glass w-full rounded-2xl px-5 py-4" 
                               value="<?= htmlspecialchars($event['event_date'] ?? '') ?>">
                        <p class="text-xs text-gray-500 mt-1">Must be at least 1 day from today</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1 font-medium">Starting Time *</label>
                        <input type="time" name="event_time" id="event_time" required 
                               class="glass w-full rounded-2xl px-5 py-4" 
                               value="<?= htmlspecialchars($event['event_time'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- Event Duration -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Event Duration *</label>
                    <select name="event_duration" id="event_duration" class="glass w-full rounded-2xl px-5 py-4">
                        <option value="1"<?= (isset($event['termination_date']) && $event['termination_date'] == date('Y-m-d', strtotime($event['event_date'] . ' + 0 days'))) ? ' selected' : '' ?>>1 day</option>
                        <option value="2"<?= (isset($event['termination_date']) && $event['termination_date'] == date('Y-m-d', strtotime($event['event_date'] . ' + 1 days'))) ? ' selected' : '' ?>>2 days</option>
                        <option value="3"<?= (isset($event['termination_date']) && $event['termination_date'] == date('Y-m-d', strtotime($event['event_date'] . ' + 2 days'))) ? ' selected' : '' ?>>3 days</option>
                        <option value="4"<?= (isset($event['termination_date']) && $event['termination_date'] == date('Y-m-d', strtotime($event['event_date'] . ' + 3 days'))) ? ' selected' : '' ?>>4 days</option>
                        <option value="5"<?= (isset($event['termination_date']) && $event['termination_date'] == date('Y-m-d', strtotime($event['event_date'] . ' + 4 days'))) ? ' selected' : '' ?>>5 days</option>
                    </select>
                    <input type="hidden" name="termination_date" id="termination_date" value="<?= htmlspecialchars($event['termination_date'] ?? '') ?>">
                    <input type="hidden" name="termination_time" id="termination_time" value="<?= htmlspecialchars($event['termination_time'] ?? '') ?>">
                </div>
                
                <!-- Number of Tickets -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1 font-medium">Number of Tickets *</label>
                    <input type="number" name="event_tickets" required min="1" 
                           class="glass w-full rounded-2xl px-5 py-4" 
                           value="<?= htmlspecialchars($event['event_tickets'] ?? 100) ?>">
                </div>
            </div>
            
            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(1)" class="text-gray-600 hover:text-gray-800 px-6 py-3 rounded-full">
                    <i class="fa fa-arrow-left mr-2"></i> Back
                </button>
                <button type="button" onclick="saveAndProceed(2)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-full font-semibold transition">
                    Save & Proceed <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- STEP 3: Entry Fee & Participation Options                     -->
        <!-- ============================================================ -->
        <div id="step-3" class="step<?= $current_step === 3 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 3: Entry Fee & Participation Options</h2>
            <p class="text-sm text-gray-600 mb-6">Configure whether participants need to pay to attend.</p>
            
            <div class="mb-6">
                <p class="text-gray-700 font-medium">Will this event have an entry fee for the participants?</p>
                <div class="flex items-center gap-4 mt-2">
                    <span class="text-sm font-medium">No</span>
                    <div id="fee-toggle" class="toggle-switch <?= $feeOn ? 'active' : '' ?>" onclick="toggleFee()">
                        <div class="slider"></div>
                    </div>
                    <span class="text-sm font-medium">Yes</span>
                </div>
            </div>
            
            <div id="fee-options" style="display: <?= $feeOn ? 'block' : 'none' ?>;">
                <p class="text-sm text-gray-600 mb-4">Define participation packages (repeatable). Each package includes a type, badge, and amount.</p>
                <div id="fee-rows">
                    <?php if (!empty($fundraise_tags)): ?>
                        <?php foreach ($fundraise_tags as $tag): ?>
                            <div class="repeatable-row">
                                <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
                                    <option value="">-- Type --</option>
                                    <?php foreach ($participation_types as $pt): ?>
                                        <option value="<?= htmlspecialchars($pt) ?>"<?= ($tag['tag_name'] === $pt) ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($pt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
                                    <option value="">-- Badge --</option>
                                    <?php foreach ($participation_badges as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>"<?= ($tag['tag_details'] === $b) ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($b) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" 
                                       class="glass rounded-xl px-4 py-2" step="0.01" 
                                       value="<?= htmlspecialchars($tag['required_amount'] ?? '') ?>">
                                <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times-circle"></i></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="repeatable-row">
                            <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
                                <option value="">-- Type --</option>
                                <?php foreach ($participation_types as $pt): ?>
                                    <option value="<?= htmlspecialchars($pt) ?>"><?= htmlspecialchars($pt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
                                <option value="">-- Badge --</option>
                                <?php foreach ($participation_badges as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" 
                                   class="glass rounded-xl px-4 py-2" step="0.01">
                            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times-circle"></i></span>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addFeeRow()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mt-2">
                    <i class="fa fa-plus"></i> Add participation type
                </button>
            </div>
            
            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(2)" class="text-gray-600 hover:text-gray-800 px-6 py-3 rounded-full">
                    <i class="fa fa-arrow-left mr-2"></i> Back
                </button>
                <button type="button" onclick="saveAndProceed(3)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-full font-semibold transition">
                    Save & Proceed <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- STEP 4: Venue Selection (Optional)                            -->
        <!-- ============================================================ -->
        <div id="step-4" class="step<?= $current_step === 4 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 4: Venue Selection</h2>
            <p class="text-sm text-gray-600 mb-2">You may select a venue for your event. <strong>This step is optional</strong> — you can skip it.</p>
            
            <!-- Filter Section -->
            <div class="filter-group">
                <input type="text" id="venue-search" placeholder="Search by owner name..." class="glass rounded-xl px-4 py-2">
                <select id="venue-region" class="glass rounded-xl px-4 py-2">
                    <option value="">All Regions</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="venue-district" class="glass rounded-xl px-4 py-2">
                    <option value="">All Districts</option>
                </select>
                <button type="button" onclick="filterVenues()" class="bg-indigo-600 text-white px-4 py-2 rounded-full">
                    <i class="fa fa-search"></i> Filter
                </button>
            </div>
            
            <!-- Venue List -->
            <div id="venue-list" class="space-y-3">
                <!-- Dynamically populated via JS -->
            </div>
            
            <!-- Navigation -->
            <div class="flex justify-between mt-8">
                <button type="button" onclick="goToStep(3)" class="text-gray-600 hover:text-gray-800 px-6 py-3 rounded-full">
                    <i class="fa fa-arrow-left mr-2"></i> Back
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-full font-semibold transition">
                    <i class="fa fa-check mr-2"></i> Create Event
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- RENTAL MODAL (Place Order / Negotiate)                        -->
<!-- ============================================================ -->
<div id="rental-modal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modal-title" class="text-xl font-bold mb-4">Place Order</h3>
        <form id="rental-form">
            <input type="hidden" id="rental-asset-id">
            <input type="hidden" id="rental-action" value="order">
            
            <label>Renting Price per Unit (TZS)</label>
            <input type="text" id="rental-price" class="readonly" readonly>
            
            <label>Available Quantity</label>
            <input type="text" id="rental-available" class="readonly" readonly>
            
            <label>Renting Quantity</label>
            <input type="number" id="rental-quantity" min="1" required>
            
            <label>Total Amount (TZS)</label>
            <input type="text" id="rental-total" class="readonly" readonly>
            
            <div id="negotiate-field" style="display:none;">
                <label>Suggest New Renting Price per Unit (TZS)</label>
                <input type="number" id="rental-suggest-price" step="0.01">
            </div>
            
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeRentalModal()" class="px-4 py-2 bg-gray-200 rounded-full hover:bg-gray-300">Cancel</button>
                <button type="button" id="rental-submit-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-full hover:bg-indigo-700">Request</button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// STEP NAVIGATION
// ================================================================

function goToStep(step) {
    window.location.href = 'create-event.php?step=' + step + '&event_id=' + encodeURIComponent(document.getElementById('event_id').value);
}

function saveAndProceed(step) {
    if (!validateStep(step)) return;
    document.getElementById('current_step').value = step;
    document.getElementById('create-event-form').submit();
}

function validateStep(step) {
    if (step === 1) {
        const cat = document.querySelector('select[name="event_category"]').value;
        if (!cat) { alert('Please select an event category.'); return false; }
        return true;
    }
    if (step === 2) {
        const title = document.querySelector('input[name="event_title"]').value.trim();
        if (!title) { alert('Please enter an event title.'); return false; }
        
        const date = document.getElementById('event_date').value;
        if (!date) { alert('Please select an event date.'); return false; }
        
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const selected = new Date(date + 'T00:00:00');
        const diffDays = Math.ceil((selected - now) / (1000 * 60 * 60 * 24));
        if (diffDays < 1) { alert('Event date must be at least 1 day from today.'); return false; }
        
        const time = document.getElementById('event_time').value;
        if (!time) { alert('Please select starting time.'); return false; }
        
        // Set termination date & time based on duration
        const duration = parseInt(document.getElementById('event_duration').value);
        const termDate = new Date(selected);
        termDate.setDate(termDate.getDate() + duration - 1);
        document.getElementById('termination_date').value = termDate.toISOString().split('T')[0];
        document.getElementById('termination_time').value = time;
        
        return true;
    }
    if (step === 3) {
        const feeOn = document.getElementById('fee-toggle').classList.contains('active');
        if (feeOn) {
            const rows = document.querySelectorAll('#fee-rows .repeatable-row');
            let valid = false;
            rows.forEach(row => {
                const type = row.querySelector('select[name="participation_type[]"]').value;
                const badge = row.querySelector('select[name="participation_badge[]"]').value;
                const amount = row.querySelector('input[name="participation_amount[]"]').value;
                if (type && badge && amount) valid = true;
            });
            if (!valid) { alert('Please add at least one complete participation package.'); return false; }
        }
        return true;
    }
    return true;
}

// ================================================================
// FEE TOGGLE
// ================================================================

function toggleFee() {
    const toggle = document.getElementById('fee-toggle');
    toggle.classList.toggle('active');
    const options = document.getElementById('fee-options');
    const hidden = document.getElementById('participation_fee');
    
    if (toggle.classList.contains('active')) {
        options.style.display = 'block';
        hidden.value = 'Present';
    } else {
        options.style.display = 'none';
        hidden.value = 'Absent';
    }
}

function addFeeRow() {
    const container = document.getElementById('fee-rows');
    const row = document.createElement('div');
    row.className = 'repeatable-row';
    row.innerHTML = `
        <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
            <option value="">-- Type --</option>
            <?php foreach ($participation_types as $pt): ?>
                <option value="<?= htmlspecialchars($pt) ?>"><?= htmlspecialchars($pt) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
            <option value="">-- Badge --</option>
            <?php foreach ($participation_badges as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" class="glass rounded-xl px-4 py-2" step="0.01">
        <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times-circle"></i></span>
    `;
    container.appendChild(row);
}

// ================================================================
// MEDIA UPLOAD PREVIEW
// ================================================================

document.getElementById('ad_media').addEventListener('change', function(e) {
    const container = document.getElementById('preview-container');
    const files = this.files;
    const mediaType = document.getElementById('event_ad_media').value;
    
    // Clear existing previews (keep existing from server if any)
    // We'll just add new ones
    if (files.length === 0) return;
    
    if (mediaType === 'Image') {
        // Limit to 4 images total (including existing)
        const existing = container.querySelectorAll('.preview-item').length;
        const maxNew = Math.min(files.length, 4 - existing);
        
        for (let i = 0; i < maxNew; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                const div = document.createElement('div');
                div.className = 'preview-item';
                
                reader.onload = function(e) {
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${i+1}">
                        <span class="remove-preview" onclick="this.parentElement.remove()">×</span>
                    `;
                };
                reader.readAsDataURL(file);
                container.appendChild(div);
            }
        }
        if (files.length > 4 - existing) {
            alert('Maximum 4 images total allowed.');
        }
    } else if (mediaType === 'Video') {
        // Remove existing video previews
        const existingVideos = container.querySelectorAll('.preview-item video');
        existingVideos.forEach(el => el.closest('.preview-item').remove());
        
        if (files.length > 1) {
            alert('Only 1 video file is allowed.');
            this.value = '';
            return;
        }
        const file = files[0];
        if (file && file.type.startsWith('video/')) {
            const reader = new FileReader();
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.style.width = '200px';
            div.style.height = '120px';
            
            reader.onload = function(e) {
                div.innerHTML = `
                    <video controls>
                        <source src="${e.target.result}" type="${file.type}">
                    </video>
                    <span class="remove-preview" onclick="this.parentElement.remove()">×</span>
                `;
            };
            reader.readAsDataURL(file);
            container.appendChild(div);
        }
    }
});

document.getElementById('event_ad_media').addEventListener('change', function() {
    const input = document.getElementById('ad_media');
    const helpText = document.getElementById('file-help-text');
    const previewContainer = document.getElementById('preview-container');
    
    // Clear only new previews (keep existing from server?)
    // We'll clear all and re-add existing from server if needed
    // For simplicity, we'll let the user manage previews manually
    
    if (this.value === 'Image') {
        input.setAttribute('accept', 'image/*');
        input.setAttribute('multiple', 'multiple');
        helpText.textContent = 'Select up to 4 images (JPG, PNG, GIF, JFIF, etc.)';
    } else {
        input.setAttribute('accept', 'video/*,.mkv');
        input.removeAttribute('multiple');
        helpText.textContent = 'Select 1 video file (MP4, MKV, WebM, AVI)';
    }
    input.value = '';
});

// ================================================================
// VENUE MANAGEMENT
// ================================================================

let allVenues = <?= json_encode($venues) ?>;

function populateVenues() {
    filterVenues();
    
    document.getElementById('venue-region').addEventListener('change', function() {
        const region = this.value;
        const districtSelect = document.getElementById('venue-district');
        districtSelect.innerHTML = '<option value="">All Districts</option>';
        if (region) {
            const districts = [...new Set(allVenues.filter(v => v.asset_region === region).map(v => v.asset_district))];
            districts.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                districtSelect.appendChild(opt);
            });
        }
        filterVenues();
    });
    
    document.getElementById('venue-search').addEventListener('input', filterVenues);
    document.getElementById('venue-district').addEventListener('change', filterVenues);
}

function filterVenues() {
    const search = document.getElementById('venue-search').value.toLowerCase();
    const region = document.getElementById('venue-region').value;
    const district = document.getElementById('venue-district').value;
    
    const filtered = allVenues.filter(v => {
        const nameMatch = v.user_full_name.toLowerCase().includes(search);
        const regionMatch = region === '' || v.asset_region === region;
        const districtMatch = district === '' || v.asset_district === district;
        return nameMatch && regionMatch && districtMatch;
    });
    renderVenues(filtered);
}

function renderVenues(venues) {
    const container = document.getElementById('venue-list');
    if (venues.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No venues available matching your filters.</p>';
        return;
    }
    container.innerHTML = '';
    venues.forEach(v => {
        const card = document.createElement('div');
        card.className = 'glass venue-card';
        card.innerHTML = `
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <p class="font-semibold">${v.user_full_name}</p>
                    <p class="text-sm"><strong>${v.asset_name}</strong> (${v.asset_quality})</p>
                    <p class="text-sm text-gray-600">Price: TZS ${parseFloat(v.asset_price).toFixed(2)} per unit</p>
                    <p class="text-xs text-gray-500">${v.asset_region}, ${v.asset_district}, ${v.asset_street || 'N/A'}</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button type="button" onclick="openRentalModal('${v.asset_id}', '${v.asset_price}', ${v.asset_quantity}, 'order')" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-full text-sm transition">
                        Place Order
                    </button>
                    <button type="button" onclick="openRentalModal('${v.asset_id}', '${v.asset_price}', ${v.asset_quantity}, 'negotiate')" 
                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-full text-sm transition">
                        Negotiate
                    </button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

// ================================================================
// RENTAL MODAL
// ================================================================

let currentRentalAssetId = null;

function openRentalModal(assetId, price, quantity, action) {
    currentRentalAssetId = assetId;
    document.getElementById('rental-asset-id').value = assetId;
    document.getElementById('rental-action').value = action;
    document.getElementById('rental-price').value = parseFloat(price).toFixed(2);
    document.getElementById('rental-available').value = quantity;
    document.getElementById('rental-quantity').value = 1;
    document.getElementById('rental-total').value = price;
    
    if (action === 'negotiate') {
        document.getElementById('modal-title').textContent = 'Negotiate Price';
        document.getElementById('negotiate-field').style.display = 'block';
        document.getElementById('rental-submit-btn').textContent = 'Plead';
        document.getElementById('rental-suggest-price').value = price;
    } else {
        document.getElementById('modal-title').textContent = 'Place Order';
        document.getElementById('negotiate-field').style.display = 'none';
        document.getElementById('rental-submit-btn').textContent = 'Request';
    }
    
    document.getElementById('rental-modal').classList.add('active');
    updateRentalTotal();
}

function updateRentalTotal() {
    const qty = parseInt(document.getElementById('rental-quantity').value) || 0;
    const action = document.getElementById('rental-action').value;
    let pricePerUnit;
    if (action === 'negotiate') {
        pricePerUnit = parseFloat(document.getElementById('rental-suggest-price').value) || 0;
    } else {
        pricePerUnit = parseFloat(document.getElementById('rental-price').value) || 0;
    }
    const total = qty * pricePerUnit;
    document.getElementById('rental-total').value = total.toFixed(2);
}

function closeRentalModal() {
    document.getElementById('rental-modal').classList.remove('active');
}

// Event listeners for rental modal
document.getElementById('rental-quantity').addEventListener('input', updateRentalTotal);
document.getElementById('rental-suggest-price').addEventListener('input', updateRentalTotal);

document.getElementById('rental-submit-btn').addEventListener('click', function() {
    const assetId = document.getElementById('rental-asset-id').value;
    const qty = parseInt(document.getElementById('rental-quantity').value);
    const available = parseInt(document.getElementById('rental-available').value);
    
    if (!qty || qty < 1) {
        alert('Please enter a valid quantity.');
        return;
    }
    if (qty > available) {
        alert('Please enter a quantity less than or equal to the available quantity.');
        return;
    }
    
    const action = document.getElementById('rental-action').value;
    let pricePerUnit;
    if (action === 'negotiate') {
        pricePerUnit = parseFloat(document.getElementById('rental-suggest-price').value);
        if (!pricePerUnit || pricePerUnit <= 0) {
            alert('Please enter a valid suggested price.');
            return;
        }
    } else {
        pricePerUnit = parseFloat(document.getElementById('rental-price').value);
    }
    
    const total = qty * pricePerUnit;
    
    // Build rental data object
    const rental = {
        asset_id: assetId,
        action: action,
        renting_price: pricePerUnit,
        rented_quantity: qty,
        total_renting_price: total
    };
    
    // Append to hidden field as JSON array
    let rentals = [];
    const hidden = document.getElementById('venue_rentals');
    if (hidden.value) {
        try { rentals = JSON.parse(hidden.value); } catch(e) { rentals = []; }
    }
    rentals.push(rental);
    hidden.value = JSON.stringify(rentals);
    
    closeRentalModal();
    alert('Rental request added successfully! You can add more or proceed to create the event.');
});

// Close modal on overlay click
document.getElementById('rental-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRentalModal();
});

// ================================================================
// INITIALIZATION
// ================================================================

// Set initial fee toggle state
(function initFromServer() {
    const feeOn = <?= $feeOn ? 'true' : 'false' ?>;
    const feeToggle = document.getElementById('fee-toggle');
    const feeHidden = document.getElementById('participation_fee');
    
    if (feeOn) {
        feeToggle.classList.add('active');
        document.getElementById('fee-options').style.display = 'block';
        feeHidden.value = 'Present';
    } else {
        feeToggle.classList.remove('active');
        document.getElementById('fee-options').style.display = 'none';
        feeHidden.value = 'Absent';
    }
    
    // If we have existing rentals, populate hidden field
    const existingRentals = <?= json_encode($existing_rentals) ?>;
    if (existingRentals && existingRentals.length) {
        const rentals = existingRentals.map(r => ({
            asset_id: r.asset_id,
            action: (r.renting_status === 'Pleaded') ? 'negotiate' : 'order',
            renting_price: r.renting_price,
            rented_quantity: r.rented_quantity,
            total_renting_price: r.total_renting_price
        }));
        document.getElementById('venue_rentals').value = JSON.stringify(rentals);
    }
    
    // Populate venues
    populateVenues();
})();

</script>
</body>
</html>