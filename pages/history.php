<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();

// ====================== HANDLE POST ACTIONS ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Accept Rental Request
    if (isset($_POST['accept_rental']) && !empty($_POST['rental_id'])) {
        $rental_id = clean($_POST['rental_id']);
        $stmt = $pdo->prepare("UPDATE event_asset_rentals SET lending_status = 'Approved', renting_status = 'Booked' WHERE rental_id = ?");
        $stmt->execute([$rental_id]);
        successMsg("Rental request accepted successfully.");
        header("Location: history.php");
        exit;
    }

    // Deny Rental Request
    if (isset($_POST['deny_rental']) && !empty($_POST['rental_id'])) {
        $rental_id = clean($_POST['rental_id']);
        $stmt = $pdo->prepare("UPDATE event_asset_rentals SET lending_status = 'Denied' WHERE rental_id = ?");
        $stmt->execute([$rental_id]);
        successMsg("Rental request denied.");
        header("Location: history.php");
        exit;
    }

    // Accept Service Hiring
    if (isset($_POST['accept_service']) && !empty($_POST['hire_id'])) {
        $hire_id = clean($_POST['hire_id']);

        // Get event_id and user_id from the hire record
        $stmt = $pdo->prepare("SELECT event_id, user_id FROM event_service_hiring WHERE hire_id = ?");
        $stmt->execute([$hire_id]);
        $hire = $stmt->fetch();

        if ($hire) {
            // Update attendance_status to 'Confirmed' in event_invitees for the Server role
            $stmt2 = $pdo->prepare("
                UPDATE event_invitees
                SET attendance_status = 'Confirmed'
                WHERE event_id = ? AND user_id = ? AND invitation_badge = 'Server'
            ");
            $stmt2->execute([$hire['event_id'], $hire['user_id']]);

            // Also update the service hiring status
            $stmt3 = $pdo->prepare("UPDATE event_service_hiring SET hire_status = 'Hired', service_status = 'Accepted' WHERE hire_id = ?");
            $stmt3->execute([$hire_id]);

            successMsg("Service request accepted.");
        } else {
            errorMsg("Service hiring record not found.");
        }
        header("Location: history.php");
        exit;
    }

    // Deny Service Hiring
    if (isset($_POST['deny_service']) && !empty($_POST['hire_id'])) {
        $hire_id = clean($_POST['hire_id']);
        $stmt = $pdo->prepare("UPDATE event_service_hiring SET hire_status = 'Rejected', service_status = 'Rejected' WHERE hire_id = ?");
        $stmt->execute([$hire_id]);
        successMsg("Service request denied.");
        header("Location: history.php");
        exit;
    }

    // Delete Asset
    if (isset($_POST['delete_asset']) && !empty($_POST['asset_id'])) {
        $asset_id = clean($_POST['asset_id']);
        $stmt = $pdo->prepare("UPDATE user_event_asset SET asset_status = 'Unavailable' WHERE asset_id = ? AND owner_id = ?");
        $stmt->execute([$asset_id, $user_id]);
        successMsg("Asset marked as unavailable.");
        header("Location: history.php");
        exit;
    }

    // Delete Job Profile
    if (isset($_POST['delete_job']) && !empty($_POST['profile_id'])) {
        $profile_id = clean($_POST['profile_id']);
        $stmt = $pdo->prepare("UPDATE user_event_jobs SET job_status = 'Invalid' WHERE profile_id = ? AND user_id = ?");
        $stmt->execute([$profile_id, $user_id]);
        successMsg("Job profile deleted.");
        header("Location: history.php");
        exit;
    }
}



// --- Helper functions (could be moved to functions.php) ---

/**
 * Fetch event memories (Closed events where user is active attendee)
 */
function getEventMemories($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT e.*,
               uea.asset_location_specifics, uea.asset_street, uea.asset_district, uea.asset_region
        FROM event_basic_info e
        JOIN event_attendees ea ON e.event_id = ea.event_id
        LEFT JOIN user_event_asset uea ON e.venue_id = uea.asset_id
        WHERE ea.participant_id = ?
          AND ea.participation_status = 'Active'
          AND e.event_activeness = 'Closed'
        ORDER BY e.termination_date DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Assets Owned – user's own assets that are not Unavailable
 */
function getAssetsOwned($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM user_event_asset 
        WHERE owner_id = ? AND asset_status != 'Unavailable'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Rental Requests – assets of this user that are requested/pleaded for an active event
 */
function getRentalRequests($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT er.*, uea.*, 
               e.event_title, e.event_date, e.event_time, e.event_category,
               e.event_activeness
        FROM event_asset_rentals er
        JOIN user_event_asset uea ON er.asset_id = uea.asset_id
        JOIN event_basic_info e ON er.event_id = e.event_id
        WHERE uea.owner_id = ? 
          AND er.renting_status IN ('Requested', 'Pleaded')
          AND er.lending_status = 'Pending'
          AND e.event_activeness IN ('Created', 'Announced')
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Rental Records – all rentals of user's assets
 */
function getRentalRecords($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT er.*, uea.*,
               e.event_title, e.event_date, e.event_time, e.event_category
        FROM event_asset_rentals er
        JOIN user_event_asset uea ON er.asset_id = uea.asset_id
        JOIN event_basic_info e ON er.event_id = e.event_id
        WHERE uea.owner_id = ?
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Service Profile – user's valid jobs
 */
function getServiceProfile($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM user_event_jobs 
        WHERE user_id = ? AND job_status = 'Valid'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Hiring Requests – user's job hiring requests for active events
 */
function getHiringRequests($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT esh.*, uej.profession_category, uej.profession_title,
               e.event_title, e.event_date, e.event_time, e.event_category,
               e.event_activeness
        FROM event_service_hiring esh
        JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
        JOIN event_basic_info e ON esh.event_id = e.event_id
        WHERE esh.user_id = ? 
          AND esh.hire_status = 'Requested'
          AND e.event_activeness IN ('Created', 'Announced')
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Service Records – all hiring records of this user
 */
function getServiceRecords($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT esh.*, uej.profession_category, uej.profession_title,
               e.event_title, e.event_date, e.event_time, e.event_category
        FROM event_service_hiring esh
        JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
        JOIN event_basic_info e ON esh.event_id = e.event_id
        WHERE esh.user_id = ?
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Fetch data for all tabs
$eventMemories = getEventMemories($user_id, $pdo);
$assetsOwned = getAssetsOwned($user_id, $pdo);
$rentalRequests = getRentalRequests($user_id, $pdo);
$rentalRecords = getRentalRecords($user_id, $pdo);
$serviceProfile = getServiceProfile($user_id, $pdo);
$hiringRequests = getHiringRequests($user_id, $pdo);
$serviceRecords = getServiceRecords($user_id, $pdo);

// Get user type for column labels in Service Profile
$userType = getUserType($user_id, $pdo); // Implement this if needed, or fetch from session


function getUserType($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT user_type FROM user_basic_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}
$userType = getUserType($user_id, $pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .tab-active { border-bottom: 4px solid #6366f1; color: #6366f1; font-weight: 600; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .history-card { transition: all 0.3s ease; }
        .history-card:hover { transform: translateY(-4px); }
        .action-btn { transition: all 0.2s; }
        .action-btn:hover { transform: scale(1.02); }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { font-weight: 600; color: #4a4a6a; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-approved { background: #10b981; color: white; }
        .badge-pending { background: #f59e0b; color: white; }
        .badge-denied { background: #ef4444; color: white; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen pb-24">

<!-- HEADER -->
<header class="glass sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">History</h1>
        <a href="account.php" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-arrow-left"></i> Return
        </a>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- TABS -->
    <div class="flex border-b mb-8 overflow-x-auto">
        <button onclick="switchTab(0)" class="tab px-6 py-4 tab-active" id="tab0">
            <i class="fa fa-clock"></i> Event Memories
        </button>
        <button onclick="switchTab(1)" class="tab px-6 py-4" id="tab1">
            <i class="fa fa-boxes"></i> Assets
        </button>
        <button onclick="switchTab(2)" class="tab px-6 py-4" id="tab2">
            <i class="fa fa-briefcase"></i> Services
        </button>
    </div>

    <!-- TAB 0: Event Memories -->
    <div id="panel0" class="tab-panel active">
        <?php if (empty($eventMemories)): ?>
            <div class="glass rounded-3xl p-16 text-center">
                <p class="text-gray-500">No closed events in your memory yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($eventMemories as $event): 
                    $location = implode(', ', array_filter([
                        $event['asset_location_specifics'],
                        $event['asset_street'],
                        $event['asset_district'],
                        $event['asset_region']
                    ])) ?: 'Location not specified';
                ?>
                    <div class="history-card glass rounded-3xl p-6 flex flex-col md:flex-row gap-6">
                        <div class="w-full md:w-48 h-40 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <span class="text-xs font-medium px-3 py-1 bg-white/20 rounded-full">Closed</span>
                                <span class="text-xs text-gray-500"><?= date('d M Y', strtotime($event['termination_date'])) ?></span>
                            </div>
                            <h3 class="text-xl font-semibold mt-3"><?= htmlspecialchars($event['event_title']) ?></h3>
                            <p class="text-indigo-600"><?= htmlspecialchars($event['event_category']) ?></p>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fa fa-calendar"></i> <?= date('d M Y', strtotime($event['event_date'])) ?> at <?= date('H:i', strtotime($event['event_time'])) ?>
                            </p>
                            <p class="text-sm text-gray-600"><i class="fa fa-map-pin"></i> <?= htmlspecialchars($location) ?></p>
                            <div class="mt-6">
                                <a href="event-profile.php?id=<?= $event['event_id'] ?>"
                                   class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-2xl font-medium">
                                    View Memories & Gallery →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 1: Assets -->
    <div id="panel1" class="tab-panel">
        <!-- 1. Assets Owned -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Assets Owned</h3>
            <?php if (empty($assetsOwned)): ?>
                <p class="text-gray-500">You have no assets registered.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset Info</th>
                                <th>Available Quantity</th>
                                <th>Renting Price</th>
                                <th>Asset Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assetsOwned as $asset): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($asset['asset_name']) ?><br>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($asset['asset_quality']) ?> • <?= htmlspecialchars($asset['asset_category']) ?></span><br>
                                        <span class="text-xs text-gray-400"><?= htmlspecialchars($asset['asset_region'] ?? '') ?>, <?= htmlspecialchars($asset['asset_district'] ?? '') ?></span>
                                    </td>
                                    <td><?= $asset['asset_quantity'] ?></td>
                                    <td>TZS <?= number_format($asset['asset_price'], 2) ?></td>
                                    <td><span class="badge <?= $asset['asset_status'] == 'Available' ? 'badge-approved' : 'badge-pending' ?>"><?= $asset['asset_status'] ?></span></td>
                                    <td>
                                        <?php if ($asset['asset_status'] == 'Available'): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this asset? It will be marked as Unavailable.')">
                                                <input type="hidden" name="asset_id" value="<?= $asset['asset_id'] ?>">
                                                <button type="submit" name="delete_asset" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Delete Asset</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Not deletable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Rental Requests -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Rental Requests</h3>
            <?php if (empty($rentalRequests)): ?>
                <p class="text-gray-500">No pending rental requests.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset Info</th>
                                <th>Event Info</th>
                                <th>Rented Quantity</th>
                                <th>Renting Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentalRequests as $req): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($req['asset_name']) ?><br>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($req['asset_quality']) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($req['event_title']) ?><br>
                                        <span class="text-sm text-gray-500"><?= date('d M Y', strtotime($req['event_date'])) ?> at <?= date('H:i', strtotime($req['event_time'])) ?></span>
                                    </td>
                                    <td><?= $req['rented_quantity'] ?></td>
                                    <td>TZS <?= number_format($req['renting_price'], 2) ?></td>
                                    <td>
                                        <div class="flex gap-2">
                                            <form method="POST">
                                                <input type="hidden" name="rental_id" value="<?= $req['rental_id'] ?>">
                                                <button type="submit" name="accept_rental" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-2xl text-sm">Accept</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="rental_id" value="<?= $req['rental_id'] ?>">
                                                <button type="submit" name="deny_rental" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Deny</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Rental Records -->
        <div>
            <h3 class="text-xl font-bold mb-4">Rental Records</h3>
            <?php if (empty($rentalRecords)): ?>
                <p class="text-gray-500">No rental records found.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset Info</th>
                                <th>Event Info</th>
                                <th>Rented Quantity</th>
                                <th>Renting Price</th>
                                <th>Renting Status</th>
                                <th>Lending Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentalRecords as $rec): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($rec['asset_name']) ?><br>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($rec['asset_quality']) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($rec['event_title']) ?><br>
                                        <span class="text-sm text-gray-500"><?= date('d M Y', strtotime($rec['event_date'])) ?></span>
                                    </td>
                                    <td><?= $rec['rented_quantity'] ?></td>
                                    <td>TZS <?= number_format($rec['renting_price'], 2) ?></td>
                                    <td><span class="badge <?= $rec['renting_status'] == 'Booked' ? 'badge-approved' : 'badge-pending' ?>"><?= $rec['renting_status'] ?></span></td>
                                    <td><span class="badge <?= $rec['lending_status'] == 'Approved' ? 'badge-approved' : ($rec['lending_status'] == 'Denied' ? 'badge-denied' : 'badge-pending') ?>"><?= $rec['lending_status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: Services -->
    <div id="panel2" class="tab-panel">
        <!-- 1. Service Profile -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Service Profile</h3>
            <?php if (empty($serviceProfile)): ?>
                <p class="text-gray-500">You have no active job profiles.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($userType == 'Personal'): ?>
                                    <th>Job Title</th>
                                    <th>Number of Events</th>
                                    <th>Average Rating</th>
                                    <th>Action</th>
                                <?php else: ?>
                                    <th>Staff Sector</th>
                                    <th>Events Participated</th>
                                    <th>Average Rating</th>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceProfile as $job): ?>
                                <tr>
                                    <?php if ($userType == 'Personal'): ?>
                                        <td><?= htmlspecialchars($job['profession_title']) ?></td>
                                        <td><?= $job['task_count'] ?></td>
                                        <td><?= number_format($job['job_average_rating'], 1) ?> ⭐</td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars($job['profession_title']) ?></td>
                                        <td><?= $job['task_count'] ?></td>
                                        <td><?= number_format($job['job_average_rating'], 1) ?> ⭐</td>
                                    <?php endif; ?>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this job profile?')">
                                            <input type="hidden" name="profile_id" value="<?= $job['profile_id'] ?>">
                                            <button type="submit" name="delete_job" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Delete Job</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Hiring Requests -->
        <div class="mb-8">
            <h3 class="text-xl font-bold mb-4">Hiring Requests</h3>
            <?php if (empty($hiringRequests)): ?>
                <p class="text-gray-500">No pending hiring requests.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Info</th>
                                <th>Service Required</th>
                                <th>Amount Offered</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hiringRequests as $hire): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($hire['event_title']) ?><br>
                                        <span class="text-sm text-gray-500"><?= date('d M Y', strtotime($hire['event_date'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($hire['profession_category']) ?> > <?= htmlspecialchars($hire['profession_title']) ?></td>
                                    <td>TZS <?= number_format($hire['hire_amount'], 2) ?></td>
                                    <td>
                                        <div class="flex gap-2">
                                            <form method="POST">
                                                <input type="hidden" name="hire_id" value="<?= $hire['hire_id'] ?>">
                                                <button type="submit" name="accept_service" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-2xl text-sm">Accept</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="hire_id" value="<?= $hire['hire_id'] ?>">
                                                <button type="submit" name="deny_service" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Deny</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Service Records -->
        <div>
            <h3 class="text-xl font-bold mb-4">Service Records</h3>
            <?php if (empty($serviceRecords)): ?>
                <p class="text-gray-500">No service records found.</p>
            <?php else: ?>
                <div class="overflow-x-auto glass rounded-3xl p-4">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Info</th>
                                <th>Service Required</th>
                                <th>Amount Offered</th>
                                <th>Request Status</th>
                                <th>Acceptance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceRecords as $rec): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($rec['event_title']) ?><br>
                                        <span class="text-sm text-gray-500"><?= date('d M Y', strtotime($rec['event_date'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($rec['profession_category']) ?> > <?= htmlspecialchars($rec['profession_title']) ?></td>
                                    <td>TZS <?= number_format($rec['hire_amount'], 2) ?></td>
                                    <td><span class="badge <?= $rec['hire_status'] == 'Hired' ? 'badge-approved' : 'badge-pending' ?>"><?= $rec['hire_status'] ?></span></td>
                                    <td><span class="badge <?= $rec['service_status'] == 'Accepted' ? 'badge-approved' : ($rec['service_status'] == 'Rejected' ? 'badge-denied' : 'badge-pending') ?>"><?= $rec['service_status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- STICKY FOOTER -->
<footer class="glass fixed bottom-0 left-0 right-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-around text-sm font-medium">
        <button onclick="switchTab(0)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab0">
            <i class="fa fa-clock"></i> Event Memories
        </button>
        <button onclick="switchTab(1)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab1">
            <i class="fa fa-boxes"></i> Assets
        </button>
        <button onclick="switchTab(2)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab2">
            <i class="fa fa-briefcase"></i> Services
        </button>
    </div>
</footer>

<script>
    function switchTab(n) {
        // Update tabs
        document.querySelectorAll('.tab').forEach((el, i) => {
            el.classList.toggle('tab-active', i === n);
        });
        document.querySelectorAll('.tab-panel').forEach((el, i) => {
            el.classList.toggle('active', i === n);
        });
        // Update footer active style
        document.querySelectorAll('.tab-footer').forEach((el, i) => {
            if (i === n) {
                el.classList.add('text-indigo-700');
                el.classList.remove('text-gray-700');
            } else {
                el.classList.remove('text-indigo-700');
                el.classList.add('text-gray-700');
            }
        });
    }

    // Set initial active footer tab
    document.addEventListener('DOMContentLoaded', function() {
        switchTab(0);
    });
</script>

</body>
</html>