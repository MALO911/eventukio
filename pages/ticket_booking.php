<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Helper functions for generating IDs (if not already defined)
if (!function_exists('generateAttendeeId')) {
    function generateAttendeeId() {
        return 'EEMS-ATTE-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!function_exists('generateInviteeId')) {
    function generateInviteeId() {
        return 'EEMS-INVI-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!function_exists('generateFundingRecordId')) {
    function generateFundingRecordId() {
        return 'EEMS-FUND-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

// ---- Get parameters ----
$event_id = (int)($_GET['id'] ?? $_GET['event_id'] ?? 0);
if ($event_id <= 0) {
    redirect('pages/events.php');
}

$user_id = getCurrentUserId();
$user = getCurrentUser(); // assume returns associative array

// ---- Check user validity - ONLY Verified users can access ----
if ($user['user_validity'] !== 'Verified') {
    errorMsg("Only verified users can access this page.");
    redirect('pages/events.php');
}

// ---- Page tracking - Insert into user_usage_track ----
$page_exited = $_SERVER['HTTP_REFERER'] ?? 'Direct Access';
$page_headed = 'Ticket Booking page';

try {
    $track_stmt = $pdo->prepare("
        INSERT INTO user_usage_track (user_id, page_exited, page_headed, track_date, track_time)
        VALUES (?, ?, ?, CURDATE(), CURTIME())
    ");
    $track_stmt->execute([$user_id, $page_exited, $page_headed]);
} catch (Exception $e) {
    // Log error but don't block the page
    error_log("Page tracking error: " . $e->getMessage());
}

// ---- Get user language for interface ----
$user_language = $user['user_language'] ?? 'English';

// ---- Determine which tab the user came from ----
$source_tab = $_GET['tab'] ?? 'all_events'; // all_events, your_area, your_invitations

// ---- Fetch event data ----
$stmt = $pdo->prepare("
    SELECT e.*, 
           u.user_full_name AS host_name, 
           u.user_profile_picture AS host_picture,
           a.asset_region, a.asset_district, a.asset_street, a.asset_location_specifics
    FROM event_basic_info e
    JOIN user_basic_info u ON e.host_id = u.user_id
    LEFT JOIN user_event_asset a ON e.venue_id = a.asset_id
    WHERE e.event_id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    errorMsg("Event not found.");
    redirect('pages/events.php');
}

// Check if event is still bookable (must be 'Announced')
if ($event['event_activeness'] !== 'Announced') {
    errorMsg("This event is no longer open for booking.");
    redirect('pages/events.php');
}

// Tickets left
$tickets_left = $event['event_tickets'] - ($event['event_tickets_sold'] ?? 0);

// ---- Check if user already has an invitation for this event ----
$inv_stmt = $pdo->prepare("
    SELECT invitee_id, attendance_status, invitation_category, invitation_badge, invitation_position
    FROM event_invitees
    WHERE event_id = ? AND user_id = ?
");
$inv_stmt->execute([$event_id, $user_id]);
$invitation = $inv_stmt->fetch(PDO::FETCH_ASSOC);

// Determine booking flow based on tab and conditions
$simple_booking = false;
$pre_selected_position = null;
$pre_selected_category = null;

if ($source_tab === 'your_invitations') {
    // Your Invitations tab logic
    if ($event['participation_fee'] === 'Absent') {
        $simple_booking = true;
    } elseif ($invitation_category === 'Non-paying') {
        $simple_booking = true;
    } elseif ($invitation_category === 'Paying' || $event['participation_fee'] === 'Present') {
        // Full booking flow
        if (empty($event['booking_fundraise_id'])) {
            errorMsg("This event requires payment but no fundraise is configured.");
            redirect('pages/events.php');
        }
        $tags_stmt = $pdo->prepare("
            SELECT fundraise_tag_id, tag_name, tag_details, required_amount, participant_count
            FROM event_fundraise_tags
            WHERE fundraise_id = ? AND tag_validity = 'Valid'
            ORDER BY required_amount ASC
        ");
        $tags_stmt->execute([$event['booking_fundraise_id']]);
        $booking_tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($booking_tags)) {
            errorMsg("No participation options available for this event.");
            redirect('pages/events.php');
        }
    }
} else {
    // All Events and Your Area tabs (same logic)
    if ($event['participation_fee'] === 'Absent') {
        $simple_booking = true;
    } else {
        // Full booking flow
        if (empty($event['booking_fundraise_id'])) {
            errorMsg("This event requires payment but no fundraise is configured.");
            redirect('pages/events.php');
        }
        $tags_stmt = $pdo->prepare("
            SELECT fundraise_tag_id, tag_name, tag_details, required_amount, participant_count
            FROM event_fundraise_tags
            WHERE fundraise_id = ? AND tag_validity = 'Valid'
            ORDER BY required_amount ASC
        ");
        $tags_stmt->execute([$event['booking_fundraise_id']]);
        $booking_tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($booking_tags)) {
            errorMsg("No participation options available for this event.");
            redirect('pages/events.php');
        }
    }
}

// ---- Fetch other fundraises (Pre-event, Active, not the booking one) ----
$other_fundraises = [];
if (!empty($event['booking_fundraise_id'])) {
    $other_stmt = $pdo->prepare("
        SELECT fundraise_id, fundraise_title, fundraise_type
        FROM event_fundraise_info
        WHERE event_id = ? 
          AND fundraise_status = 'Active'
          AND fundraise_duration = 'Pre-event'
          AND fundraise_id != ?
        ORDER BY creation_date DESC
    ");
    $other_stmt->execute([$event_id, $event['booking_fundraise_id']]);
    $other_fundraises = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // If no booking fundraise, show all active pre-event fundraises
    $other_stmt = $pdo->prepare("
        SELECT fundraise_id, fundraise_title, fundraise_type
        FROM event_fundraise_info
        WHERE event_id = ? 
          AND fundraise_status = 'Active'
          AND fundraise_duration = 'Pre-event'
        ORDER BY creation_date DESC
    ");
    $other_stmt->execute([$event_id]);
    $other_fundraises = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Fetch event media (images or video) ----
$media_type = $event['event_ad_media'];
$media_images = [];
$media_video = null;
if ($media_type === 'Image') {
    $img_stmt = $pdo->prepare("
        SELECT image_a, image_b, image_c, image_d
        FROM event_ad_images
        WHERE event_id = ?
        ORDER BY images_upload_date DESC, images_upload_time DESC
        LIMIT 1
    ");
    $img_stmt->execute([$event_id]);
    $media_images = $img_stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($media_type === 'Video') {
    $vid_stmt = $pdo->prepare("
        SELECT video_uploaded
        FROM event_ad_video
        WHERE event_id = ?
        ORDER BY video_upload_date DESC, video_upload_time DESC
        LIMIT 1
    ");
    $vid_stmt->execute([$event_id]);
    $media_video = $vid_stmt->fetchColumn();
}

// ---- For Your Invitations tab, check invitation_category ----
$invitation_category = null;
if ($source_tab === 'your_invitations' && $invitation) {
    $invitation_category = $invitation['invitation_category'];
}

// ---- Fetch fundraise title for booking fundraise ----
$fundraise_title = null;
if (!empty($event['booking_fundraise_id'])) {
    $fund_stmt = $pdo->prepare("SELECT fundraise_title FROM event_fundraise_info WHERE fundraise_id = ?");
    $fund_stmt->execute([$event['booking_fundraise_id']]);
    $fundraise_title = $fund_stmt->fetchColumn();
}

// ---- Handle POST submission ----
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple booking (no payment)
    if ($simple_booking) {
        // If invitation exists, just update attendance_status to 'Confirmed'
        // else create new invitation and attendee record
        try {
            $pdo->beginTransaction();

            // Lock event row and check ticket availability
            $lock_stmt = $pdo->prepare("SELECT event_tickets, event_tickets_sold FROM event_basic_info WHERE event_id = ? FOR UPDATE");
            $lock_stmt->execute([$event_id]);
            $event_lock = $lock_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event_lock) {
                throw new Exception("Event not found.");
            }
            $tickets_left = (int)$event_lock['event_tickets'] - (int)($event_lock['event_tickets_sold'] ?? 0);
            if ($tickets_left < 1) {
                throw new Exception("This event is fully booked.");
            }

            // Check if attendee record exists, else create
            $att_check = $pdo->prepare("SELECT attendee_id FROM event_attendees WHERE event_id = ? AND participant_id = ?");
            $att_check->execute([$event_id, $user_id]);
            $is_already_attendee = $att_check->fetch();

            if ($is_already_attendee) {
                throw new Exception("You are already registered/confirmed for this event.");
            }

            if ($invitation) {
                // Update existing invitation (Your Invitations tab case)
                $upd = $pdo->prepare("
                    UPDATE event_invitees
                    SET attendance_status = 'Confirmed',
                        attendance_date = CURDATE(),
                        attendance_time = CURTIME()
                    WHERE invitee_id = ?
                ");
                $upd->execute([$invitation['invitee_id']]);
                $invitee_id = $invitation['invitee_id'];
            } else {
                // Create new invitation (All Events/Your Area tabs)
                $invitee_id = generateInviteeId();
                $ins_inv = $pdo->prepare("
                    INSERT INTO event_invitees 
                        (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category, attendance_date, attendance_time)
                    VALUES (?, ?, ?, 'Confirmed', 'Normal', 'Normal Guest', 'Non-paying', CURDATE(), CURTIME())
                ");
                $ins_inv->execute([$invitee_id, $event_id, $user_id]);
            }

            // Create attendee record
            $attendee_id = generateAttendeeId();
            $ins_att = $pdo->prepare("
                INSERT INTO event_attendees (attendee_id, invitee_id, event_id, participant_id, participation_badge, participation_status)
                VALUES (?, ?, ?, ?, 'Normal', 'Active')
            ");
            $ins_att->execute([$attendee_id, $invitee_id, $event_id, $user_id]);

            // Increase tickets sold
            $pdo->prepare("UPDATE event_basic_info SET event_tickets_sold = event_tickets_sold + 1 WHERE event_id = ?")
                ->execute([$event_id]);

            $pdo->commit();
            $success = true;
            $_SESSION['success_msg'] = "Ticket booked successfully!";
            // Redirect back to events page or to booking page with success
            redirect('pages/events.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Booking failed: " . $e->getMessage();
        }
    } else {
        // Full booking with payment (two-step)
        // Step 1: collect position and category
        $position = clean($_POST['position'] ?? '');
        $category = clean($_POST['category'] ?? '');
        $selected_users = isset($_POST['selected_users']) ? (array)$_POST['selected_users'] : [];

        // Validate
        if (empty($position) || empty($category)) {
            $errors[] = "Please select participation position and category.";
        } else {
            // Find tag for this combination (should exist)
            $tag = null;
            foreach ($booking_tags as $t) {
                if ($t['tag_details'] === $position && $t['tag_name'] === $category) {
                    $tag = $t;
                    break;
                }
            }
            if (!$tag) {
                $errors[] = "Invalid participation selection.";
            } else {
                // Check number of users selected must be participant_count - 1
                $needed = $tag['participant_count'] - 1;
                if ($needed < 0) {
                    $needed = 0;
                }
                if (count($selected_users) !== $needed) {
                    $errors[] = "Please select exactly $needed other attendee(s).";
                } else {
                    // Check if current user is already an attendee
                    $att_check = $pdo->prepare("SELECT attendee_id FROM event_attendees WHERE event_id = ? AND participant_id = ?");
                    $att_check->execute([$event_id, $user_id]);
                    if ($att_check->fetch()) {
                        $errors[] = "You are already registered/confirmed for this event.";
                    }

                    // Check if selected other users are already invited or attending
                    if (empty($errors) && !empty($selected_users)) {
                        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
                        $chk_selected = $pdo->prepare("
                            SELECT user_id FROM event_invitees 
                            WHERE event_id = ? AND user_id IN ($placeholders)
                        ");
                        $chk_selected->execute(array_merge([$event_id], $selected_users));
                        $already_invited = $chk_selected->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($already_invited)) {
                            $errors[] = "Some selected attendees are already invited or attending this event.";
                        }
                    }

                    if (empty($errors)) {
                        // Check wallet balance
                        $wallet_stmt = $pdo->prepare("SELECT account_balance FROM user_wallet_info WHERE user_id = ?");
                        $wallet_stmt->execute([$user_id]);
                        $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
                        $balance = $wallet ? (float)$wallet['account_balance'] : 0;
                        $amount = (float)$tag['required_amount'];

                        if ($balance < $amount) {
                            // Show insufficient funds modal - don't add to errors, handle via JavaScript
                            $insufficient_funds = true;
                            $required_amount = $amount;
                            $current_balance = $balance;
                        } else {
                            // Proceed with transaction
                            try {
                                $pdo->beginTransaction();

                                // Lock event row and check ticket availability
                                $lock_stmt = $pdo->prepare("SELECT event_tickets, event_tickets_sold FROM event_basic_info WHERE event_id = ? FOR UPDATE");
                                $lock_stmt->execute([$event_id]);
                                $event_lock = $lock_stmt->fetch(PDO::FETCH_ASSOC);
                                if (!$event_lock) {
                                    throw new Exception("Event not found.");
                                }
                                $tickets_left = (int)$event_lock['event_tickets'] - (int)($event_lock['event_tickets_sold'] ?? 0);
                                $tickets_needed = 1 + count($selected_users);
                                if ($tickets_left < $tickets_needed) {
                                    throw new Exception("Not enough tickets left. Only $tickets_left ticket(s) remaining.");
                                }

                                // 1. Deduct from wallet
                                $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance - ? WHERE user_id = ?")
                                    ->execute([$amount, $user_id]);

                                // 2. Record transaction in user_wallet_transactions
                                $transaction_details = "Contribution for {$fundraise_title} in the event {$event['event_title']}";
                                $pdo->prepare("
                                    INSERT INTO user_wallet_transactions 
                                        (user_id, account_id, transaction_details, transaction_amount, transaction_type)
                                    SELECT ?, account_id, ?, ?, 'Outgoing'
                                    FROM user_wallet_info WHERE user_id = ?
                                ")->execute([
                                    $user_id,
                                    $transaction_details,
                                    $amount,
                                    $user_id
                                ]);

                                // 3. Insert into event_funding_records
                                $fundraise_id = $event['booking_fundraise_id'];
                                $funding_record_id = generateFundingRecordId();
                                $pdo->prepare("
                                    INSERT INTO event_funding_records 
                                        (funding_record_id, fundraise_id, event_id, fundraise_tag_id, payer_id, funded_amount)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ")->execute([
                                    $funding_record_id,
                                    $fundraise_id,
                                    $event_id,
                                    $tag['fundraise_tag_id'],
                                    $user_id,
                                    $amount
                                ]);

                                // 4. Update collected_amount in event_fundraise_info
                                $pdo->prepare("
                                    UPDATE event_fundraise_info 
                                    SET collected_amount = collected_amount + ? 
                                    WHERE fundraise_id = ?
                                ")->execute([$amount, $fundraise_id]);

                                // 5. Create invitation for current user (payer)
                                $invitee_id = generateInviteeId();
                                $ins_inv = $pdo->prepare("
                                    INSERT INTO event_invitees 
                                        (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category, attendance_date, attendance_time)
                                    VALUES (?, ?, ?, 'Confirmed', 'Normal', ?, 'Paying', CURDATE(), CURTIME())
                                ");
                                $ins_inv->execute([$invitee_id, $event_id, $user_id, $position]);

                                // 6. Create attendee record for current user
                                $attendee_id = generateAttendeeId();
                                $pdo->prepare("
                                    INSERT INTO event_attendees (attendee_id, invitee_id, event_id, participant_id, participation_badge, participation_status)
                                    VALUES (?, ?, ?, ?, ?, 'Active')
                                ")->execute([$attendee_id, $invitee_id, $event_id, $user_id, $category]);

                                // 7. For each selected user, create invitation and attendee records
                                foreach ($selected_users as $uid) {
                                    if ($uid == $user_id) continue; // skip current user
                                    $chk = $pdo->prepare("SELECT invitee_id FROM event_invitees WHERE event_id = ? AND user_id = ?");
                                    $chk->execute([$event_id, $uid]);
                                    if (!$chk->fetch()) {
                                        $inv_id2 = generateInviteeId();
                                        $ins_inv2 = $pdo->prepare("
                                            INSERT INTO event_invitees 
                                                (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category)
                                            VALUES (?, ?, ?, 'Pending', 'Normal', ?, 'Paying')
                                        ");
                                        $ins_inv2->execute([$inv_id2, $event_id, $uid, $position]);

                                        $att_id2 = generateAttendeeId();
                                        $pdo->prepare("
                                            INSERT INTO event_attendees (attendee_id, invitee_id, event_id, participant_id, participation_badge, participation_status)
                                            VALUES (?, ?, ?, ?, ?, 'Active')
                                        ")->execute([$att_id2, $inv_id2, $event_id, $uid, $category]);
                                    }
                                }

                                // 8. Increase tickets sold by the total tickets purchased in the booking
                                $pdo->prepare("UPDATE event_basic_info SET event_tickets_sold = event_tickets_sold + ? WHERE event_id = ?")
                                    ->execute([$tickets_needed, $event_id]);

                                // 9. Send invitation emails to all selected users including current user
                                $all_attendees = array_merge([$user_id], $selected_users);
                                foreach ($all_attendees as $attendee_id) {
                                    $user_stmt = $pdo->prepare("SELECT user_full_name, user_email FROM user_basic_info WHERE user_id = ?");
                                    $user_stmt->execute([$attendee_id]);
                                    $attendee_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($attendee_user) {
                                        $to = $attendee_user['user_email'];
                                        $subject = "Invitation to {$event['event_title']}";
                                        $message = "
                                            <html>
                                            <head>
                                            <title>Event Invitation</title>
                                            </head>
                                            <body>
                                            <h2>You are invited to: {$event['event_title']}</h2>
                                            <p><strong>Category:</strong> {$event['event_category']}</p>
                                            <p><strong>Date:</strong> " . date('d M Y', strtotime($event['event_date'])) . "</p>
                                            <p><strong>Time:</strong> " . date('h:i A', strtotime($event['event_time'])) . "</p>
                                            <p><strong>Location:</strong> {$event['asset_location_specifics']}, {$event['asset_district']}, {$event['asset_region']}</p>
                                            <p><strong>Host:</strong> {$event['host_name']}</p>
                                            <p><strong>Your Participation Badge:</strong> $category</p>
                                            <p><strong>Invitee Name:</strong> {$attendee_user['user_full_name']}</p>
                                            <p>We look forward to seeing you there!</p>
                                            </body>
                                            </html>
                                        ";
                                        $headers = "MIME-Version: 1.0\r\n";
                                        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                                        $headers .= "From: noreply@eventukio.com\r\n";
                                        @mail($to, $subject, $message, $headers);
                                    }
                                }

                                $pdo->commit();
                                $success = true;
                                $_SESSION['success_msg'] = "Booking successful! Invitations have been sent.";
                                redirect('pages/events.php');
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                $errors[] = "Transaction failed: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="<?= $user_language === 'Swahili' ? 'sw' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Booking - <?= htmlspecialchars($event['event_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { 
            background: rgba(255, 255, 255, 0.45); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
        }
        .glass-dark {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .step { display: none; }
        .step.active { display: block; }
        .media-slideshow img { width: 100%; height: auto; max-height: 300px; object-fit: cover; }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .fundraise-card {
            transition: all 0.3s ease;
        }
        .fundraise-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.7);
        }
        @media (max-width: 768px) {
            .glass { padding: 1rem; }
        }
        
        /* Light Theme Color Overrides to align with other pages */
        .text-white:not(button):not(.bg-indigo-600):not(.bg-red-500):not(.bg-gray-500):not(.bg-gradient-to-r) {
            color: #4338ca !important; /* text-indigo-700 */
        }
        .text-white:not(button):not(.bg-indigo-600):not(.bg-red-500):not(.bg-gray-500):not(.bg-gradient-to-r):hover {
            color: #4f46e5 !important;
        }
        .text-purple-200 {
            color: #4b5563 !important; /* text-gray-600 */
        }
        .bg-white\/5 {
            background-color: rgba(79, 70, 229, 0.05) !important;
        }
        .border-white\/10, .border-white\/20 {
            border-color: rgba(79, 70, 229, 0.15) !important;
        }
        input::placeholder {
            color: #9ca3af !important;
        }
        select, input[type="text"] {
            color: #1f2937 !important;
        }
        select option {
            color: #1f2937 !important;
            background-color: #ffffff !important;
        }
        
        /* Premium Alert Box styling */
        .bg-red-500\/30 {
            background-color: #fee2e2 !important; /* bg-red-100 */
            border-color: #fca5a5 !important; /* border-red-300 */
            color: #991b1b !important; /* text-red-800 */
        }
        .bg-red-500\/30 .text-white {
            color: #991b1b !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<!-- Header Section (same as Events Page) -->
<header class="glass sticky top-0 z-50 shadow-lg">
    <div class="max-w-6xl mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="events.php" class="text-white hover:text-purple-200 transition">
                    <i class="fa fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-white">
                    <?= $user_language === 'Swahili' ? 'Udhamishaji wa Tiketi' : 'Ticket Booking' ?>
                </h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="profile.php" class="w-8 h-8 rounded-full overflow-hidden border-2 border-white">
                    <img src="<?= htmlspecialchars(getProfilePictureUrl($user['user_profile_picture'] ?? '')) ?>" alt="Profile" class="w-full h-full object-cover">
                </a>
            </div>
        </div>
    </div>
</header>

<div class="max-w-6xl mx-auto px-4 py-6">
    <?php if (!empty($errors)): ?>
        <div class="glass bg-red-500/30 border border-red-400 text-white px-4 py-3 rounded-2xl mb-6">
            <?php foreach ($errors as $e): ?>
                <p class="mb-1"><i class="fa fa-exclamation-circle mr-2"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Insufficient Funds Modal -->
    <div id="insufficientFundsModal" class="modal-overlay">
        <div class="glass rounded-3xl p-6 max-w-md mx-4 text-center">
            <div class="text-5xl mb-4">💰</div>
            <h3 class="text-xl font-bold text-white mb-2">
                <?= $user_language === 'Swahili' ? 'Samahani!' : 'Sorry!' ?>
            </h3>
            <p class="text-white mb-6">
                <?= $user_language === 'Swahili' ? 'Hauna fedha za kutosha kuendelea na muamala huu!' : 'You have insufficient funds to proceed with this transaction!' ?>
            </p>
            <div class="flex gap-3 justify-center">
                <a href="manage_wallet.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-semibold transition">
                    <?= $user_language === 'Swahili' ? 'Tembelea Mkoba wangu' : 'Visit my wallet' ?>
                </a>
                <button onclick="closeInsufficientFundsModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-2xl font-semibold transition">
                    <?= $user_language === 'Swahili' ? 'Funga' : 'Close' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Event Details Section -->
    <div class="glass rounded-3xl p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-4">
            <i class="fa fa-info-circle mr-2"></i>
            <?= $user_language === 'Swahili' ? 'Maelezo ya Tukio' : 'Event Details' ?>
        </h3>
        <div class="space-y-4">
            <div class="flex items-start gap-4">
                <img src="<?= htmlspecialchars(getProfilePictureUrl($event['host_picture'] ?? '')) ?>" 
                     class="w-14 h-14 rounded-full object-cover border-2 border-white" alt="Host">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($event['event_title']) ?></h2>
                    <p class="text-purple-200 font-medium"><?= htmlspecialchars($event['event_category']) ?></p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="glass-dark rounded-2xl p-4">
                    <p class="text-purple-200 text-sm mb-1">
                        <i class="fa fa-calendar mr-2"></i>
                        <?= $user_language === 'Swahili' ? 'Tarehe' : 'Date' ?>
                    </p>
                    <p class="text-white font-semibold"><?= date('d M Y', strtotime($event['event_date'])) ?></p>
                </div>
                <div class="glass-dark rounded-2xl p-4">
                    <p class="text-purple-200 text-sm mb-1">
                        <i class="fa fa-clock mr-2"></i>
                        <?= $user_language === 'Swahili' ? 'Muda' : 'Time' ?>
                    </p>
                    <p class="text-white font-semibold"><?= date('h:i A', strtotime($event['event_time'])) ?></p>
                </div>
                <div class="glass-dark rounded-2xl p-4">
                    <p class="text-purple-200 text-sm mb-1">
                        <i class="fa fa-tag mr-2"></i>
                        <?= $user_language === 'Swahili' ? 'Aina ya Tukio' : 'Event Type' ?>
                    </p>
                    <p class="text-white font-semibold"><?= htmlspecialchars($event['event_type']) ?></p>
                </div>
                <div class="glass-dark rounded-2xl p-4">
                    <p class="text-purple-200 text-sm mb-1">
                        <i class="fa fa-ticket mr-2"></i>
                        <?= $user_language === 'Swahili' ? 'Tiketi zilizobaki' : 'Tickets Left' ?>
                    </p>
                    <p class="text-white font-semibold"><?= max(0, $tickets_left) ?></p>
                </div>
            </div>
            <div class="glass-dark rounded-2xl p-4">
                <p class="text-purple-200 text-sm mb-1">
                    <i class="fa fa-map-marker-alt mr-2"></i>
                    <?= $user_language === 'Swahili' ? 'Mahali' : 'Location' ?>
                </p>
                <p class="text-white font-semibold">
                    <?= htmlspecialchars($event['asset_location_specifics'] ?? $event['asset_street'] ?? '') ?>,
                    <?= htmlspecialchars($event['asset_district'] ?? '') ?>,
                    <?= htmlspecialchars($event['asset_region'] ?? '') ?>
                </p>
            </div>
            <div class="glass-dark rounded-2xl p-4">
                <p class="text-purple-200 text-sm mb-1">
                    <i class="fa fa-user mr-2"></i>
                    <?= $user_language === 'Swahili' ? 'Mwenyeji' : 'Event Host' ?>
                </p>
                <p class="text-white font-semibold"><?= htmlspecialchars($event['host_name']) ?></p>
            </div>
        </div>
    </div>

    <!-- Media Section -->
    <div class="glass rounded-3xl p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-4">
            <i class="fa fa-photo-video mr-2"></i>
            <?= $user_language === 'Swahili' ? 'Vyombo vya Habari' : 'Media' ?>
        </h3>
        <?php if ($media_type === 'Image' && $media_images): ?>
            <div class="media-slideshow">
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach (['image_a','image_b','image_c','image_d'] as $col): ?>
                        <?php if (!empty($media_images[$col])): ?>
                            <img src="<?= '../uploads/events/' . basename($media_images[$col]) ?>" alt="Event image" class="rounded-2xl w-full h-48 object-cover">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($media_type === 'Video' && $media_video): ?>
            <div class="rounded-2xl overflow-hidden">
                <video controls class="w-full">
                    <source src="<?= '../uploads/events/' . basename($media_video) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        <?php else: ?>
            <p class="text-purple-200">
                <?= $user_language === 'Swahili' ? 'Hakuna media ya tukio' : 'No event media available' ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Booking Section -->
    <div class="glass rounded-3xl p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-4">
            <i class="fa fa-ticket-alt mr-2"></i>
            <?= $user_language === 'Swahili' ? 'Udhamishaji' : 'Booking' ?>
        </h3>
        
        <?php if ($simple_booking): ?>
            <!-- Simple booking (no payment) -->
            <form method="POST" action="">
                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white py-4 rounded-2xl font-semibold text-lg transition transform hover:scale-105">
                    <i class="fa fa-check-circle mr-2"></i>
                    <?= $user_language === 'Swahili' ? 'Hifadhi Tiketi' : 'Book Ticket' ?>
                </button>
            </form>
        <?php else: ?>
            <!-- Full booking with payment -->
            <form method="POST" action="" id="bookingForm">
                <!-- Step 1: Select position and category -->
                <div id="step1" class="step active">
                    <h4 class="font-medium text-white mb-4">
                        <?= $user_language === 'Swahili' ? 'Chagua ushiriki wako' : 'Select your participation' ?>
                    </h4>
                    <div class="mb-4">
                        <label class="block text-purple-200 text-sm font-medium mb-2">
                            <?= $user_language === 'Swahili' ? 'Nafasi ya Ushiriki' : 'Participation Position' ?>
                        </label>
                        <select name="position" id="position" class="glass-dark w-full rounded-2xl px-4 py-3 text-white bg-transparent" required>
                            <option value="" class="text-gray-800">-- <?= $user_language === 'Swahili' ? 'Chagua' : 'Select' ?> --</option>
                            <?php foreach ($booking_tags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag['tag_details']) ?>" class="text-gray-800">
                                    <?= htmlspecialchars($tag['tag_details']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-purple-200 text-sm font-medium mb-2">
                            <?= $user_language === 'Swahili' ? 'Kundi la Ushiriki' : 'Participation Category' ?>
                        </label>
                        <select name="category" id="category" class="glass-dark w-full rounded-2xl px-4 py-3 text-white bg-transparent" required>
                            <option value="" class="text-gray-800">-- <?= $user_language === 'Swahili' ? 'Chagua' : 'Select' ?> --</option>
                            <?php foreach ($booking_tags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag['tag_name']) ?>" 
                                        data-count="<?= $tag['participant_count'] ?>" 
                                        data-amount="<?= $tag['required_amount'] ?>" class="text-gray-800">
                                    <?= htmlspecialchars($tag['tag_name']) ?> 
                                    (<?= $tag['participant_count'] ?> <?= $user_language === 'Swahili' ? 'watu' : 'people' ?>, TZS <?= number_format($tag['required_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="step1Info" class="glass-dark rounded-2xl p-4 mb-4">
                        <p id="peopleInfo" class="text-purple-200 text-sm"></p>
                        <p id="feeInfo" class="text-purple-200 text-sm"></p>
                    </div>
                    <button type="button" id="gotoStep2" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white py-3 rounded-2xl font-semibold transition transform hover:scale-105">
                        <?= $user_language === 'Swahili' ? 'Endelea' : 'Next' ?> <i class="fa fa-arrow-right ml-2"></i>
                    </button>
                </div>

                <!-- Step 2: Select other attendees -->
                <div id="step2" class="step">
                    <h4 class="font-medium text-white mb-4">
                        <?= $user_language === 'Swahili' ? 'Chagua washiriki wengine' : 'Select other attendees' ?> 
                        (<?= $user_language === 'Swahili' ? 'haswa' : 'exactly' ?> <span id="neededCount">0</span> <?= $user_language === 'Swahili' ? 'zaidi' : 'more' ?>)
                    </h4>
                    <div class="mb-4">
                        <input type="text" id="searchUsers" placeholder="<?= $user_language === 'Swahili' ? 'Tafuta kwa jina...' : 'Search by name...' ?>" class="glass-dark w-full rounded-2xl px-4 py-3 text-white placeholder-purple-200 bg-transparent">
                    </div>
                    <div id="userList" class="max-h-80 overflow-y-auto space-y-2">
                        <!-- Users will be loaded via AJAX -->
                    </div>
                    <div class="flex gap-3 mt-4">
                        <button type="button" id="backToStep1" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-2xl font-semibold transition">
                            <i class="fa fa-arrow-left mr-2"></i> <?= $user_language === 'Swahili' ? 'Rudi' : 'Back' ?>
                        </button>
                        <button type="submit" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-6 py-3 rounded-2xl font-semibold transition transform hover:scale-105">
                            <i class="fa fa-credit-card mr-2"></i> <?= $user_language === 'Swahili' ? 'Hifadhi na Lipa' : 'Book Now' ?>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Other Fundraises Section -->
    <?php if (!empty($other_fundraises)): ?>
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">
                <i class="fa fa-hand-holding-heart mr-2"></i>
                <?= $user_language === 'Swahili' ? 'Vyanzo vingine vya Misaada' : 'Other Fundraises' ?>
            </h3>
            <div class="space-y-3">
                <?php foreach ($other_fundraises as $of): ?>
                    <a href="fundraise.php?event_id=<?= $event_id ?>&fundraise_id=<?= $of['fundraise_id'] ?>"
                       class="fundraise-card block glass-dark p-4 rounded-2xl border border-white/20">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-white"><?= htmlspecialchars($of['fundraise_title']) ?></p>
                                <p class="text-sm text-purple-200">
                                    <i class="fa fa-tag mr-1"></i>
                                    <?= $user_language === 'Swahili' ? 'Aina' : 'Type' ?>: <?= htmlspecialchars($of['fundraise_type']) ?>
                                </p>
                            </div>
                            <i class="fa fa-arrow-right text-white"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Show insufficient funds modal if needed
    <?php if (isset($insufficient_funds) && $insufficient_funds): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('insufficientFundsModal').classList.add('active');
    });
    <?php endif; ?>

    // Close insufficient funds modal
    function closeInsufficientFundsModal() {
        document.getElementById('insufficientFundsModal').classList.remove('active');
    }

    // For full booking flow
    <?php if (!$simple_booking): ?>
    const tags = <?= json_encode($booking_tags) ?>;
    const positionSelect = document.getElementById('position');
    const categorySelect = document.getElementById('category');
    const peopleInfo = document.getElementById('peopleInfo');
    const feeInfo = document.getElementById('feeInfo');
    const gotoStep2 = document.getElementById('gotoStep2');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const backStep1 = document.getElementById('backToStep1');
    const neededCount = document.getElementById('neededCount');
    const userList = document.getElementById('userList');
    const searchUsers = document.getElementById('searchUsers');

    // Update info when category changes
    categorySelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            const count = parseInt(opt.dataset.count) || 0;
            const amount = parseFloat(opt.dataset.amount) || 0;
            peopleInfo.textContent = `👥 ${count} <?= $user_language === 'Swahili' ? 'watu wanahusika (wewe pamoja nao)' : 'people involved (including you)' ?>`;
            feeInfo.textContent = `💰 <?= $user_language === 'Swahili' ? 'Ada ya Ushiriki' : 'Participation fee' ?>: TZS ${amount.toFixed(2)}`;
        } else {
            peopleInfo.textContent = '';
            feeInfo.textContent = '';
        }
    });

    // Step 2: load users
    gotoStep2.addEventListener('click', function() {
        const pos = positionSelect.value;
        const cat = categorySelect.value;
        if (!pos || !cat) {
            alert('<?= $user_language === 'Swahili' ? 'Tafadhali chagua nafasi na kundi la ushiriki.' : 'Please select both position and category.' ?>');
            return;
        }
        // Find the tag to get participant_count
        const tag = tags.find(t => t.tag_details === pos && t.tag_name === cat);
        if (!tag) {
            alert('<?= $user_language === 'Swahili' ? 'Chaguo batili.' : 'Invalid selection.' ?>');
            return;
        }
        const count = parseInt(tag.participant_count) || 1;  // fallback to 1
        const needed = Math.max(0, count - 1);
        neededCount.textContent = needed;

        if (needed <= 0) {
            searchUsers.parentElement.style.display = 'none';
            userList.innerHTML = '<div class="text-center p-6 bg-white/5 rounded-2xl border border-white/10 text-purple-200"><i class="fa fa-info-circle text-2xl mb-2 block text-indigo-400"></i><?= $user_language === "Swahili" ? "Hakuna washiriki wa ziada wanaohitajika. Bonyeza Hifadhi na Lipa ili kuendelea." : "No additional attendees are required. Click Book Now to proceed." ?></div>';
        } else {
            searchUsers.parentElement.style.display = 'block';
            userList.innerHTML = '<p class="text-purple-200 p-2 text-center"><?= $user_language === "Swahili" ? "Inapakia watumiaji..." : "Loading users..." ?></p>';
            // Fetch users (exclude current user, only verified/registered)
            fetch('get_users.php?exclude=<?= $user_id ?>&status=Verified,Registered')
                .then(res => res.json())
                .then(data => {
                    userList.innerHTML = '';
                    if (data.length === 0) {
                        userList.innerHTML = '<p class="text-purple-200 p-2"><?= $user_language === "Swahili" ? "Hakuna watumiaji wapatikano" : "No users available" ?></p>';
                        return;
                    }
                    data.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-3 p-3 glass-dark rounded-xl border border-white/10';
                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.name = 'selected_users[]';
                        cb.value = user.user_id;
                        cb.className = 'w-5 h-5 accent-indigo-600';
                        cb.addEventListener('change', function() {
                            // Limit selection to 'needed'
                            const checked = document.querySelectorAll('input[name="selected_users[]"]:checked');
                            if (checked.length > needed) {
                                this.checked = false;
                                alert(`<?= $user_language === 'Swahili' ? 'Unaweza kuchagua kwa zaidi ya' : 'You can select at most' ?> ${needed} <?= $user_language === 'Swahili' ? 'washiriki wengine.' : 'other attendees.' ?>`);
                            }
                        });
                        const img = document.createElement('img');
                        img.src = user.user_profile_picture || '../assets/images/default.png';
                        img.className = 'w-12 h-12 rounded-full object-cover border-2 border-white';
                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'flex-1';
                        const name = document.createElement('p');
                        name.className = 'text-white font-medium';
                        name.textContent = user.user_full_name;
                        const type = document.createElement('p');
                        type.className = 'text-purple-200 text-sm';
                        type.textContent = `${user.user_type} • ${user.user_gender}`;
                        infoDiv.appendChild(name);
                        infoDiv.appendChild(type);
                        div.appendChild(cb);
                        div.appendChild(img);
                        div.appendChild(infoDiv);
                        userList.appendChild(div);
                    });
                })
                .catch(err => {
                    console.error(err);
                    userList.innerHTML = '<p class="text-red-300 p-2"><?= $user_language === "Swahili" ? "Kuna hitilafu kupakia watumiaji." : "Error loading users." ?></p>';
                });
        }

        step1.classList.remove('active');
        step2.classList.add('active');
    });

    backStep1.addEventListener('click', function() {
        step2.classList.remove('active');
        step1.classList.add('active');
    });

    // Filter users by search
    searchUsers.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const items = userList.querySelectorAll('.flex.items-center');
        items.forEach(item => {
            const name = item.querySelector('.text-white')?.textContent?.toLowerCase() || '';
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });
    <?php endif; ?>
</script>
</body>
</html>