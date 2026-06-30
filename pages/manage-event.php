<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();

/**
 * Insert a service hire + invitee record (shared by Created step 3 and Announced modal).
 */
function insertServiceHire($pdo, $event_id, $service_user_id, $profile_id, $hire_amount, $profession_title) {
    $hire_id = generateHireId();
    $invitee_id = generateInviteeId();

    $stmt = $pdo->prepare("
        INSERT INTO event_service_hiring
            (hire_id, user_id, profile_id, event_id, hire_amount, hire_date, hire_time, hire_status, service_status, invitee_id)
        VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'Requested', 'Pending', ?)
    ");
    $stmt->execute([$hire_id, $service_user_id, $profile_id, $event_id, $hire_amount, $invitee_id]);

    $stmt = $pdo->prepare("
        INSERT INTO event_invitees
            (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category)
        VALUES (?, ?, ?, 'Pending', 'Server', ?, 'Paying')
    ");
    $stmt->execute([$invitee_id, $event_id, $service_user_id, $profession_title]);
}

// ---- Handle POST actions ----
// Cancel event (Created tab)
if (isset($_POST['cancel_event']) && isset($_POST['event_id'])) {
    $eid = (int)$_POST['event_id'];
    $stmt = $pdo->prepare("UPDATE event_basic_info SET event_activeness = 'Closed' WHERE event_id = ? AND host_id = ?");
    $stmt->execute([$eid, $user_id]);
    redirect('pages/manage-event.php');
}

// Announce event (after multi-step form submission)
if (isset($_POST['announce_event']) && isset($_POST['event_id'])) {
    $eid = (int)$_POST['event_id'];
    // In a real implementation, all the collected data from steps 1-5 would be saved here.
    // For now, we just update the status to 'Announced'.
    $stmt = $pdo->prepare("UPDATE event_basic_info SET event_activeness = 'Announced' WHERE event_id = ? AND host_id = ?");
    $stmt->execute([$eid, $user_id]);
    redirect('pages/manage-event.php');
}

// Choose venue (select an approved rental and mark as booked)
if (isset($_POST['choose_venue']) && isset($_POST['rental_id'])) {
    $rental_id = clean($_POST['rental_id']);
    try {
        // Fetch rental, event and asset info
        $infoS = $pdo->prepare("SELECT er.event_id, er.asset_id, er.lending_status, e.host_id, uea.asset_category FROM event_asset_rentals er JOIN event_basic_info e ON er.event_id = e.event_id JOIN user_event_asset uea ON er.asset_id = uea.asset_id WHERE er.rental_id = ? LIMIT 1");
        $infoS->execute([$rental_id]);
        $info = $infoS->fetch();
        if (!$info) {
            throw new Exception('Rental not found');
        }
        if ($info['host_id'] != $user_id) {
            throw new Exception('You are not authorized to choose venue for this event');
        }
        if ($info['asset_category'] !== 'Venue') {
            throw new Exception('Selected asset is not a venue');
        }
        if ($info['lending_status'] !== 'Approved') {
            throw new Exception('Rental must be approved before booking');
        }

        $eventId = (int)$info['event_id'];
        $assetId = $info['asset_id'];

        $pdo->beginTransaction();

        // Postpone other venue rentals for this event
        $updOther = $pdo->prepare("UPDATE event_asset_rentals er JOIN user_event_asset uea ON er.asset_id = uea.asset_id SET er.renting_status = 'Postponed' WHERE er.event_id = ? AND er.rental_id != ? AND uea.asset_category = 'Venue'");
        $updOther->execute([$eventId, $rental_id]);

        // Book selected rental
        $updSel = $pdo->prepare("UPDATE event_asset_rentals SET renting_status = 'Booked', lending_status = 'Booked and Approved' WHERE rental_id = ? AND event_id = ?");
        $updSel->execute([$rental_id, $eventId]);

        // Mark asset as booked
        $updAsset = $pdo->prepare("UPDATE user_event_asset SET asset_status = 'Booked' WHERE asset_id = ?");
        $updAsset->execute([$assetId]);

        // Set venue_id on event_basic_info
        $updEvent = $pdo->prepare("UPDATE event_basic_info SET venue_id = ? WHERE event_id = ? AND host_id = ?");
        $updEvent->execute([$assetId, $eventId, $user_id]);

        $pdo->commit();
        successMsg('Venue chosen and booked successfully.');
        redirect('pages/manage-event.php?process=' . $eventId);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        errorMsg('Failed to choose venue: ' . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Submit hired services (Announced Events modal)
if (isset($_POST['submit_hired_services']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $services = json_decode($_POST['services'] ?? '[]', true);
    $is_ajax = !empty($_POST['ajax']);

    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();

    if ($event_host != $user_id) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'You are not authorized to hire services for this event.']);
            exit;
        }
        errorMsg("You are not authorized to hire services for this event.");
        redirect('pages/manage-event.php');
    }

    if (empty($services)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No services to hire.']);
            exit;
        }
        errorMsg("No services to hire.");
        redirect('pages/manage-event.php');
    }

    try {
        $pdo->beginTransaction();

        foreach ($services as $service) {
            $service_user_id = clean($service['user_id'] ?? '');
            $profile_id = clean($service['profile_id'] ?? '');
            $hire_amount = (float)($service['hire_amount'] ?? 0);
            $profession_title = clean($service['profession_title'] ?? '');

            if (empty($profession_title) || empty($hire_amount) || empty($service_user_id) || empty($profile_id)) {
                continue;
            }

            insertServiceHire($pdo, $event_id, $service_user_id, $profile_id, $hire_amount, $profession_title);
        }

        $pdo->commit();

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Service providers hired successfully!']);
            exit;
        }

        successMsg("Service providers hired successfully!");
        redirect('pages/manage-event.php');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to hire services: ' . $e->getMessage()]);
            exit;
        }
        errorMsg("Failed to hire services: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Submit service requests (Created Events step 3 — Hire Event Services)
if (isset($_POST['submit_service_requests']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $profession_titles = $_POST['profession_title'] ?? [];
    $hire_amounts = $_POST['hire_amount'] ?? [];
    $user_ids = $_POST['user_id'] ?? [];
    $profile_ids = $_POST['profile_id'] ?? [];

    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();

    if ($event_host != $user_id) {
        errorMsg("You are not authorized to hire services for this event.");
        redirect('pages/manage-event.php');
    }

    if (empty($profession_titles) || empty($hire_amounts)) {
        errorMsg("No services to hire.");
        redirect('pages/manage-event.php');
    }

    try {
        $pdo->beginTransaction();

        foreach ($profession_titles as $index => $profession_title) {
            $profession_title = clean($profession_title);
            $hire_amount = (float)($hire_amounts[$index] ?? 0);
            $service_user_id = clean($user_ids[$index] ?? '');
            $profile_id = clean($profile_ids[$index] ?? '');

            if (empty($profession_title) || empty($hire_amount) || empty($service_user_id) || empty($profile_id)) {
                continue;
            }

            insertServiceHire($pdo, $event_id, $service_user_id, $profile_id, $hire_amount, $profession_title);
        }

        $pdo->commit();
        successMsg("Service requests submitted successfully!");
        redirect('pages/manage-event.php');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        errorMsg("Failed to submit service requests: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Submit rental request (Place Order or Negotiate)
if (isset($_POST['submit_rental_request']) && isset($_POST['event_id']) && isset($_POST['asset_id'])) {
    $event_id = (int)$_POST['event_id'];
    $asset_id = clean($_POST['asset_id']);
    $renting_price = (float)$_POST['renting_price'];
    $rented_quantity = (int)$_POST['rented_quantity'];
    $total_renting_price = (float)$_POST['total_renting_price'];
    $renting_status = clean($_POST['renting_status']);
    
    // Check if the user is authorized for this event
    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();
    
    if ($event_host != $user_id) {
        errorMsg("You are not authorized to rent assets for this event.");
        redirect('pages/manage-event.php');
    }
    
    if ($rented_quantity <= 0) {
        errorMsg("Invalid quantity.");
        redirect('pages/manage-event.php');
    }
    
    if (!in_array($renting_status, ['Requested', 'Pleaded'])) {
        errorMsg("Invalid rental status.");
        redirect('pages/manage-event.php');
    }
    
    try {
        $rental_id = generateRentalId();
        
        $stmt = $pdo->prepare("
            INSERT INTO event_asset_rentals 
                (rental_id, event_id, asset_id, renting_price, total_renting_price, rented_quantity, renting_date, renting_time, renting_status, lending_status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Pending')
        ");
        $stmt->execute([$rental_id, $event_id, $asset_id, $renting_price, $total_renting_price, $rented_quantity, $renting_status]);
        
        successMsg("Rental request submitted successfully!");
        redirect('pages/manage-event.php');
    } catch (Exception $e) {
        errorMsg("Failed to submit rental request: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Add schedule item
if (isset($_POST['add_schedule']) && isset($_GET['schedule'])) {
    $event_id = (int)$_GET['schedule'];
    $start_time = clean($_POST['start_time'] ?? '');
    $end_time = clean($_POST['end_time'] ?? '');
    $title = clean($_POST['schedule_title'] ?? '');
    
    // Check if the user is authorized for this event
    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();
    
    if ($event_host != $user_id) {
        errorMsg("You are not authorized to manage schedule for this event.");
        redirect('pages/manage-event.php');
    }
    
    if (empty($start_time) || empty($end_time) || empty($title)) {
        errorMsg("All schedule fields are required.");
        redirect('pages/manage-event.php');
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO event_schedule_timetable 
                (event_id, schedule_start_time, schedule_end_time, schedule_title, schedule_status)
            VALUES (?, ?, ?, ?, 'On schedule')
        ");
        $stmt->execute([$event_id, $start_time, $end_time, $title]);
        successMsg("Schedule item added successfully!");
        redirect('pages/manage-event.php');
    } catch (Exception $e) {
        errorMsg("Failed to add schedule item: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Add new fundraise post action
if (isset($_POST['add_fundraise']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $title = clean($_POST['fundraise_title'] ?? '');
    
    // Check if the user is authorized for this event
    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();
    
    if ($event_host != $user_id) {
        errorMsg("You are not authorized to manage fundraises for this event.");
        redirect('pages/manage-event.php');
    }
    
    $has_goal = isset($_POST['has_goal']) && $_POST['has_goal'] === 'on';
    $goal_category = $has_goal ? 'Limited' : 'Unlimited';
    $goal_amount = $has_goal ? (float)($_POST['required_amount'] ?? 0) : 0.00;
    
    $duration_option = $_POST['fundraise_duration'] ?? '';
    $duration = ($duration_option === 'During the event') ? 'Mid-event' : 'Pre-event';
    
    $has_fixed = isset($_POST['has_fixed_amounts']) && $_POST['has_fixed_amounts'] === 'on';
    $type = $has_fixed ? 'Contribution' : 'Donation';
    
    try {
        $pdo->beginTransaction();
        
        $fundraise_id = generateFundraiseId();
        
        $stmt = $pdo->prepare("
            INSERT INTO event_fundraise_info 
                (fundraise_id, event_id, fundraise_title, fundraise_category, required_amount, collected_amount, fundraise_duration, fundraise_type, fundraise_status, creation_date, creation_time)
            VALUES (?, ?, ?, ?, ?, 0.00, ?, ?, 'Active', CURDATE(), CURTIME())
        ");
        $stmt->execute([
            $fundraise_id,
            $event_id,
            $title,
            $goal_category,
            $goal_amount,
            $duration,
            $type
        ]);
        
        if ($has_fixed) {
            $tag_names = $_POST['tag_name'] ?? [];
            $tag_details = $_POST['tag_details'] ?? [];
            $tag_amounts = $_POST['tag_amount'] ?? [];
            
            $tag_stmt = $pdo->prepare("
                INSERT INTO event_fundraise_tags 
                    (fundraise_tag_id, fundraise_id, event_id, tag_name, tag_details, required_amount, participant_count, tag_validity)
                VALUES (?, ?, ?, ?, ?, ?, 1, 'Valid')
            ");
            
            foreach ($tag_names as $i => $name) {
                if (!empty($name)) {
                    $tag_id = generateFundraiseTagId();
                    $amt = (float)($tag_amounts[$i] ?? 0);
                    $details = $tag_details[$i] ?? '';
                    $tag_stmt->execute([
                        $tag_id,
                        $fundraise_id,
                        $event_id,
                        clean($name),
                        clean($details),
                        $amt
                    ]);
                }
            }
        }
        
        $pdo->commit();
        successMsg("Fundraise created successfully!");
        redirect('pages/manage-event.php');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        errorMsg("Failed to create fundraise: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// Add guest invitation (Created Events step 5)
if (isset($_POST['add_guest']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $guest_name = clean($_POST['guest_name'] ?? '');
    $position = clean($_POST['position'] ?? 'Normal Guest');
    $invitation_category = clean($_POST['invitation_category'] ?? 'Paying');
    $guest_user_id = clean($_POST['guest_user_id'] ?? '');

    // Check if the user is authorized for this event
    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();

    if ($event_host != $user_id) {
        errorMsg("You are not authorized to invite guests for this event.");
        redirect('pages/manage-event.php');
    }

    if (empty($guest_name) || empty($guest_user_id)) {
        errorMsg("Please select a guest to invite.");
        redirect('pages/manage-event.php');
    }

    try {
        $invitee_id = generateInviteeId();

        $stmt = $pdo->prepare("
            INSERT INTO event_invitees
                (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category)
            VALUES (?, ?, ?, 'Pending', 'Normal', ?, ?)
        ");
        $stmt->execute([$invitee_id, $event_id, $guest_user_id, $position, $invitation_category]);

        successMsg("Guest invited successfully!");
        redirect('pages/manage-event.php?process=' . $event_id);
    } catch (Exception $e) {
        errorMsg("Failed to invite guest: " . $e->getMessage());
        redirect('pages/manage-event.php');
    }
}

// ---- Fetch events for the current user ----
// Created events (status = 'Created')
$stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE host_id = ? AND event_activeness = 'Created' ORDER BY event_date ASC, event_time ASC");
$stmt->execute([$user_id]);
$createdEvents = $stmt->fetchAll();

// Announced / In Session / Terminated events
$stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE host_id = ? AND event_activeness IN ('Announced', 'In Session', 'Terminated') ORDER BY event_date DESC, event_time DESC");
$stmt->execute([$user_id]);
$announcedEvents = $stmt->fetchAll();

// For the multi-step form in Created tab, we need to know which event is being processed.
$processingEventId = isset($_GET['process']) ? (int)$_GET['process'] : 0;
$processingEvent = null;
if ($processingEventId) {
    $stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE event_id = ? AND host_id = ? AND event_activeness = 'Created'");
    $stmt->execute([$processingEventId, $user_id]);
    $processingEvent = $stmt->fetch();
    if (!$processingEvent) {
        $processingEventId = 0;
    }
}

// ---- Helper to get event rental details (for Step 2) ----
function getEventRentals($event_id, $pdo) {
    $stmt = $pdo->prepare("SELECT er.*, uea.asset_category, uea.asset_name, uea.asset_quality, ubi.user_full_name 
                           FROM event_asset_rentals er
                           JOIN user_event_asset uea ON er.asset_id = uea.asset_id
                           JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
                           WHERE er.event_id = ? AND uea.asset_category = 'Venue'");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

// ---- Helper to get hired services (for Step 3) ----
function getHiredServices($event_id, $pdo) {
    $stmt = $pdo->prepare("SELECT esh.*, uej.profession_category, uej.profession_title, ubi.user_full_name 
                           FROM event_service_hiring esh
                           JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
                           JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
                           WHERE esh.event_id = ? AND esh.service_status = 'Pending'");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

// ---- Helper to get invited guests (for Step 5) ----
function getInvitedGuests($event_id, $pdo) {
    $stmt = $pdo->prepare("SELECT ei.*, ubi.user_full_name, ubi.user_profile_picture 
                           FROM event_invitees ei
                           JOIN user_basic_info ubi ON ei.user_id = ubi.user_id
                           WHERE ei.event_id = ? AND ei.attendance_status = 'Pending'");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Event - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .tab-active { border-bottom: 4px solid #6366f1; color: #6366f1; font-weight: 600; }
        .tab.tab-active { border-color: #6366f1 !important; color: #6366f1 !important; }
        .event-card { transition: all 0.3s; }
        .event-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
        .accordion-content { display: none; }
        .accordion-content.open { display: block; }
        .step { display: none; }
        .step.active { display: block; }
        .role-btn { transition: 0.2s; }
        .role-btn:hover:not(:disabled) { transform: scale(1.02); }
        .role-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Manage Events</h1>
        <a href="events.php" class="text-gray-700 hover:text-indigo-700"><i class="fa fa-arrow-left"></i> Events</a>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-2xl mb-6 flex items-center justify-between">
            <p class="font-medium"><i class="fa fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']) ?></p>
            <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900"><i class="fa fa-times"></i></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 flex items-center justify-between">
            <p class="font-medium"><i class="fa fa-exclamation-circle mr-2"></i><?= htmlspecialchars($_SESSION['error']) ?></p>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900"><i class="fa fa-times"></i></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>



    <!-- TAB 0: Created Events -->
    <div id="panel0" class="tab-panel">
        <?php if (empty($createdEvents)): ?>
            <div class="glass rounded-3xl p-12 text-center">
                <p class="text-gray-500 text-lg">You have no events in the 'Created' state.</p>
                <a href="create-event.php" class="inline-block mt-4 text-indigo-600 hover:underline">Create a new event</a>
            </div>
        <?php else: ?>
            <!-- List of created event cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($createdEvents as $ev): ?>
                    <div class="event-card glass rounded-3xl p-6">
                        <h3 class="font-bold text-xl"><?= htmlspecialchars($ev['event_title']) ?></h3>
                        <p class="text-sm text-gray-600"><?= date('d M Y', strtotime($ev['event_date'])) ?> at <?= date('H:i', strtotime($ev['event_time'])) ?></p>
                        <div class="flex justify-between mt-4">
                            <a href="?process=<?= $ev['event_id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-2xl text-sm">Proceed</a>
                            <form method="POST" onsubmit="return confirm('Cancel this event? It will be closed permanently.')">
                                <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                                <button type="submit" name="cancel_event" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Cancel</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Multi-step form if processing an event -->
            <?php if ($processingEvent): ?>
                <div class="mt-8 glass rounded-3xl p-6">
                    <h2 class="text-2xl font-bold mb-4">Manage: <?= htmlspecialchars($processingEvent['event_title']) ?></h2>
                    <!-- Step navigation -->
                    <div class="flex justify-between items-center mb-6">
                        <button onclick="changeStep(-1)" class="text-indigo-600 hover:underline" id="stepBack">Back</button>
                        <span id="stepIndicator">Step 1 of 5</span>
                        <button onclick="changeStep(1)" class="text-indigo-600 hover:underline" id="stepNext">Next</button>
                    </div>

                    <!-- Steps container -->
                    <div id="stepContainer">
                        <!-- Step 1: List of events (already done) – but here we show the event card? Actually Step 1 is the list; we are already past that. The blueprint says Step 1 is the card list, but we already have that. So we'll start with Step 2: Venue Selection -->
                        <!-- Step 2: Venue Selection -->
                        <div class="step active" data-step="2">
                            <h3 class="font-semibold text-lg">Venue Selection</h3>
                            <?php $rentals = getEventRentals($processingEvent['event_id'], $pdo); ?>
                            <?php if (empty($rentals)): ?>
                                <p class="text-gray-500">No venue requests yet. You can rent a venue from the <a href="events.php" class="text-indigo-600">Events page</a>.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <?php foreach ($rentals as $rental): ?>
                                        <div class="glass p-4 rounded-2xl">
                                            <p><strong><?= htmlspecialchars($rental['asset_name']) ?></strong> (<?= htmlspecialchars($rental['asset_category']) ?>)</p>
                                            <p>Owner: <?= htmlspecialchars($rental['user_full_name']) ?></p>
                                            <p>Quality: <?= htmlspecialchars($rental['asset_quality']) ?></p>
                                            <p>Total: TZS <?= number_format($rental['total_renting_price'], 2) ?></p>
                                            <p>Status: <?= $rental['lending_status'] ?></p>
                                            <?php if ($rental['lending_status'] == 'Approved'): ?>
                                                <form method="POST" action="?process=<?= $processingEvent['event_id'] ?>">
                                                    <input type="hidden" name="rental_id" value="<?= $rental['rental_id'] ?>">
                                                    <button type="submit" name="choose_venue" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mt-2">Choose Venue</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Step 3: Hire Event Services -->
                        <div class="step" data-step="3">
                            <h3 class="font-semibold text-lg">Hire Event Services</h3>
                            <p class="text-sm text-gray-600">Add service providers you wish to hire.</p>
                            <div class="mt-4">
                                <form method="POST" action="?process=<?= $processingEvent['event_id'] ?>">
                                    <input type="hidden" name="event_id" value="<?= $processingEvent['event_id'] ?>">
                                    <div id="service-rows">
                                        <div class="service-row flex flex-wrap gap-4 items-end mb-4">
                                            <div>
                                                <label class="block text-sm">Hire (server)</label>
                                                <select name="profession_title[]" class="rounded-xl px-4 py-2 glass profession-select">
                                                    <option value="">Select profession</option>
                                                    <?php
                                                    $stmt = $pdo->query("SELECT DISTINCT profession_title FROM user_event_jobs WHERE job_status = 'Valid'");
                                                    while ($row = $stmt->fetch()) {
                                                        echo "<option value='" . htmlspecialchars($row['profession_title']) . "'>" . htmlspecialchars($row['profession_title']) . "</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm">For (TZS)</label>
                                                <input type="number" name="hire_amount[]" class="rounded-xl px-4 py-2 glass" placeholder="Amount">
                                            </div>
                                            <div>
                                                <label class="block text-sm">Name (auto-filled)</label>
                                                <input type="text" name="user_name[]" class="rounded-xl px-4 py-2 glass user-name-input" readonly placeholder="Select from list">
                                            </div>
                                            <input type="hidden" name="user_id[]" class="user-id-input">
                                            <input type="hidden" name="profile_id[]" class="profile-id-input">
                                            <button type="button" onclick="removeServiceRow(this)" class="bg-red-500 text-white px-4 py-2 rounded-2xl remove-btn" style="display:none;">Remove</button>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addServiceRow()" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mb-4">Add</button>
                                    <button type="submit" name="submit_service_requests" class="bg-green-600 text-white px-6 py-3 rounded-2xl font-semibold">Submit Requests</button>
                                </form>
                                <!-- Professionals box (simplified) -->
                                <div class="mt-4 glass p-4 rounded-2xl">
                                    <h4 class="font-semibold">Available Professionals</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <?php
                                        $stmt = $pdo->query("SELECT ubi.user_id, uej.profile_id, ubi.user_full_name, uej.profession_title, uej.job_average_rating, uej.task_count 
                                                             FROM user_event_jobs uej
                                                             JOIN user_basic_info ubi ON uej.user_id = ubi.user_id
                                                             WHERE uej.job_status = 'Valid' AND ubi.user_validity = 'Verified'
                                                             LIMIT 5");
                                        while ($prof = $stmt->fetch()) {
                                            echo "<div class='glass p-3 rounded-xl flex justify-between items-center'>";
                                            echo "<div><span class='font-medium'>" . htmlspecialchars($prof['user_full_name']) . "</span><br><span class='text-sm'>" . htmlspecialchars($prof['profession_title']) . "</span> ⭐ " . number_format($prof['job_average_rating'], 1) . " (".$prof['task_count']." events)</div>";
                                            echo "<button onclick=\"fillServiceRow('" . addslashes($prof['user_id']) . "', '" . addslashes($prof['profile_id']) . "', '" . addslashes($prof['user_full_name']) . "', '" . addslashes($prof['profession_title']) . "')\" class='bg-green-500 text-white px-3 py-1 rounded-xl text-sm'>Hire</button>";
                                            echo "</div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                                <!-- Total budget -->
                                <div class="mt-4 text-right font-semibold">Total Budget: TZS <span id="totalBudget">0.00</span></div>
                            </div>
                        </div>

                        <!-- Step 4: Event Rentals (all asset categories) -->
                        <div class="step" data-step="4">
                            <h3 class="font-semibold text-lg">Event Rentals</h3>
                            <p class="text-sm text-gray-600">Rent additional assets for your event.</p>
                            <!-- Filter section (simplified) -->
                            <div class="flex flex-wrap gap-4 my-4">
                                <input type="text" placeholder="Search owner" class="rounded-xl px-4 py-2 glass">
                                <select class="rounded-xl px-4 py-2 glass"><option>All categories</option></select>
                                <select class="rounded-xl px-4 py-2 glass"><option>All regions</option></select>
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-2xl">Filter</button>
                            </div>
                            <!-- List of assets (simplified) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php
                                $stmt = $pdo->query("SELECT uea.*, ubi.user_full_name FROM user_event_asset uea
                                                     JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
                                                     WHERE uea.asset_status = 'Available' AND ubi.user_validity = 'Verified'
                                                     LIMIT 6");
                                while ($asset = $stmt->fetch()) {
                                    echo "<div class='glass p-4 rounded-2xl'>";
                                    echo "<p><strong>" . htmlspecialchars($asset['asset_name']) . "</strong> (" . htmlspecialchars($asset['asset_category']) . ")</p>";
                                    echo "<p>Owner: " . htmlspecialchars($asset['user_full_name']) . "</p>";
                                    echo "<p>Quality: " . htmlspecialchars($asset['asset_quality']) . "</p>";
                                    echo "<p>Price/unit: TZS " . number_format($asset['asset_price'], 2) . "</p>";
                                    echo "<p>Available quantity: " . htmlspecialchars($asset['asset_quantity']) . "</p>";
                                    echo "<div class='flex gap-2 mt-2'>";
                                    echo "<button onclick=\"openPlaceOrderModal('" . addslashes($asset['asset_id']) . "', " . $asset['asset_price'] . ", " . $asset['asset_quantity'] . ", '" . $processingEvent['event_id'] . "')\" class='bg-indigo-600 text-white px-4 py-1 rounded-2xl text-sm'>Place Order</button>";
                                    echo "<button onclick=\"openNegotiateModal('" . addslashes($asset['asset_id']) . "', " . $asset['asset_price'] . ", " . $asset['asset_quantity'] . ", '" . $processingEvent['event_id'] . "')\" class='bg-yellow-500 text-white px-4 py-1 rounded-2xl text-sm'>Negotiate</button>";
                                    echo "</div></div>";
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Step 5: Guests Invitations -->
                        <div class="step" data-step="5">
                            <h3 class="font-semibold text-lg">Guests Invitations</h3>
                            <p class="text-sm text-gray-600">Invite people to your event.</p>
                            <!-- Guest box -->
                            <div class="glass p-4 rounded-2xl my-4">
                                <input type="text" placeholder="Search users..." class="w-full rounded-xl px-4 py-2 glass" id="guestSearch">
                                <div id="guestList" class="mt-2 space-y-2">
                                    <?php
                                    $stmt = $pdo->query("SELECT user_id, user_full_name, user_profile_picture, user_type FROM user_basic_info WHERE user_validity != 'Banned' LIMIT 10");
                                    while ($guest = $stmt->fetch()) {
                                        echo "<div class='flex justify-between items-center glass p-2 rounded-xl'>";
                                        echo "<div><img src='".htmlspecialchars(getProfilePictureUrl($guest['user_profile_picture'] ?? ''))."' class='w-8 h-8 rounded-full inline mr-2'>".htmlspecialchars($guest['user_full_name'])." (".$guest['user_type'].")</div>";
                                        echo "<button onclick=\"document.querySelector('input[name=guest_name]').value='".addslashes($guest['user_full_name'])."'; document.querySelector('input[name=guest_user_id]').value='".addslashes($guest['user_id'])."'\" class='bg-indigo-600 text-white px-3 py-1 rounded-xl text-sm'>Invite</button>";
                                        echo "</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- Repeatable form -->
                            <form method="POST" action="?process=<?= $processingEvent['event_id'] ?>">
                                <input type="hidden" name="event_id" value="<?= $processingEvent['event_id'] ?>">
                                <input type="hidden" name="guest_user_id" id="guest_user_id">
                                <div class="flex flex-wrap gap-4 items-end">
                                    <div>
                                        <label class="block text-sm">Name of guest</label>
                                        <input type="text" name="guest_name" class="rounded-xl px-4 py-2 glass" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm">Position</label>
                                        <select name="position" class="rounded-xl px-4 py-2 glass">
                                            <option>Normal Guest</option>
                                            <option>Guest of Honour</option>
                                            <option>Special Guest</option>
                                            <option>Friend</option>
                                            <option>Family</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm">Participation Category</label>
                                        <select name="invitation_category" class="rounded-xl px-4 py-2 glass">
                                            <option value="Non-paying">Non-paying</option>
                                            <option value="Paying">Paying</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_guest" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl">Add</button>
                                </div>
                            </form>
                            <!-- Final submission -->
                            <form method="POST" action="?process=<?= $processingEvent['event_id'] ?>" class="mt-6 text-right">
                                <input type="hidden" name="event_id" value="<?= $processingEvent['event_id'] ?>">
                                <button type="submit" name="announce_event" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-2xl font-semibold">Announce Event</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- TAB 1: Announced Events -->
    <div id="panel1" class="tab-panel hidden">
        <?php if (empty($announcedEvents)): ?>
            <div class="glass rounded-3xl p-12 text-center">
                <p class="text-gray-500 text-lg">No announced events yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($announcedEvents as $ae): ?>
                    <div class="glass rounded-3xl overflow-hidden">
                        <!-- Event Title Card (clickable to toggle accordion) -->
                        <div class="flex justify-between items-center p-6 cursor-pointer accordion-toggle" data-target="accordion-<?= $ae['event_id'] ?>">
                            <div>
                                <h3 class="font-bold text-xl"><?= htmlspecialchars($ae['event_title']) ?></h3>
                                <p class="text-sm text-gray-600"><?= date('d M Y', strtotime($ae['event_date'])) ?> at <?= date('H:i', strtotime($ae['event_time'])) ?> • <?= $ae['event_activeness'] ?></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <?php if ($ae['event_activeness'] != 'Announced'): ?>
                                    <a href="control-event.php?id=<?= $ae['event_id'] ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl text-sm">Control Event</a>
                                <?php endif; ?>
                                <i class="fa fa-chevron-down"></i>
                            </div>
                        </div>
                        <!-- Accordion content -->
                        <div id="accordion-<?= $ae['event_id'] ?>" class="accordion-content p-6 border-t border-white/10">
                            <!-- 1. Event Basic Information -->
                            <div class="glass rounded-2xl p-4 mb-4">
                                <h4 class="font-semibold">Event Basic Information</h4>
                                <form method="POST" action="?update=<?= $ae['event_id'] ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <div><label class="block text-sm">Event Title</label><input type="text" name="event_title" value="<?= htmlspecialchars($ae['event_title']) ?>" class="w-full rounded-xl px-4 py-2 glass"></div>
                                        <div><label class="block text-sm">Extra Details</label><textarea name="extra_detail" class="w-full rounded-xl px-4 py-2 glass"><?= htmlspecialchars($ae['event_extra_detail']) ?></textarea></div>
                                        <div><label class="block text-sm">Event Date</label><input type="date" name="event_date" value="<?= $ae['event_date'] ?>" class="w-full rounded-xl px-4 py-2 glass"></div>
                                        <div><label class="block text-sm">Event Time</label><input type="time" name="event_time" value="<?= $ae['event_time'] ?>" class="w-full rounded-xl px-4 py-2 glass"></div>
                                        <div><label class="block text-sm">Duration (days)</label><select name="duration" class="w-full rounded-xl px-4 py-2 glass"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></div>
                                        <div><label class="block text-sm">Number of Tickets</label><input type="number" name="tickets" value="<?= $ae['event_tickets'] ?>" class="w-full rounded-xl px-4 py-2 glass"></div>
                                    </div>
                                    <button type="submit" name="update_basic" class="bg-indigo-600 text-white px-6 py-2 rounded-2xl mt-4">Save Changes</button>
                                </form>
                            </div>

                            <!-- 2. Event Fundraise Information -->
                            <div class="glass rounded-2xl p-4 mb-4">
                                <h4 class="font-semibold">Fundraise Information</h4>
                                <table class="w-full text-sm">
                                    <thead><tr><th>Title</th><th>Created</th><th>Planned</th><th>Collected</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE event_id = ? AND fundraise_status = 'Active' AND fundraise_duration != 'Post-event'");
                                        $stmt->execute([$ae['event_id']]);
                                        while ($fund = $stmt->fetch()) {
                                            echo "<tr><td>" . htmlspecialchars($fund['fundraise_title']) . "</td>
                                                      <td>" . $fund['creation_date'] . "</td>
                                                      <td>" . number_format($fund['required_amount'], 2) . "</td>
                                                      <td>" . number_format($fund['collected_amount'], 2) . "</td>
                                                      <td><button onclick=\"alert('Stop receiving funds')\" class='text-red-600'>Stop</button> | <button onclick=\"alert('Delete fundraise')\" class='text-red-600'>Delete</button></td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <button onclick="openFundraiseModal(<?= $ae['event_id'] ?>)" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mt-2">Add New Fundraise</button>
                            </div>

                            <!-- 3. Event Services Hires -->
                            <div class="glass rounded-2xl p-4 mb-4">
                                <h4 class="font-semibold">Hired Services</h4>
                                <table class="w-full text-sm">
                                    <thead><tr><th>User</th><th>Rating</th><th>Hired as</th><th>Amount</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT esh.*, uej.job_average_rating, uej.profession_title, ubi.user_full_name 
                                                               FROM event_service_hiring esh
                                                               JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
                                                               JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
                                                               WHERE esh.event_id = ? AND esh.service_status = 'Accepted'");
                                        $stmt->execute([$ae['event_id']]);
                                        while ($ser = $stmt->fetch()) {
                                            echo "<tr><td>" . htmlspecialchars($ser['user_full_name']) . "</td>
                                                      <td>" . number_format($ser['job_average_rating'], 1) . "</td>
                                                      <td>" . htmlspecialchars($ser['profession_title']) . "</td>
                                                      <td>TZS " . number_format($ser['hire_amount'], 2) . "</td>
                                                      <td><button onclick=\"alert('Hire')\" class='text-green-600'>Hire</button> | <button onclick=\"alert('Fire')\" class='text-red-600'>Fire</button></td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <button onclick="openHireModal(<?= $ae['event_id'] ?>)" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mt-2">Hire More</button>
                            </div>

                            <!-- 4. Event Rental Information -->
                            <div class="glass rounded-2xl p-4 mb-4">
                                <h4 class="font-semibold">Asset Rentals</h4>
                                <table class="w-full text-sm">
                                    <thead><tr><th>Owner</th><th>Asset</th><th>Qty</th><th>Total Price</th><th>Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT er.*, uea.asset_name, uea.asset_quality, ubi.user_full_name 
                                                               FROM event_asset_rentals er
                                                               JOIN user_event_asset uea ON er.asset_id = uea.asset_id
                                                               JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
                                                               WHERE er.event_id = ? AND er.lending_status = 'Approved'");
                                        $stmt->execute([$ae['event_id']]);
                                        while ($rent = $stmt->fetch()) {
                                            echo "<tr><td>" . htmlspecialchars($rent['user_full_name']) . "</td>
                                                      <td>" . htmlspecialchars($rent['asset_name']) . " (" . htmlspecialchars($rent['asset_quality']) . ")</td>
                                                      <td>" . $rent['rented_quantity'] . "</td>
                                                      <td>TZS " . number_format($rent['total_renting_price'], 2) . "</td>
                                                      <td>" . $rent['renting_status'] . "</td>
                                                      <td><button onclick=\"alert('Choose asset')\" class='text-green-600'>Choose</button> | <button onclick=\"alert('Ignore')\" class='text-red-600'>Ignore</button></td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <button onclick="openRentAssetModal(<?= $ae['event_id'] ?>)" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mt-2">Rent New Asset</button>
                            </div>

                            <!-- 5. Event Attendance Information -->
                            <div class="glass rounded-2xl p-4 mb-4">
                                <h4 class="font-semibold">Attendance</h4>
                                <table class="w-full text-sm">
                                    <thead><tr><th>User</th><th>Invited as</th><th>Badge</th><th>Accepted on</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT ei.*, ubi.user_full_name 
                                                               FROM event_invitees ei
                                                               JOIN user_basic_info ubi ON ei.user_id = ubi.user_id
                                                               WHERE ei.event_id = ? AND ei.attendance_status = 'Confirmed'");
                                        $stmt->execute([$ae['event_id']]);
                                        while ($att = $stmt->fetch()) {
                                            echo "<tr><td>" . htmlspecialchars($att['user_full_name']) . "</td>
                                                      <td>" . htmlspecialchars($att['invitation_position']) . "</td>
                                                      <td>" . htmlspecialchars($att['invitation_badge']) . "</td>
                                                      <td>" . ($att['attendance_date'] ? $att['attendance_date'] : 'N/A') . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <button onclick="alert('Invite more people modal')" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl mt-2">Invite More</button>
                            </div>

                            <!-- 6. Event Schedule Planning -->
                            <div class="glass rounded-2xl p-4">
                                <h4 class="font-semibold">Schedule</h4>
                                <table class="w-full text-sm">
                                    <thead><tr><th>From</th><th>To</th><th>Title</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM event_schedule_timetable WHERE event_id = ? AND schedule_status = 'On schedule'");
                                        $stmt->execute([$ae['event_id']]);
                                        while ($sch = $stmt->fetch()) {
                                            echo "<tr><td>" . $sch['schedule_start_time'] . "</td>
                                                      <td>" . $sch['schedule_end_time'] . "</td>
                                                      <td>" . htmlspecialchars($sch['schedule_title']) . "</td>
                                                      <td><button onclick=\"alert('Delete')\" class='text-red-600'>Delete</button></td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <form method="POST" action="?schedule=<?= $ae['event_id'] ?>" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <input type="time" name="start_time" class="rounded-xl px-4 py-2 glass" placeholder="Start">
                                    <input type="time" name="end_time" class="rounded-xl px-4 py-2 glass" placeholder="End">
                                    <input type="text" name="schedule_title" class="rounded-xl px-4 py-2 glass" placeholder="Title">
                                    <button type="submit" name="add_schedule" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl">Add to Schedule</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Spacing to prevent footer overlapping content -->
    <div class="h-24"></div>
</div>

<!-- STICKY FOOTER - Tab Navigation -->
<footer class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-md glass rounded-3xl border border-white/30 shadow-xl overflow-hidden">
    <div class="flex justify-center items-center">
        <button onclick="switchTab(0)" id="tab0" 
                class="tab flex-1 px-4 py-4 text-center font-semibold text-gray-600 border-b-4 border-transparent transition hover:text-indigo-700 hover:bg-white/10 text-xs md:text-sm tab-active">
            <i class="fa fa-plus-circle mr-1 md:mr-2"></i> <span class="hidden sm:inline">Created Events</span><span class="sm:hidden">Created</span>
        </button>
        <button onclick="switchTab(1)" id="tab1" 
                class="tab flex-1 px-4 py-4 text-center font-semibold text-gray-600 border-b-4 border-transparent transition hover:text-indigo-700 hover:bg-white/10 text-xs md:text-sm">
            <i class="fa fa-bullhorn mr-1 md:mr-2"></i> <span class="hidden sm:inline">Announced Events</span><span class="sm:hidden">Announced</span>
        </button>
    </div>
</footer>

<script>
    // Tab switching
    function switchTab(n) {
        document.querySelectorAll('.tab').forEach((tab, i) => {
            tab.classList.toggle('tab-active', i === n);
        });
        document.querySelectorAll('.tab-panel').forEach((panel, i) => {
            panel.classList.toggle('hidden', i !== n);
        });
    }

    // Accordion toggling
    document.querySelectorAll('.accordion-toggle').forEach(el => {
        el.addEventListener('click', function(e) {
            // Prevent if clicking on a button inside
            if (e.target.closest('a, button')) return;
            const targetId = this.dataset.target;
            const content = document.getElementById(targetId);
            content.classList.toggle('open');
            // Change icon
            const icon = this.querySelector('.fa-chevron-down');
            if (icon) icon.classList.toggle('rotate-180');
        });
    });

    // Multi-step navigation (for Created tab)
    let currentStep = 2; // steps start at 2
    const totalSteps = 5;

    function changeStep(delta) {
        const steps = document.querySelectorAll('.step');
        const newStep = currentStep + delta;
        if (newStep < 2 || newStep > totalSteps) return;
        steps.forEach(step => step.classList.remove('active'));
        document.querySelector(`.step[data-step="${newStep}"]`).classList.add('active');
        currentStep = newStep;
        document.getElementById('stepIndicator').textContent = `Step ${currentStep-1} of ${totalSteps-1}`;
        document.getElementById('stepBack').style.visibility = currentStep === 2 ? 'hidden' : 'visible';
        document.getElementById('stepNext').style.visibility = currentStep === totalSteps ? 'hidden' : 'visible';
    }

    // Initialize step navigation visibility
    document.addEventListener('DOMContentLoaded', function() {
        changeStep(0); // set initial state
    });

    // Live search for guests (simple)
    document.getElementById('guestSearch')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#guestList > div').forEach(el => {
            const name = el.textContent.toLowerCase();
            el.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });
</script>

<!-- Fundraise Modal (Single instance - place this before </body>) -->
<div id="fundraiseModal" class="modal-overlay">
    <div class="glass max-w-2xl w-full mx-4 rounded-3xl p-6 shadow-2xl overflow-y-auto max-h-[90vh]">
        <h3 class="text-2xl font-bold text-indigo-700 mb-6 flex items-center gap-2">
            <i class="fa fa-hand-holding-usd"></i> Create New Fundraise
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="add_fundraise" value="1">
            <input type="hidden" name="event_id" id="modalEventId" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Title of the fundraise</label>
                <input type="text" name="fundraise_title" required placeholder="e.g. Wedding Contribution" 
                       class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">
            </div>

            <!-- Goal Toggle, Duration, Fixed Amounts, etc. (keep your existing form fields) -->
            <!-- 2. Goal Toggle -->
            <div class="flex items-center justify-between mb-4 bg-white/30 p-4 rounded-2xl border border-white/40">
                <span class="text-sm font-semibold text-gray-700">Is there a maximum total amount (goal) required for this fundraise?</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="has_goal" id="hasGoalToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
            
            <!-- 3. Goal Amount -->
            <div id="goalAmountWrapper" class="mb-4 opacity-50 pointer-events-none transition-all duration-300">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Enter the maximum amount required</label>
                <input type="number" name="required_amount" id="goalAmountInput" step="0.01" min="0" placeholder="0.00" disabled class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
            </div>
            
            <!-- 4. Duration Dropdown -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">When will the participants be required to contribute/donate for this event?</label>
                <select name="fundraise_duration" class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                    <option value="Before the event">Before the event</option>
                    <option value="During the event">During the event</option>
                </select>
            </div>
            
            <!-- 5. Fixed Amount Toggle -->
            <div class="flex items-center justify-between mb-4 bg-white/30 p-4 rounded-2xl border border-white/40">
                <span class="text-sm font-semibold text-gray-700">Are there fixed amounts that the participants should contribute/donate?</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="has_fixed_amounts" id="hasFixedToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
            
            <!-- Repeatable elements section -->
            <div id="fixedAmountsWrapper" class="mb-6 opacity-50 pointer-events-none transition-all duration-300">
                <h4 class="text-sm font-bold text-indigo-700 mb-3 border-b pb-2">Participation Types</h4>
                <div id="tagsContainer" class="space-y-3">
                    <!-- Default Tag Row -->
                    <div class="tag-row grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-gray-200/50 pb-3 mb-3 relative">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Name</label>
                            <input type="text" name="tag_name[]" placeholder="e.g. VIP" disabled class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Details</label>
                            <input type="text" name="tag_details[]" placeholder="e.g. Front row seating" disabled class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Amount paid</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="tag_amount[]" step="0.01" min="0" placeholder="0.00" disabled class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                                <button type="button" class="remove-tag-btn text-red-500 hover:text-red-700 transition opacity-0 cursor-default" onclick="removeTagRow(this)" disabled><i class="fa fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" id="addTagRowBtn" disabled class="flex items-center gap-2 text-indigo-600 font-semibold hover:text-indigo-800 transition mt-3 text-sm">
                    <i class="fa fa-plus-circle"></i> Add participation types
                </button>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeFundraiseModal()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-2xl font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-2xl font-semibold transition">
                    Create Fundraise
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fundraise Modal Functions
function openFundraiseModal(eventId) {
    document.getElementById('modalEventId').value = eventId;
    document.getElementById('fundraiseModal').classList.add('active');
}

function closeFundraiseModal() {
    document.getElementById('fundraiseModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('fundraiseModal').addEventListener('click', function(e) {
    if (e.target === this) closeFundraiseModal();
});

// Goal Toggle Logic
document.getElementById('hasGoalToggle').addEventListener('change', function() {
    const wrapper = document.getElementById('goalAmountWrapper');
    const input = document.getElementById('goalAmountInput');
    if (this.checked) {
        wrapper.classList.remove('opacity-50', 'pointer-events-none');
        input.disabled = false;
        input.required = true;
    } else {
        wrapper.classList.add('opacity-50', 'pointer-events-none');
        input.disabled = true;
        input.required = false;
        input.value = '';
    }
});

// Fixed Amount Toggle Logic
document.getElementById('hasFixedToggle').addEventListener('change', function() {
    const wrapper = document.getElementById('fixedAmountsWrapper');
    const addBtn = document.getElementById('addTagRowBtn');
    const inputs = document.querySelectorAll('.tag-input');
    const deleteBtns = document.querySelectorAll('.remove-tag-btn');
    
    if (this.checked) {
        wrapper.classList.remove('opacity-50', 'pointer-events-none');
        addBtn.disabled = false;
        inputs.forEach(input => {
            input.disabled = false;
            input.required = true;
        });
        deleteBtns.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-0', 'cursor-default');
        });
    } else {
        wrapper.classList.add('opacity-50', 'pointer-events-none');
        addBtn.disabled = true;
        inputs.forEach(input => {
            input.disabled = true;
            input.required = false;
            input.value = '';
        });
        deleteBtns.forEach(btn => {
            btn.disabled = true;
            btn.classList.add('opacity-0', 'cursor-default');
        });
    }
});

// Add Tag Row
document.getElementById('addTagRowBtn').addEventListener('click', function() {
    const newRow = document.createElement('div');
    newRow.className = 'tag-row grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-gray-200/50 pb-3 mb-3 relative';
    newRow.innerHTML = `
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Name</label>
            <input type="text" name="tag_name[]" placeholder="e.g. VIP" required class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Details</label>
            <input type="text" name="tag_details[]" placeholder="e.g. Front row seating" required class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Amount paid</label>
            <div class="flex items-center gap-2">
                <input type="number" name="tag_amount[]" step="0.01" min="0" placeholder="0.00" required class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                <button type="button" class="remove-tag-btn text-red-500 hover:text-red-700 transition" onclick="removeTagRow(this)"><i class="fa fa-trash"></i></button>
            </div>
        </div>
    `;
    document.getElementById('tagsContainer').appendChild(newRow);
    updateDeleteButtons();
});

function removeTagRow(button) {
    const row = button.closest('.tag-row');
    const rows = document.querySelectorAll('.tag-row');
    if (rows.length > 1) {
        row.remove();
        updateDeleteButtons();
    }
}

function updateDeleteButtons() {
    const rows = document.querySelectorAll('.tag-row');
    const show = rows.length > 1;
    rows.forEach(row => {
        const btn = row.querySelector('.remove-tag-btn');
        if (btn) {
            if (show) {
                btn.classList.remove('opacity-0', 'cursor-default');
                btn.disabled = false;
            } else {
                btn.classList.add('opacity-0', 'cursor-default');
                btn.disabled = true;
            }
        }
    });
}

// Open Hire Modal
function openHireModal(eventId) {
    document.getElementById('hireEventId').value = eventId;
    document.getElementById('hireModal').classList.add('active');
    loadProfessions();
    resetHireForm();
}

function closeHireModal() {
    document.getElementById('hireModal').classList.remove('active');
}

// Load professions from API
function loadProfessions() {
    fetch('../api/get_professions.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('professionSelect');
                select.innerHTML = '<option value="">Select profession...</option>';
                data.professions.forEach(prof => {
                    select.innerHTML += `<option value="${prof}">${prof}</option>`;
                });
            }
        })
        .catch(err => console.error('Error loading professions:', err));
}

// Load professionals based on selected profession
function loadProfessionals() {
    const profession = document.getElementById('professionSelect').value;
    const list = document.getElementById('professionalsList');
    
    if (!profession) {
        list.innerHTML = '<p class="text-gray-500 text-center py-8">Select a profession to see available professionals</p>';
        return;
    }
    
    list.innerHTML = '<p class="text-gray-500 text-center py-8">Loading...</p>';
    
    fetch(`../api/get_professionals.php?profession=${encodeURIComponent(profession)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.professionals.length > 0) {
                let html = '';
                data.professionals.forEach(p => {
                    const profilePic = p.user_profile_picture || '../assets/images/default.png';
                    html += `
                        <div class="glass p-4 rounded-2xl professional-card" data-name="${p.user_full_name.toLowerCase()}">
                            <div class="flex items-start gap-3">
                                <img src="${profilePic}" class="w-12 h-12 rounded-full object-cover" onerror="this.src='../assets/images/default.png'">
                                <div class="flex-1">
                                    <div class="flex justify-between">
                                        <p class="font-semibold">${p.user_full_name}</p>
                                        <span class="text-yellow-500 text-sm">★ ${p.job_average_rating}</span>
                                    </div>
                                    <p class="text-sm text-gray-600">${p.profession_title}</p>
                                    <p class="text-xs text-gray-500">${p.location}</p>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-xs text-gray-500">${p.task_count} events served</span>
                                        <button onclick="selectProfessional('${p.user_id}', '${p.profile_id}', '${p.user_full_name}')" 
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-xl text-sm">
                                            Hire
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<p class="text-gray-500 text-center py-8">No professionals available for this profession</p>';
            }
        })
        .catch(err => {
            list.innerHTML = '<p class="text-red-500 text-center py-8">Error loading professionals</p>';
            console.error('Error loading professionals:', err);
        });
}

// Filter professionals by search
function filterProfessionals() {
    const term = document.getElementById('professionalSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.professional-card');
    cards.forEach(card => {
        card.style.display = card.dataset.name.includes(term) ? 'block' : 'none';
    });
}

// Select professional from list
function selectProfessional(userId, profileId, userName) {
    document.getElementById('userId').value = userId;
    document.getElementById('profileId').value = profileId;
    document.getElementById('userName').value = userName;
    validateHireForm();
}

// Validate hire form and enable/disable Add button
function validateHireForm() {
    // Validation is handled in submitHiredServices()
}

// Build a hire entry from the current modal form fields (if complete)





function resetHireForm() {
    document.getElementById('professionSelect').value = '';
    document.getElementById('hireAmount').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userId').value = '';
    document.getElementById('profileId').value = '';
    document.getElementById('professionalsList').innerHTML = '<p class="text-gray-500 text-center py-8">Select a profession to see available professionals</p>';
}

// Submit hired services
function submitHiredServices() {
    const profession = document.getElementById('professionSelect').value;
    const amount = parseFloat(document.getElementById('hireAmount').value);
    const userId = document.getElementById('userId').value;
    const profileId = document.getElementById('profileId').value;
    
    if (!profession || !amount || amount <= 0 || !userId || !profileId) {
        alert('Please select a profession, choose a professional from the list, and enter a valid hire amount.');
        return;
    }
    
    const eventId = document.getElementById('hireEventId').value;
    const services = [{
        profession_title: profession,
        hire_amount: amount,
        user_id: userId,
        profile_id: profileId
    }];
    
    fetch('manage-event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'submit_hired_services': '1',
            'event_id': eventId,
            'is_ajax': '1',
            'services': JSON.stringify(services)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Service provider hired successfully!');
            closeHireModal();
            location.reload();
        } else {
            alert(data.error || 'Failed to hire service provider.');
        }
    })
    .catch(err => {
        alert('Error submitting hired services: ' + err);
        console.error(err);
    });
}

// Add event listeners for form validation


// ====================== STEP 2 REPEATABLE SERVICE FORM ======================

// Add new service row
function addServiceRow() {
    const container = document.getElementById('service-rows');
    const newRow = document.createElement('div');
    newRow.className = 'service-row flex flex-wrap gap-4 items-end mb-4';
    newRow.innerHTML = `
        <div>
            <label class="block text-sm">Hire (server)</label>
            <select name="profession_title[]" class="rounded-xl px-4 py-2 glass profession-select">
                <option value="">Select profession</option>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT profession_title FROM user_event_jobs WHERE job_status = 'Valid'");
                while ($row = $stmt->fetch()) {
                    echo "<option value='" . htmlspecialchars($row['profession_title']) . "'>" . htmlspecialchars($row['profession_title']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div>
            <label class="block text-sm">For (TZS)</label>
            <input type="number" name="hire_amount[]" class="rounded-xl px-4 py-2 glass" placeholder="Amount">
        </div>
        <div>
            <label class="block text-sm">Name (auto-filled)</label>
            <input type="text" name="user_name[]" class="rounded-xl px-4 py-2 glass user-name-input" readonly placeholder="Select from list">
        </div>
        <input type="hidden" name="user_id[]" class="user-id-input">
        <input type="hidden" name="profile_id[]" class="profile-id-input">
        <button type="button" onclick="removeServiceRow(this)" class="bg-red-500 text-white px-4 py-2 rounded-2xl">Remove</button>
    `;
    container.appendChild(newRow);
}

// Remove service row
function removeServiceRow(button) {
    const row = button.closest('.service-row');
    const container = document.getElementById('service-rows');
    if (container.children.length > 1) {
        row.remove();
    } else {
        alert('You must have at least one service row.');
    }
}

// Fill service row when clicking "Hire" on a professional
function fillServiceRow(userId, profileId, userName, professionTitle) {
    // Find the first empty row or the last row
    const rows = document.querySelectorAll('.service-row');
    let targetRow = null;
    
    for (let row of rows) {
        const userIdInput = row.querySelector('.user-id-input');
        const profileIdInput = row.querySelector('.profile-id-input');
        if (!userIdInput.value || !profileIdInput.value) {
            targetRow = row;
            break;
        }
    }
    
    // If no empty row, add a new one
    if (!targetRow) {
        addServiceRow();
        targetRow = document.querySelectorAll('.service-row')[document.querySelectorAll('.service-row').length - 1];
    }
    
    // Fill the row
    targetRow.querySelector('.profession-select').value = professionTitle;
    targetRow.querySelector('.user-name-input').value = userName;
    targetRow.querySelector('.user-id-input').value = userId;
    targetRow.querySelector('.profile-id-input').value = profileId;
}

// ====================== RENT ASSET MODAL ======================
let rentAssetEventId = '';

function openRentAssetModal(eventId) {
    rentAssetEventId = eventId;
    document.getElementById('rentAssetModal').classList.add('active');
    loadAssetNames();
    loadRegions();
    resetRentAssetFilters();
}

function closeRentAssetModal() {
    document.getElementById('rentAssetModal').classList.remove('active');
}

function resetRentAssetFilters() {
    document.getElementById('assetSearch').value = '';
    document.getElementById('assetNameFilter').value = '';
    document.getElementById('regionFilter').value = '';
    document.getElementById('districtFilter').value = '';
    document.getElementById('districtFilter').innerHTML = '<option value="">All districts</option>';
    document.getElementById('assetsList').innerHTML = '<p class="text-gray-500 text-center py-8">Click "Apply Filters" to see available assets</p>';
}

function loadAssetNames() {
    fetch('../api/get_asset_names.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('assetNameFilter');
                select.innerHTML = '<option value="">All properties</option>';
                data.asset_names.forEach(name => {
                    select.innerHTML += `<option value="${name}">${name}</option>`;
                });
            }
        })
        .catch(err => console.error('Error loading asset names:', err));
}

function loadRegions() {
    fetch('../api/get_regions.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('regionFilter');
                select.innerHTML = '<option value="">All regions</option>';
                data.regions.forEach(region => {
                    select.innerHTML += `<option value="${region}">${region}</option>`;
                });
            }
        })
        .catch(err => console.error('Error loading regions:', err));
}

function loadDistricts() {
    const region = document.getElementById('regionFilter').value;
    const districtSelect = document.getElementById('districtFilter');
    
    if (!region) {
        districtSelect.innerHTML = '<option value="">All districts</option>';
        return;
    }
    
    fetch(`../api/get_districts.php?region=${encodeURIComponent(region)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                districtSelect.innerHTML = '<option value="">All districts</option>';
                data.districts.forEach(district => {
                    districtSelect.innerHTML += `<option value="${district}">${district}</option>`;
                });
            }
        })
        .catch(err => console.error('Error loading districts:', err));
}

function filterAssets() {
    const assetName = document.getElementById('assetNameFilter').value;
    const region = document.getElementById('regionFilter').value;
    const district = document.getElementById('districtFilter').value;
    const search = document.getElementById('assetSearch').value;
    
    const params = new URLSearchParams();
    if (assetName) params.append('asset_name', assetName);
    if (region) params.append('region', region);
    if (district) params.append('district', district);
    if (search) params.append('search', search);
    
    const list = document.getElementById('assetsList');
    list.innerHTML = '<p class="text-gray-500 text-center py-8">Loading...</p>';
    
    fetch(`../api/get_available_assets.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.assets.length > 0) {
                let html = '';
                data.assets.forEach(asset => {
                    html += `
                        <div class="glass p-4 rounded-2xl">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold">${asset.user_full_name}</p>
                                    <p class="text-sm text-gray-600">${asset.asset_name} (${asset.asset_quality})</p>
                                    <p class="text-sm text-gray-500">TZS ${parseFloat(asset.asset_price).toFixed(2)} per unit</p>
                                    <p class="text-xs text-gray-500">${asset.asset_region}, ${asset.asset_district}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openPlaceOrderModal('${asset.asset_id}', '${asset.asset_price}', '${asset.asset_quantity}')" 
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-xl text-sm">
                                        Place Order
                                    </button>
                                    <button onclick="openNegotiateModal('${asset.asset_id}', '${asset.asset_price}', '${asset.asset_quantity}')" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-xl text-sm">
                                        Negotiate
                                    </button>
                                </div>
                            </div>
                        </div>`;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<p class="text-gray-500 text-center py-8">No assets found matching your filters</p>';
            }
        })
        .catch(err => {
            list.innerHTML = '<p class="text-red-500 text-center py-8">Error loading assets</p>';
            console.error('Error loading assets:', err);
        });
}

// ====================== PLACE ORDER MINI MODAL ======================
function openPlaceOrderModal(assetId, price, availableQty, eventId) {
    document.getElementById('placeOrderAssetId').value = assetId;
    document.getElementById('placeOrderEventId').value = eventId || rentAssetEventId;
    document.getElementById('placeOrderPrice').value = parseFloat(price).toFixed(2);
    document.getElementById('placeOrderAvailableQty').value = availableQty;
    document.getElementById('placeOrderRentQty').value = '';
    document.getElementById('placeOrderTotal').value = '';
    document.getElementById('placeOrderModal').classList.add('active');
}

function closePlaceOrderModal() {
    document.getElementById('placeOrderModal').classList.remove('active');
}

function calculatePlaceOrderTotal() {
    const price = parseFloat(document.getElementById('placeOrderPrice').value);
    const qty = parseInt(document.getElementById('placeOrderRentQty').value);
    const availableQty = parseInt(document.getElementById('placeOrderAvailableQty').value);
    
    if (qty > availableQty) {
        alert('Please insert a quantity less or equal to the available quantity');
        document.getElementById('placeOrderRentQty').value = '';
        document.getElementById('placeOrderTotal').value = '';
        return;
    }
    
    if (qty > 0 && price > 0) {
        document.getElementById('placeOrderTotal').value = (price * qty).toFixed(2);
    } else {
        document.getElementById('placeOrderTotal').value = '';
    }
}

function submitPlaceOrder() {
    const assetId = document.getElementById('placeOrderAssetId').value;
    const eventId = document.getElementById('placeOrderEventId').value;
    const price = parseFloat(document.getElementById('placeOrderPrice').value);
    const qty = parseInt(document.getElementById('placeOrderRentQty').value);
    const total = parseFloat(document.getElementById('placeOrderTotal').value);
    
    if (!qty || qty <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    if (!total) {
        alert('Please calculate total amount');
        return;
    }
    
    fetch('manage-event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'submit_rental_request': '1',
            'event_id': eventId,
            'asset_id': assetId,
            'renting_price': price,
            'rented_quantity': qty,
            'total_renting_price': total,
            'renting_status': 'Requested'
        })
    })
    .then(response => response.text())
    .then(data => {
        alert('Rental request submitted successfully!');
        closePlaceOrderModal();
        closeRentAssetModal();
        location.reload();
    })
    .catch(err => {
        alert('Error submitting rental request: ' + err);
        console.error(err);
    });
}

// ====================== NEGOTIATE MINI MODAL ======================
function openNegotiateModal(assetId, price, availableQty, eventId) {
    document.getElementById('negotiateAssetId').value = assetId;
    document.getElementById('negotiateEventId').value = eventId || rentAssetEventId;
    document.getElementById('negotiateOriginalPrice').value = parseFloat(price).toFixed(2);
    document.getElementById('negotiateAvailableQty').value = availableQty;
    document.getElementById('negotiateNewPrice').value = '';
    document.getElementById('negotiateRentQty').value = '';
    document.getElementById('negotiateTotal').value = '';
    document.getElementById('negotiateModal').classList.add('active');
}

function closeNegotiateModal() {
    document.getElementById('negotiateModal').classList.remove('active');
}

function calculateNegotiateTotal() {
    const newPrice = parseFloat(document.getElementById('negotiateNewPrice').value);
    const qty = parseInt(document.getElementById('negotiateRentQty').value);
    const availableQty = parseInt(document.getElementById('negotiateAvailableQty').value);
    
    if (qty > availableQty) {
        alert('Please insert a quantity less or equal to the available quantity');
        document.getElementById('negotiateRentQty').value = '';
        document.getElementById('negotiateTotal').value = '';
        return;
    }
    
    if (qty > 0 && newPrice > 0) {
        document.getElementById('negotiateTotal').value = (newPrice * qty).toFixed(2);
    } else {
        document.getElementById('negotiateTotal').value = '';
    }
}

function submitNegotiate() {
    const assetId = document.getElementById('negotiateAssetId').value;
    const eventId = document.getElementById('negotiateEventId').value;
    const newPrice = parseFloat(document.getElementById('negotiateNewPrice').value);
    const qty = parseInt(document.getElementById('negotiateRentQty').value);
    const total = parseFloat(document.getElementById('negotiateTotal').value);
    
    if (!newPrice || newPrice <= 0) {
        alert('Please enter a valid suggested price');
        return;
    }
    
    if (!qty || qty <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    if (!total) {
        alert('Please calculate total amount');
        return;
    }
    
    fetch('manage-event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'submit_rental_request': '1',
            'event_id': eventId,
            'asset_id': assetId,
            'renting_price': newPrice,
            'rented_quantity': qty,
            'total_renting_price': total,
            'renting_status': 'Pleaded'
        })
    })
    .then(response => response.text())
    .then(data => {
        alert('Negotiation plea submitted successfully!');
        closeNegotiateModal();
        closeRentAssetModal();
        location.reload();
    })
    .catch(err => {
        alert('Error submitting negotiation: ' + err);
        console.error(err);
    });
}
</script>

<!-- Hire More Modal -->
<div id="hireModal" class="modal-overlay">
    <div class="glass max-w-5xl w-full mx-4 rounded-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-indigo-700">Hire Service Providers</h3>
            <button onclick="closeHireModal()" class="text-3xl text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Repeatable Form -->
            <div>
                <h4 class="font-semibold text-lg mb-4">Add Service Provider</h4>
                
                <form id="hireForm" class="space-y-4">
                    <input type="hidden" name="event_id" id="hireEventId" value="">
                    
                    <!-- Hire (server) - Dropdown -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Hire (server)</label>
                        <select name="profession_title" id="professionSelect" 
                                class="w-full glass rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                onchange="loadProfessionals(); validateHireForm();">
                            <option value="">Select profession...</option>
                        </select>
                    </div>
                    
                    <!-- For (TZS) - Number input -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">For (TZS)</label>
                        <input type="number" name="hire_amount" id="hireAmount" 
                               placeholder="Enter amount" min="0" step="0.01"
                               class="w-full glass rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                    </div>
                    
                    <!-- Name - Autofill -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Name</label>
                        <input type="text" name="user_name" id="userName"
                               placeholder="Auto-filled from professionals box" readonly
                               class="w-full glass rounded-2xl px-4 py-3 bg-gray-100 focus:outline-none">
                        <input type="hidden" name="user_id" id="userId" value="">
                        <input type="hidden" name="profile_id" id="profileId" value="">
                    </div>

                    <button type="button" id="addHireBtn" onclick="addHireEntry()" disabled
                            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white py-3 rounded-2xl font-semibold transition">
                        Add to List
                    </button>
                </form>

                <!-- Submit Button -->
                <button type="button" onclick="submitHiredServices()" 
                        class="w-full mt-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white py-3 rounded-2xl font-semibold transition">
                    Submit Hired Services
                </button>
            </div>
            
            <!-- Right: Professionals Box -->
            <div>
                <h4 class="font-semibold text-lg mb-4">Available Professionals</h4>
                
                <!-- Search bar -->
                <input type="text" id="professionalSearch" placeholder="Search professionals..." 
                       class="w-full glass rounded-2xl px-4 py-3 mb-4 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                       onkeyup="filterProfessionals()">
                
                <!-- Professionals list -->
                <div id="professionalsList" class="space-y-3 max-h-[50vh] overflow-y-auto">
                    <p class="text-gray-500 text-center py-8">Select a profession to see available professionals</p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-right">
            <button onclick="closeHireModal()" 
                    class="px-6 py-3 bg-gray-300 hover:bg-gray-400 rounded-2xl">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Rent New Asset Modal -->
<div id="rentAssetModal" class="modal-overlay">
    <div class="glass max-w-5xl w-full mx-4 rounded-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-indigo-700">Rent New Asset</h3>
            <button onclick="closeRentAssetModal()" class="text-3xl text-gray-400 hover:text-gray-600">×</button>
        </div>

        <!-- Filter Section -->
        <div class="glass p-4 rounded-2xl mb-6">
            <h4 class="font-semibold mb-4">Filters</h4>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search Owner</label>
                    <input type="text" id="assetSearch" placeholder="Search by owner name..." 
                           class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Property Name</label>
                    <select id="assetNameFilter" class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                        <option value="">All properties</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Region</label>
                    <select id="regionFilter" class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                            onchange="loadDistricts()">
                        <option value="">All regions</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">District</label>
                    <select id="districtFilter" class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                        <option value="">All districts</option>
                    </select>
                </div>
            </div>
            <button onclick="filterAssets()" class="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-2xl">
                Apply Filters
            </button>
        </div>

        <!-- List Section -->
        <div id="assetsList" class="space-y-4 max-h-[50vh] overflow-y-auto">
            <p class="text-gray-500 text-center py-8">Click "Apply Filters" to see available assets</p>
        </div>

        <div class="mt-6 text-right">
            <button onclick="closeRentAssetModal()" class="px-6 py-3 bg-gray-300 hover:bg-gray-400 rounded-2xl">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Place Order Mini Modal -->
<div id="placeOrderModal" class="modal-overlay">
    <div class="glass max-w-md w-full mx-4 rounded-3xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-indigo-700">Place Order</h3>
            <button onclick="closePlaceOrderModal()" class="text-2xl text-gray-400 hover:text-gray-600">×</button>
        </div>

        <form id="placeOrderForm" class="space-y-4">
            <input type="hidden" id="placeOrderAssetId" value="">
            <input type="hidden" id="placeOrderEventId" value="">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Renting price per unit</label>
                <input type="text" id="placeOrderPrice" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Available quantity</label>
                <input type="text" id="placeOrderAvailableQty" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Renting quantity</label>
                <input type="number" id="placeOrderRentQty" min="1" 
                       class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                       oninput="calculatePlaceOrderTotal()">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Total amount</label>
                <input type="text" id="placeOrderTotal" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <button type="button" onclick="submitPlaceOrder()" 
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-2xl font-semibold">
                Request
            </button>
        </form>
    </div>
</div>

<!-- Negotiate Mini Modal -->
<div id="negotiateModal" class="modal-overlay">
    <div class="glass max-w-md w-full mx-4 rounded-3xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-indigo-700">Negotiate Price</h3>
            <button onclick="closeNegotiateModal()" class="text-2xl text-gray-400 hover:text-gray-600">×</button>
        </div>

        <form id="negotiateForm" class="space-y-4">
            <input type="hidden" id="negotiateAssetId" value="">
            <input type="hidden" id="negotiateEventId" value="">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Original renting price per unit</label>
                <input type="text" id="negotiateOriginalPrice" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Available quantity</label>
                <input type="text" id="negotiateAvailableQty" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Suggest new renting price per unit</label>
                <input type="number" id="negotiateNewPrice" min="0" step="0.01"
                       class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                       oninput="calculateNegotiateTotal()">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Renting quantity</label>
                <input type="number" id="negotiateRentQty" min="1" 
                       class="w-full glass rounded-2xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                       oninput="calculateNegotiateTotal()">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Total amount</label>
                <input type="text" id="negotiateTotal" readonly 
                       class="w-full glass rounded-2xl px-4 py-2 bg-gray-100 focus:outline-none">
            </div>
            
            <button type="button" onclick="submitNegotiate()" 
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-2xl font-semibold">
                Plead
            </button>
        </form>
    </div>
</div>

</body>
</html>