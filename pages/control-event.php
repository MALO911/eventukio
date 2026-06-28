<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();
$event_id = (int)($_GET['id'] ?? 0);

if ($event_id <= 0) {
    errorMsg("Invalid Event");
    redirect('pages/manage-event.php');
}

// Verify host ownership
$stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE event_id = ? AND host_id = ?");
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch();

if (!$event) {
    errorMsg("You do not have permission to control this event.");
    redirect('pages/events.php');
}

// ---- Helper functions ----

function getEventFundraises($event_id, $pdo, $status = 'Complete') {
    $stmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE event_id = ? AND fundraise_status = ? AND fundraise_duration != 'Post-event'");
    $stmt->execute([$event_id, $status]);
    return $stmt->fetchAll();
}

function getBudgetFundraise($event_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE fundraise_id = (SELECT pocket_id FROM event_basic_info WHERE event_id = ?)");
    $stmt->execute([$event_id]);
    return $stmt->fetch();
}

function getServiceProviders($event_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT esh.*, uej.job_average_rating, uej.profession_title, uej.profession_category,
               ubi.user_full_name, ubi.user_id as provider_user_id, ei.attendance_status
        FROM event_service_hiring esh
        JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
        JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
        JOIN event_invitees ei ON esh.invitee_id = ei.invitee_id
        WHERE esh.event_id = ?
          AND ei.event_id = ?
          AND esh.hire_status = 'Hired'
          AND esh.service_status = 'Accepted'
          AND esh.presence_status = 'Active'
          AND ei.attendance_status = 'Confirmed'
          AND ei.invitation_badge = 'Server'
    ");
    $stmt->execute([$event_id, $event_id]);
    return $stmt->fetchAll();
}

function getAssetRentals($event_id, $pdo, $status = 'Received') {
    $stmt = $pdo->prepare("
        SELECT er.*, uea.asset_name, uea.asset_quality, uea.asset_category,
               ubi.user_full_name, ubi.user_id as owner_user_id
        FROM event_asset_rentals er
        JOIN user_event_asset uea ON er.asset_id = uea.asset_id
        JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
        WHERE er.event_id = ? AND er.lending_status = 'Approved' AND er.renting_status = ?
    ");
    $stmt->execute([$event_id, $status]);
    return $stmt->fetchAll();
}

function getBookedAssets($event_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT er.*, uea.asset_name, uea.asset_quality,
               ubi.user_full_name
        FROM event_asset_rentals er
        JOIN user_event_asset uea ON er.asset_id = uea.asset_id
        JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
        WHERE er.event_id = ? AND er.lending_status = 'Approved' AND er.renting_status = 'Booked'
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

function getAttendees($event_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT ea.*, ubi.user_full_name, ubi.user_profile_picture, ubi.user_phone_number,
               ei.invitation_badge, ei.invitation_position
        FROM event_attendees ea
        JOIN user_basic_info ubi ON ea.participant_id = ubi.user_id
        JOIN event_invitees ei ON ea.invitee_id = ei.invitee_id
        WHERE ea.event_id = ? AND ea.participation_status = 'Active'
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

function getServiceProviderList($event_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT esh.*, uej.job_average_rating, uej.profession_title, uej.profession_category,
               ubi.user_full_name, ubi.user_profile_picture, ubi.user_id as provider_user_id
        FROM event_service_hiring esh
        JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
        JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
        WHERE esh.event_id = ? 
          AND esh.service_status = 'Accepted'
          AND esh.presence_status = 'Active'
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

function getEventGallery($event_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT esm.*, ubi.user_full_name
        FROM event_shared_media esm
        JOIN user_basic_info ubi ON esm.uploader_id = ubi.user_id
        WHERE esm.event_id = ? AND esm.media_validity = 'Valid'
        ORDER BY esm.upload_datetime DESC
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

function getEventFunders($fundraise_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT efr.*, ubi.user_full_name
        FROM event_funding_records efr
        JOIN user_basic_info ubi ON efr.payer_id = ubi.user_id
        WHERE efr.fundraise_id = ? AND efr.fund_validity = 'Valid'
    ");
    $stmt->execute([$fundraise_id]);
    return $stmt->fetchAll();
}

// ---- Handle POST Actions ----

// Compile fundraise to budget
if (isset($_POST['compile_fundraise']) && isset($_POST['fundraise_id'])) {
    $fundraise_id = $_POST['fundraise_id'];
    $is_ajax = isset($_POST['ajax']);

    // Update fundraise status to Compiled
    $stmt = $pdo->prepare("UPDATE event_fundraise_info SET fundraise_status = 'Compiled' WHERE fundraise_id = ?");
    $stmt->execute([$fundraise_id]);

    // Get the fundraise data
    $stmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE fundraise_id = ?");
    $stmt->execute([$fundraise_id]);
    $fund = $stmt->fetch();

    if ($fund) {
        // Add collected amount to spent_amount for this fundraise
        $stmt2 = $pdo->prepare("UPDATE event_fundraise_info SET spent_amount = ? WHERE fundraise_id = ?");
        $stmt2->execute([$fund['collected_amount'], $fundraise_id]);

        // Add collected amount to the budget pocket
        $stmt3 = $pdo->prepare("
            UPDATE event_fundraise_info
            SET collected_amount = collected_amount + ?
            WHERE fundraise_id = (SELECT pocket_id FROM event_basic_info WHERE event_id = ?)
        ");
        $stmt3->execute([$fund['collected_amount'], $event_id]);
    }

    if ($is_ajax) {
        echo json_encode(['success' => true, 'message' => 'Fundraise compiled to budget successfully']);
        exit;
    }
    redirect("pages/control-event.php?id={$event_id}");
}

// Delete fundraise (refund contributors)
if (isset($_POST['delete_fundraise']) && isset($_POST['fundraise_id'])) {
    $fundraise_id = $_POST['fundraise_id'];
    $is_ajax = isset($_POST['ajax']);
    
    // Change status to Terminated
    $stmt = $pdo->prepare("UPDATE event_fundraise_info SET fundraise_status = 'Terminated' WHERE fundraise_id = ?");
    $stmt->execute([$fundraise_id]);
    
    // Get all funding records for this fundraise
    $stmt = $pdo->prepare("SELECT * FROM event_funding_records WHERE fundraise_id = ?");
    $stmt->execute([$fundraise_id]);
    $records = $stmt->fetchAll();
    
    $total_refund = 0;
    foreach ($records as $record) {
        $total_refund += $record['funded_amount'];
        
        // Insert refund into fundraise_user_transactions
        $stmt2 = $pdo->prepare("
            INSERT INTO fundraise_user_transactions 
            (event_id, fundraise_id, user_id, account_id, transaction_amount, transaction_details, transaction_permission, acceptance_status)
            SELECT ?, ?, payer_id, 
                   (SELECT account_id FROM user_wallet_info WHERE user_id = payer_id LIMIT 1),
                   ?, 'Refund from cancelled event', 'Allowed', 'Waiting'
            FROM event_funding_records WHERE funding_id = ?
        ");
        $stmt2->execute([$event_id, $fundraise_id, $record['funded_amount'], $record['funding_id']]);
    }
    
    // Update spent_amount with total refunded
    $stmt3 = $pdo->prepare("UPDATE event_fundraise_info SET spent_amount = ? WHERE fundraise_id = ?");
    $stmt3->execute([$total_refund, $fundraise_id]);
    
    if ($is_ajax) {
        echo json_encode(['success' => true, 'message' => 'Fundraise deleted and refunds processed']);
        exit;
    }
    redirect("control-event.php?id={$event_id}");
}

// Stop receiving funds
if (isset($_POST['stop_funds']) && isset($_POST['fundraise_id'])) {
    $fundraise_id = $_POST['fundraise_id'];
    $is_ajax = isset($_POST['ajax']);
    $stmt = $pdo->prepare("UPDATE event_fundraise_info SET fundraise_status = 'Complete' WHERE fundraise_id = ?");
    $stmt->execute([$fundraise_id]);

    if ($is_ajax) {
        echo json_encode(['success' => true, 'message' => 'Fundraise stopped successfully']);
        exit;
    }
    redirect("pages/control-event.php?id={$event_id}");
}

// Pay for Service
if (isset($_POST['pay_service']) && isset($_POST['hire_id']) && isset($_POST['amount'])) {
    $hire_id = $_POST['hire_id'];
    $amount = $_POST['amount'];
    
    // Check budget availability
    $budget = getBudgetFundraise($event_id, $pdo);
    if ($budget && ($budget['collected_amount'] - $budget['spent_amount']) >= $amount) {
        // Get service provider details
        $stmt = $pdo->prepare("SELECT user_id, event_id FROM event_service_hiring WHERE hire_id = ?");
        $stmt->execute([$hire_id]);
        $service = $stmt->fetch();
        
        if ($service) {
            // Insert into fundraise_user_transactions
            $stmt2 = $pdo->prepare("
                INSERT INTO fundraise_user_transactions 
                (event_id, fundraise_id, user_id, account_id, transaction_amount, transaction_details, transaction_permission, acceptance_status)
                SELECT ?, 
                       (SELECT pocket_id FROM event_basic_info WHERE event_id = ?),
                       ?, 
                       (SELECT account_id FROM user_wallet_info WHERE user_id = ? LIMIT 1),
                       ?, 'Payment from service in event', 'Waiting', 'Waiting'
            ");
            $stmt2->execute([$event_id, $event_id, $service['user_id'], $service['user_id'], $amount]);
            
            // Update spent_amount
            $stmt3 = $pdo->prepare("
                UPDATE event_fundraise_info 
                SET spent_amount = spent_amount + ? 
                WHERE fundraise_id = (SELECT pocket_id FROM event_basic_info WHERE event_id = ?)
            ");
            $stmt3->execute([$amount, $event_id]);
            
            // Update service payment status
            $stmt4 = $pdo->prepare("UPDATE event_service_hiring SET payment_status = 'Paid' WHERE hire_id = ?");
            $stmt4->execute([$hire_id]);
        }
    } else {
        // Insufficient funds - show error in session
        errorMsg("Sorry! The budget has insufficient funds to support this transaction! Please compile more fundraises to support this transaction!");
    }
    redirect("pages/control-event.php?id={$event_id}");
}

// Pay Rent
if (isset($_POST['pay_rent']) && isset($_POST['rental_id']) && isset($_POST['amount'])) {
    $rental_id = $_POST['rental_id'];
    $amount = $_POST['amount'];
    
    // Check budget availability
    $budget = getBudgetFundraise($event_id, $pdo);
    if ($budget && ($budget['collected_amount'] - $budget['spent_amount']) >= $amount) {
        // Get asset owner details
        $stmt = $pdo->prepare("
            SELECT uea.owner_id, er.event_id 
            FROM event_asset_rentals er
            JOIN user_event_asset uea ON er.asset_id = uea.asset_id
            WHERE er.rental_id = ?
        ");
        $stmt->execute([$rental_id]);
        $rental = $stmt->fetch();
        
        if ($rental) {
            // Insert into fundraise_user_transactions
            $stmt2 = $pdo->prepare("
                INSERT INTO fundraise_user_transactions 
                (event_id, fundraise_id, user_id, account_id, transaction_amount, transaction_details, transaction_permission, acceptance_status)
                SELECT ?, 
                       (SELECT pocket_id FROM event_basic_info WHERE event_id = ?),
                       ?, 
                       (SELECT account_id FROM user_wallet_info WHERE user_id = ? LIMIT 1),
                       ?, 'Payment from property rentals in event', 'Waiting', 'Waiting'
            ");
            $stmt2->execute([$event_id, $event_id, $rental['owner_id'], $rental['owner_id'], $amount]);
            
            // Update spent_amount
            $stmt3 = $pdo->prepare("
                UPDATE event_fundraise_info 
                SET spent_amount = spent_amount + ? 
                WHERE fundraise_id = (SELECT pocket_id FROM event_basic_info WHERE event_id = ?)
            ");
            $stmt3->execute([$amount, $event_id]);
        }
    } else {
        errorMsg("Sorry! The budget has insufficient funds to support this transaction! Please compile more fundraises to support this transaction!");
    }
    redirect("pages/control-event.php?id={$event_id}");
}

// Deny Asset Reception
if (isset($_POST['deny_asset']) && isset($_POST['rental_id'])) {
    $rental_id = $_POST['rental_id'];

    // Get rental details
    $stmt = $pdo->prepare("SELECT * FROM event_asset_rentals WHERE rental_id = ?");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch();

    if ($rental) {
        // Check if return record already exists
        $check_stmt = $pdo->prepare("SELECT return_id FROM event_asset_returns WHERE rental_id = ?");
        $check_stmt->execute([$rental_id]);
        $existing_return = $check_stmt->fetch();

        if (!$existing_return) {
            // Insert into event_asset_returns
            $stmt3 = $pdo->prepare("
                INSERT INTO event_asset_returns
                (rental_id, event_id, asset_id, returned_quantity, returned_date, returned_time, return_status, payment_status, reception_status)
                VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'Complete', 'Unpaid', 'Received')
            ");
            $stmt3->execute([$rental_id, $rental['event_id'], $rental['asset_id'], $rental['rented_quantity']]);

            // Update asset_status to Available
            $stmt4 = $pdo->prepare("UPDATE user_event_asset SET asset_status = 'Available' WHERE asset_id = ?");
            $stmt4->execute([$rental['asset_id']]);
        }

        // Update rental status
        $stmt2 = $pdo->prepare("UPDATE event_asset_rentals SET renting_status = 'Postponed' WHERE rental_id = ?");
        $stmt2->execute([$rental_id]);
    }

    redirect("pages/control-event.php?id={$event_id}");
}

// Confirm Asset Arrival
if (isset($_POST['confirm_arrival']) && isset($_POST['rental_id'])) {
    $rental_id = $_POST['rental_id'];
    $stmt = $pdo->prepare("UPDATE event_asset_rentals SET renting_status = 'Received' WHERE rental_id = ?");
    $stmt->execute([$rental_id]);
    redirect("pages/control-event.php?id={$event_id}");
}

// Toggle chat permissions
if (isset($_POST['toggle_group_chat'])) {
    $new_value = $event['groupchat_permission'] == 'Unlocked' ? 'Locked' : 'Unlocked';
    $stmt = $pdo->prepare("UPDATE event_basic_info SET groupchat_permission = ? WHERE event_id = ?");
    $stmt->execute([$new_value, $event_id]);
    redirect("control-event.php?id={$event_id}");
}
if (isset($_POST['toggle_private_chat'])) {
    $new_value = $event['privatechat_permission'] == 'Unlocked' ? 'Locked' : 'Unlocked';
    $stmt = $pdo->prepare("UPDATE event_basic_info SET privatechat_permission = ? WHERE event_id = ?");
    $stmt->execute([$new_value, $event_id]);
    redirect("control-event.php?id={$event_id}");
}

// --- Shut Down Event (5-step process) ---

// Step 1: Return rented assets
if (isset($_POST['return_asset']) && isset($_POST['rental_id']) && isset($_POST['returned_quantity'])) {
    $rental_id = $_POST['rental_id'];
    $returned_qty = (int)$_POST['returned_quantity'];

    // Get rental details
    $stmt = $pdo->prepare("SELECT * FROM event_asset_rentals WHERE rental_id = ? AND renting_status = 'Received'");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch();

    if (!$rental) {
        errorMsg("Rental record not found or already returned.");
        redirect("pages/control-event.php?id={$event_id}&step=terminate");
    }

    // Validate quantity
    if ($returned_qty <= $rental['rented_quantity'] && $returned_qty > 0) {
        // Update rental status
        $stmt2 = $pdo->prepare("UPDATE event_asset_rentals SET renting_status = 'Returned' WHERE rental_id = ?");
        $stmt2->execute([$rental_id]);

        // Insert into event_asset_returns
        $return_status = ($returned_qty == $rental['rented_quantity']) ? 'Complete' : 'Incomplete';
        $stmt3 = $pdo->prepare("
            INSERT INTO event_asset_returns
            (rental_id, event_id, asset_id, returned_quantity, returned_date, returned_time, return_status, payment_status, reception_status)
            VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Unpaid', 'Waiting')
        ");
        $stmt3->execute([$rental_id, $event_id, $rental['asset_id'], $returned_qty, $return_status]);

        successMsg("Asset returned successfully.");
    } else {
        errorMsg("Please enter a quantity less or equal to the one that you rented for this event!");
    }
    redirect("pages/control-event.php?id={$event_id}&step=terminate");
}

// Step 2: Complete rental transactions
if (isset($_POST['complete_rental_payment']) && isset($_POST['rental_id'])) {
    $rental_id = $_POST['rental_id'];

    // Update asset return payment status
    $stmt = $pdo->prepare("UPDATE event_asset_returns SET payment_status = 'Paid' WHERE rental_id = ?");
    $stmt->execute([$rental_id]);

    // Update fundraise_user_transactions
    $stmt2 = $pdo->prepare("
        UPDATE fundraise_user_transactions
        SET transaction_permission = 'Allowed'
        WHERE event_id = ? AND transaction_details = 'Payment from property rentals in event' AND transaction_permission = 'Waiting'
    ");
    $stmt2->execute([$event_id]);

    redirect("pages/control-event.php?id={$event_id}&step=terminate");
}

// Step 3: Complete service transactions
if (isset($_POST['complete_service_payment']) && isset($_POST['hire_id'])) {
    $hire_id = $_POST['hire_id'];

    // Update service payment status
    $stmt = $pdo->prepare("UPDATE event_service_hiring SET payment_status = 'Paid' WHERE hire_id = ?");
    $stmt->execute([$hire_id]);

    // Update fundraise_user_transactions
    $stmt2 = $pdo->prepare("
        UPDATE fundraise_user_transactions
        SET transaction_permission = 'Allowed'
        WHERE event_id = ? AND transaction_details = 'Payment from service in event' AND transaction_permission = 'Waiting'
    ");
    $stmt2->execute([$event_id]);

    redirect("pages/control-event.php?id={$event_id}&step=terminate");
}

// Step 5: Close Event
if (isset($_POST['close_event'])) {
    // Get budget pocket
    $budget = getBudgetFundraise($event_id, $pdo);
    if ($budget) {
        $difference = $budget['collected_amount'] - $budget['spent_amount'];
        
        if ($difference > 0) {
            // Insert remaining funds to host
            $stmt = $pdo->prepare("
                INSERT INTO fundraise_user_transactions 
                (event_id, fundraise_id, user_id, account_id, transaction_amount, transaction_details, transaction_permission, acceptance_status)
                SELECT ?, 
                       (SELECT pocket_id FROM event_basic_info WHERE event_id = ?),
                       ?, 
                       (SELECT account_id FROM user_wallet_info WHERE user_id = ? LIMIT 1),
                       ?, 'Remaining funds from the budget of the event', 'Allowed', 'Waiting'
            ");
            $stmt->execute([$event_id, $event_id, $user_id, $user_id, $difference]);
        }
        
        // Update spent_amount to equal collected_amount
        $stmt2 = $pdo->prepare("
            UPDATE event_fundraise_info 
            SET spent_amount = collected_amount 
            WHERE fundraise_id = (SELECT pocket_id FROM event_basic_info WHERE event_id = ?)
        ");
        $stmt2->execute([$event_id]);
    }
    
    // Close the event
    $stmt3 = $pdo->prepare("UPDATE event_basic_info SET event_activeness = 'Closed' WHERE event_id = ?");
    $stmt3->execute([$event_id]);

    redirect('pages/history.php');
}

// --- Add New Fundraise (from Fundraises tab) ---
if (isset($_POST['add_fundraise']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $title = clean($_POST['fundraise_title'] ?? '');
    
    // Check if the user is authorized for this event
    $auth_stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $auth_stmt->execute([$event_id]);
    $event_host = $auth_stmt->fetchColumn();
    
    if ($event_host != $user_id) {
        errorMsg("You are not authorized to manage fundraises for this event.");
        redirect("control-event.php?id={$event_id}");
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
        redirect("pages/control-event.php?id={$event_id}");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        errorMsg("Failed to create fundraise: " . $e->getMessage());
        redirect("pages/control-event.php?id={$event_id}");
    }
}

// ---- Fetch Data for Tabs ----

$completeFundraises = getEventFundraises($event_id, $pdo, 'Complete');
$activeFundraises = getEventFundraises($event_id, $pdo, 'Active');
$budgetFund = getBudgetFundraise($event_id, $pdo);
$serviceProviders = getServiceProviders($event_id, $pdo);
$assetRentals = getAssetRentals($event_id, $pdo, 'Received');
$bookedAssets = getBookedAssets($event_id, $pdo);
$attendees = getAttendees($event_id, $pdo);
$serviceProviderList = getServiceProviderList($event_id, $pdo);
$galleryMedia = getEventGallery($event_id, $pdo);

// Check if in terminate mode
$terminate_mode = isset($_GET['step']) && $_GET['step'] == 'terminate';

// Check step availability for termination
$can_step2 = true;
$can_step3 = true;
$can_step4 = true;
$can_step5 = true;

// Check if any assets are still Booked or Received
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_asset_rentals WHERE event_id = ? AND renting_status IN ('Booked', 'Received')");
$stmt->execute([$event_id]);
if ($stmt->fetchColumn() > 0) {
    $can_step2 = false;
}

// Check if any asset returns are unpaid
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_asset_returns WHERE event_id = ? AND payment_status = 'Unpaid'");
$stmt->execute([$event_id]);
if ($stmt->fetchColumn() > 0) {
    $can_step3 = false;
}

// Check if any services are unpaid
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_service_hiring WHERE event_id = ? AND payment_status = 'Unpaid' AND service_status = 'Accepted'");
$stmt->execute([$event_id]);
if ($stmt->fetchColumn() > 0) {
    $can_step4 = false;
}

// Check if any fundraises are not Compiled
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_fundraise_info WHERE event_id = ? AND fundraise_status NOT IN ('Compiled', 'Complete') AND fundraise_duration != 'Post-event'");
$stmt->execute([$event_id]);
if ($stmt->fetchColumn() > 0) {
    $can_step5 = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Event - <?= htmlspecialchars($event['event_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .tab-active { border-bottom: 4px solid #6366f1; color: #6366f1; font-weight: 600; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .step { display: none; }
        .step.active { display: block; }
        .modal-overlay {
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-approved { background: #10b981; color: white; }
        .badge-pending { background: #f59e0b; color: white; }
        .badge-denied { background: #ef4444; color: white; }
        .badge-active { background: #3b82f6; color: white; }
        .badge-inactive { background: #6b7280; color: white; }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #6366f1; }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen pb-24">

<!-- HEADER -->
<header class="glass sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Control Event</h1>
        <button onclick="history.back()" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-arrow-left"></i> Back
        </button>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Event Info -->
    <div class="glass rounded-3xl p-6 mb-6">
        <h2 class="text-2xl font-semibold"><?= htmlspecialchars($event['event_title']) ?></h2>
        <p class="text-indigo-600"><?= htmlspecialchars($event['event_category']) ?> • <?= date('d M Y H:i', strtotime($event['event_date'] . ' ' . $event['event_time'])) ?></p>
        <p class="mt-2">Status: <span class="font-medium"><?= $event['event_activeness'] ?></span></p>
    </div>



    <!-- TAB 0: Fundraises -->
    <div id="panel0" class="tab-panel active">
        <!-- Table of Complete Fundraises -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Complete Fundraises</h3>
            <?php if (empty($completeFundraises)): ?>
                <p class="text-gray-500">No complete fundraises yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Fundraise Title</th>
                                <th class="text-left p-2">Created On</th>
                                <th class="text-left p-2">Planned Amount</th>
                                <th class="text-left p-2">Collected Amount</th>
                                <th class="text-left p-2">Spent Amount</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completeFundraises as $fund): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($fund['fundraise_title']) ?></td>
                                    <td class="p-2"><?= $fund['creation_date'] . ' ' . $fund['creation_time'] ?></td>
                                    <td class="p-2">TZS <?= number_format($fund['required_amount'] ?? 0, 2) ?></td>
                                    <td class="p-2">TZS <?= number_format($fund['collected_amount'] ?? 0, 2) ?></td>
                                    <td class="p-2">TZS <?= number_format($fund['spent_amount'] ?? 0, 2) ?></td>
                                    <td class="p-2">
                                        <div class="flex flex-wrap gap-2">
                                            <button onclick="viewFunders('<?= $fund['fundraise_id'] ?>')" class="bg-blue-500 text-white px-3 py-1 rounded-xl text-xs">View Funders</button>
                                            <button onclick="compileFundraise('<?= $fund['fundraise_id'] ?>')" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Compile to Budget</button>
                                            <button onclick="deleteFundraise('<?= $fund['fundraise_id'] ?>')" class="bg-red-500 text-white px-3 py-1 rounded-xl text-xs">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Fundraises -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Active Fundraises</h3>
            <?php if (empty($activeFundraises)): ?>
                <p class="text-gray-500">No active fundraises.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Fundraise Title</th>
                                <th class="text-left p-2">Created On</th>
                                <th class="text-left p-2">Planned Amount</th>
                                <th class="text-left p-2">Collected Amount</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeFundraises as $fund): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($fund['fundraise_title']) ?></td>
                                    <td class="p-2"><?= $fund['creation_date'] . ' ' . $fund['creation_time'] ?></td>
                                    <td class="p-2">TZS <?= number_format($fund['required_amount'] ?? 0, 2) ?></td>
                                    <td class="p-2">TZS <?= number_format($fund['collected_amount'] ?? 0, 2) ?></td>
                                    <td class="p-2">
                                        <button onclick="stopFunds('<?= $fund['fundraise_id'] ?>')" class="bg-red-500 text-white px-3 py-1 rounded-xl text-xs">Stop receiving funds</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add New Fundraise Form -->
        <div class="glass rounded-3xl p-6">
            <button onclick="openFundraiseModal(<?= $event_id ?>)" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl">
                <i class="fa fa-plus"></i> Add New Fundraise
            </button>
        </div>
    </div>

    <!-- TAB 1: Budgeting -->
    <div id="panel1" class="tab-panel">
        <!-- Budget Summary -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Budget Summary</h3>
            <?php if ($budgetFund): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Title</p>
                        <p class="font-semibold"><?= htmlspecialchars($budgetFund['fundraise_title']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Available (TZS)</p>
                        <p class="font-semibold text-green-600">TZS <?= number_format($budgetFund['collected_amount'] - $budgetFund['spent_amount'], 2) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Spent (TZS)</p>
                        <p class="font-semibold text-red-600">TZS <?= number_format($budgetFund['spent_amount'] ?? 0, 2) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No budget fundraise found.</p>
            <?php endif; ?>
        </div>

        <!-- Service Providers -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Service Providers</h3>
            <?php if (empty($serviceProviders)): ?>
                <p class="text-gray-500">No active service providers.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">User</th>
                                <th class="text-left p-2">Rating</th>
                                <th class="text-left p-2">Hired as</th>
                                <th class="text-left p-2">For (TZS)</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceProviders as $sp): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($sp['user_full_name']) ?></td>
                                    <td class="p-2"><?= number_format($sp['job_average_rating'] ?? 0, 1) ?> ⭐</td>
                                    <td class="p-2"><?= htmlspecialchars($sp['profession_title']) ?></td>
                                    <td class="p-2">TZS <?= number_format($sp['hire_amount'], 2) ?></td>
                                    <td class="p-2">
                                        <form method="POST">
                                            <input type="hidden" name="hire_id" value="<?= $sp['hire_id'] ?>">
                                            <input type="hidden" name="amount" value="<?= $sp['hire_amount'] ?>">
                                            <button type="submit" name="pay_service" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Pay for Service</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Asset Rentals -->
        <div class="glass rounded-3xl p-6">
            <h3 class="font-semibold text-lg mb-4">Asset Rentals</h3>
            <?php if (empty($assetRentals)): ?>
                <p class="text-gray-500">No received asset rentals.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Owner</th>
                                <th class="text-left p-2">Asset</th>
                                <th class="text-left p-2">Rented Qty</th>
                                <th class="text-left p-2">Renting Price</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assetRentals as $ar): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($ar['user_full_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($ar['asset_name']) ?> (<?= htmlspecialchars($ar['asset_quality']) ?>)</td>
                                    <td class="p-2"><?= $ar['rented_quantity'] ?></td>
                                    <td class="p-2">TZS <?= number_format($ar['total_renting_price'], 2) ?></td>
                                    <td class="p-2">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST">
                                                <input type="hidden" name="rental_id" value="<?= $ar['rental_id'] ?>">
                                                <input type="hidden" name="amount" value="<?= $ar['total_renting_price'] ?>">
                                                <button type="submit" name="pay_rent" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Pay Rent</button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="rental_id" value="<?= $ar['rental_id'] ?>">
                                                <button type="submit" name="deny_asset" class="bg-red-500 text-white px-3 py-1 rounded-xl text-xs">Deny</button>
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
    </div>

    <!-- TAB 2: Control (Default) -->
    <div id="panel2" class="tab-panel">
        <!-- Chatrooms -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Chatrooms</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span>Group Chats</span>
                    <form method="POST">
                        <label class="toggle-switch">
                            <input type="checkbox" <?= $event['groupchat_permission'] == 'Unlocked' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="slider"></span>
                        </label>
                        <input type="hidden" name="toggle_group_chat" value="1">
                    </form>
                </div>
                <div class="flex items-center justify-between">
                    <span>Private Chats</span>
                    <form method="POST">
                        <label class="toggle-switch">
                            <input type="checkbox" <?= $event['privatechat_permission'] == 'Unlocked' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="slider"></span>
                        </label>
                        <input type="hidden" name="toggle_private_chat" value="1">
                    </form>
                </div>
            </div>
        </div>

        <!-- Booked Assets -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Booked Assets</h3>
            <?php if (empty($bookedAssets)): ?>
                <p class="text-gray-500">No booked assets.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Owner</th>
                                <th class="text-left p-2">Asset</th>
                                <th class="text-left p-2">Rented Qty</th>
                                <th class="text-left p-2">Renting Price</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookedAssets as $ba): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($ba['user_full_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($ba['asset_name']) ?></td>
                                    <td class="p-2"><?= $ba['rented_quantity'] ?></td>
                                    <td class="p-2">TZS <?= number_format($ba['total_renting_price'], 2) ?></td>
                                    <td class="p-2">
                                        <form method="POST">
                                            <input type="hidden" name="rental_id" value="<?= $ba['rental_id'] ?>">
                                            <button type="submit" name="confirm_arrival" class="bg-indigo-600 text-white px-3 py-1 rounded-xl text-xs">Confirm Arrival</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Terminate Event -->
        <div class="glass rounded-3xl p-6 border-2 border-red-300">
            <h3 class="font-semibold text-lg mb-4 text-red-600">Terminate Event</h3>
            <button onclick="openTerminateModal()" class="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-2xl font-semibold">
                <i class="fa fa-power-off"></i> Shut Down Event
            </button>
        </div>
    </div>

    <!-- TAB 3: Crowd -->
    <div id="panel3" class="tab-panel">
        <!-- Event Population Summary -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Event Population Summary</h3>
            <?php
                // Count invited people (not denied)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_invitees WHERE event_id = ? AND attendance_status != 'Denied'");
                $stmt->execute([$event_id]);
                $invited = $stmt->fetchColumn();
                
                // Count attended people (active)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_attendees WHERE event_id = ? AND participation_status = 'Active'");
                $stmt->execute([$event_id]);
                $attended = $stmt->fetchColumn();
                
                // Count service providers (active)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_service_hiring WHERE event_id = ? AND presence_status = 'Active'");
                $stmt->execute([$event_id]);
                $providers = $stmt->fetchColumn();
            ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Invited People</p>
                    <p class="font-bold text-2xl"><?= $invited ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Attended People</p>
                    <p class="font-bold text-2xl text-green-600"><?= $attended ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Service Providers</p>
                    <p class="font-bold text-2xl text-blue-600"><?= $providers ?></p>
                </div>
            </div>
        </div>

        <!-- Attendees List -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4">Attendees</h3>
            <?php if (empty($attendees)): ?>
                <p class="text-gray-500">No active attendees.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Name</th>
                                <th class="text-left p-2">Badge</th>
                                <th class="text-left p-2">Position</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $att):
                                $attendee_profile_url = getProfilePictureUrl($att['user_profile_picture'] ?? '');
                            ?>
                                <tr>
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <img src="<?= htmlspecialchars($attendee_profile_url) ?>" class="w-8 h-8 rounded-full object-cover">
                                            <?= htmlspecialchars($att['user_full_name']) ?>
                                        </div>
                                    </td>
                                    <td class="p-2"><?= htmlspecialchars($att['participation_badge'] ?? 'Normal') ?></td>
                                    <td class="p-2"><?= htmlspecialchars($att['invitation_position']) ?></td>
                                    <td class="p-2">
                                        <button onclick="viewAttendee('<?= htmlspecialchars($att['participant_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($att['user_full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($att['user_phone_number'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($att['participation_badge'] ?? 'Normal', ENT_QUOTES) ?>', '<?= htmlspecialchars($att['invitation_position'], ENT_QUOTES) ?>', '<?= htmlspecialchars($attendee_profile_url, ENT_QUOTES) ?>')" class="bg-indigo-600 text-white px-3 py-1 rounded-xl text-xs">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Service Providers List -->
        <div class="glass rounded-3xl p-6">
            <h3 class="font-semibold text-lg mb-4">Service Providers</h3>
            <?php if (empty($serviceProviderList)): ?>
                <p class="text-gray-500">No active service providers.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($serviceProviderList as $sp): ?>
                        <div class="glass rounded-2xl p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars(getProfilePictureUrl($sp['user_profile_picture'] ?? '')) ?>" class="w-12 h-12 rounded-full object-cover">
                                <div>
                                    <p class="font-semibold"><?= htmlspecialchars($sp['user_full_name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($sp['profession_title']) ?></p>
                                    <p class="text-xs">⭐ <?= number_format($sp['job_average_rating'] ?? 0, 1) ?></p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="fireProvider(<?= $sp['hire_id'] ?>)" class="bg-red-500 text-white px-3 py-1 rounded-xl text-xs">Fire</button>
                                <button onclick="rateProvider('<?= $sp['profile_id'] ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded-xl text-xs">Rate</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 4: Gallery -->
    <div id="panel4" class="tab-panel">
        <div class="glass rounded-3xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Event Gallery</h3>
                <button onclick="openUploadModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl">
                    <i class="fa fa-upload"></i> Upload
                </button>
            </div>
            
            <?php if (empty($galleryMedia)): ?>
                <p class="text-gray-500">No media in the gallery yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($galleryMedia as $media): ?>
                        <div class="relative group rounded-xl overflow-hidden glass">
                            <?php if ($media['media_type'] == 'Photo'): ?>
                                <img src="<?= htmlspecialchars($media['media_file']) ?>" class="w-full h-48 object-cover">
                            <?php else: ?>
                                <video src="<?= htmlspecialchars($media['media_file']) ?>" class="w-full h-48 object-cover" controls></video>
                            <?php endif; ?>
                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs p-2">
                                <?= htmlspecialchars($media['user_full_name']) ?>
                            </div>
                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition">
                                <button onclick="viewMedia('<?= $media['media_id'] ?>')" class="bg-white/80 text-black p-1 rounded-full text-xs">View</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TERMINATE EVENT MODAL (Shut Down Event) -->
<div id="terminateModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Shut Down Event</h2>
            <button onclick="closeTerminateModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times text-xl"></i></button>
        </div>
        
        <!-- Step Navigation -->
        <div class="flex justify-between mb-6">
            <button onclick="changeTerminateStep(-1)" class="text-indigo-600" id="termBack">Back</button>
            <span id="termIndicator">Step 1 of 5</span>
            <button onclick="changeTerminateStep(1)" class="text-indigo-600" id="termNext">Next</button>
        </div>
        
        <!-- Step 1: Return rented assets -->
        <div class="step active" data-step="1">
            <h3 class="font-semibold text-lg mb-4">Return rented assets</h3>
            <?php
                $returnAssets = getAssetRentals($event_id, $pdo, 'Received');
            ?>
            <?php if (empty($returnAssets)): ?>
                <p class="text-green-600">All assets have been returned.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Owner</th>
                                <th class="text-left p-2">Asset</th>
                                <th class="text-left p-2">Rented Qty</th>
                                <th class="text-left p-2">Return Qty</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returnAssets as $ra): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($ra['user_full_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($ra['asset_name']) ?></td>
                                    <td class="p-2"><?= $ra['rented_quantity'] ?></td>
                                    <td class="p-2">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="number" name="returned_quantity" value="<?= $ra['rented_quantity'] ?>" max="<?= $ra['rented_quantity'] ?>" min="1" class="w-20 rounded-xl px-2 py-1 glass">
                                            <input type="hidden" name="rental_id" value="<?= $ra['rental_id'] ?>">
                                            <button type="submit" name="return_asset" class="bg-indigo-600 text-white px-3 py-1 rounded-xl text-xs">Return</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($returnAssets)): ?>
                    <p class="text-red-500 text-sm mt-2">* You must return all assets before proceeding.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Step 2: Complete rental transactions -->
        <div class="step" data-step="2">
            <h3 class="font-semibold text-lg mb-4">Complete rental transactions</h3>
            <?php
                $stmt = $pdo->prepare("
                    SELECT er.*, uea.asset_name, uea.asset_quality, ubi.user_full_name
                    FROM event_asset_rentals er
                    JOIN user_event_asset uea ON er.asset_id = uea.asset_id
                    JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
                    WHERE er.event_id = ? AND er.renting_status = 'Returned'
                ");
                $stmt->execute([$event_id]);
                $returnedAssets = $stmt->fetchAll();
            ?>
            <?php if (empty($returnedAssets)): ?>
                <p class="text-gray-500">No assets pending payment.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Owner</th>
                                <th class="text-left p-2">Asset</th>
                                <th class="text-left p-2">Renting Price</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returnedAssets as $ra): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($ra['user_full_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($ra['asset_name']) ?></td>
                                    <td class="p-2">TZS <?= number_format($ra['total_renting_price'], 2) ?></td>
                                    <td class="p-2">
                                        <form method="POST">
                                            <input type="hidden" name="rental_id" value="<?= $ra['rental_id'] ?>">
                                            <button type="submit" name="complete_rental_payment" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Complete Payment</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($returnedAssets)): ?>
                    <p class="text-red-500 text-sm mt-2">* All rental payments must be completed before proceeding.</p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$can_step3): ?>
                <p class="text-red-500 text-sm mt-2">* You must complete all rental transactions before proceeding.</p>
            <?php endif; ?>
        </div>
        
        <!-- Step 3: Complete service transactions -->
        <div class="step" data-step="3">
            <h3 class="font-semibold text-lg mb-4">Complete service transactions</h3>
            <?php
                $stmt = $pdo->prepare("
                    SELECT esh.*, uej.profession_title, ubi.user_full_name
                    FROM event_service_hiring esh
                    JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
                    JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
                    WHERE esh.event_id = ? AND esh.service_status = 'Accepted' AND esh.presence_status = 'Active'
                ");
                $stmt->execute([$event_id]);
                $services = $stmt->fetchAll();
            ?>
            <?php if (empty($services)): ?>
                <p class="text-gray-500">No services pending payment.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left p-2">User</th>
                                <th class="text-left p-2">Rating</th>
                                <th class="text-left p-2">Hired as</th>
                                <th class="text-left p-2">For (TZS)</th>
                                <th class="text-left p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $svc): ?>
                                <tr>
                                    <td class="p-2"><?= htmlspecialchars($svc['user_full_name']) ?></td>
                                    <td class="p-2"><?= number_format($svc['job_average_rating'] ?? 0, 1) ?> ⭐</td>
                                    <td class="p-2"><?= htmlspecialchars($svc['profession_title']) ?></td>
                                    <td class="p-2">TZS <?= number_format($svc['hire_amount'], 2) ?></td>
                                    <td class="p-2">
                                        <form method="POST">
                                            <input type="hidden" name="hire_id" value="<?= $svc['hire_id'] ?>">
                                            <button type="submit" name="complete_service_payment" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Complete Payment</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($services)): ?>
                    <p class="text-red-500 text-sm mt-2">* All service payments must be completed before proceeding.</p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$can_step4): ?>
                <p class="text-red-500 text-sm mt-2">* You must complete all service transactions before proceeding.</p>
            <?php endif; ?>
        </div>
        
        <!-- Step 4: Fundraises check -->
        <div class="step" data-step="4">
            <h3 class="font-semibold text-lg mb-4">Fundraises check</h3>
            
            <!-- Active Fundraises -->
            <div class="mb-4">
                <h4 class="font-semibold">Active Fundraises</h4>
                <?php
                    $activeFunds = getEventFundraises($event_id, $pdo, 'Active');
                ?>
                <?php if (empty($activeFunds)): ?>
                    <p class="text-green-600">No active fundraises.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="text-left p-2">Title</th>
                                    <th class="text-left p-2">Created</th>
                                    <th class="text-left p-2">Planned</th>
                                    <th class="text-left p-2">Collected</th>
                                    <th class="text-left p-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeFunds as $fund): ?>
                                    <tr>
                                        <td class="p-2"><?= htmlspecialchars($fund['fundraise_title']) ?></td>
                                        <td class="p-2"><?= $fund['creation_date'] ?></td>
                                        <td class="p-2">TZS <?= number_format($fund['required_amount'] ?? 0, 2) ?></td>
                                        <td class="p-2">TZS <?= number_format($fund['collected_amount'] ?? 0, 2) ?></td>
                                        <td class="p-2">
                                            <form method="POST">
                                                <input type="hidden" name="fundraise_id" value="<?= $fund['fundraise_id'] ?>">
                                                <button type="submit" name="stop_funds" class="bg-red-500 text-white px-3 py-1 rounded-xl text-xs">Stop</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Complete Fundraises -->
            <div>
                <h4 class="font-semibold">Complete Fundraises</h4>
                <?php
                    $completeFunds = getEventFundraises($event_id, $pdo, 'Complete');
                ?>
                <?php if (empty($completeFunds)): ?>
                    <p class="text-green-600">No complete fundraises to compile.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="text-left p-2">Title</th>
                                    <th class="text-left p-2">Created</th>
                                    <th class="text-left p-2">Planned</th>
                                    <th class="text-left p-2">Collected</th>
                                    <th class="text-left p-2">Spent</th>
                                    <th class="text-left p-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completeFunds as $fund): ?>
                                    <tr>
                                        <td class="p-2"><?= htmlspecialchars($fund['fundraise_title']) ?></td>
                                        <td class="p-2"><?= $fund['creation_date'] ?></td>
                                        <td class="p-2">TZS <?= number_format($fund['required_amount'] ?? 0, 2) ?></td>
                                        <td class="p-2">TZS <?= number_format($fund['collected_amount'] ?? 0, 2) ?></td>
                                        <td class="p-2">TZS <?= number_format($fund['spent_amount'] ?? 0, 2) ?></td>
                                        <td class="p-2">
                                            <form method="POST">
                                                <input type="hidden" name="fundraise_id" value="<?= $fund['fundraise_id'] ?>">
                                                <button type="submit" name="compile_fundraise" class="bg-green-500 text-white px-3 py-1 rounded-xl text-xs">Compile</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$can_step5): ?>
                <p class="text-red-500 text-sm mt-2">* You must compile all fundraises before closing the event.</p>
            <?php endif; ?>
        </div>
        
        <!-- Step 5: Close Event -->
        <div class="step" data-step="5">
            <h3 class="font-semibold text-lg mb-4">Close Event</h3>
            <p class="text-gray-600 mb-4">Click the button below to close this event. This action will:</p>
            <ul class="list-disc list-inside text-sm text-gray-600 mb-4">
                <li>Calculate remaining budget funds</li>
                <li>Transfer remaining funds to the host</li>
                <li>Mark the event as Closed</li>
            </ul>
            <form method="POST">
                <button type="submit" name="close_event" class="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-2xl font-semibold text-lg">
                    <i class="fa fa-check-circle"></i> Close Event
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ATTENDEE VIEW MODAL -->
<div id="attendeeModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Attendee Details</h3>
            <button onclick="closeAttendeeModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
        </div>
        <div id="attendeeDetails" class="text-center">
            <img id="attendeePhoto" src="../assets/images/default.png" class="w-24 h-24 rounded-full mx-auto mb-4 object-cover">
            <p id="attendeeName" class="font-semibold text-lg">Name</p>
            <p id="attendeePhone" class="text-gray-600 text-sm">Phone</p>
            <div class="flex justify-center gap-4 mt-2">
                <span id="attendeeBadge" class="badge badge-active">Badge</span>
                <span id="attendeePosition" class="badge badge-pending">Position</span>
            </div>
            <form method="POST" class="mt-4">
                <input type="hidden" name="participant_id" id="removeParticipantId">
                <button type="submit" name="remove_attendee" class="bg-red-500 text-white px-6 py-2 rounded-2xl">Remove from event</button>
                <button type="button" onclick="closeAttendeeModal()" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-2xl ml-2">Close</button>
            </form>
        </div>
    </div>
</div>

<!-- RATE PROVIDER MODAL -->
<div id="rateModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Rate Service Provider</h3>
        <div class="text-center mb-4">
            <div class="text-4xl" id="ratingStars">
                <span onclick="setRating(1)" class="cursor-pointer">⭐</span>
                <span onclick="setRating(2)" class="cursor-pointer">⭐</span>
                <span onclick="setRating(3)" class="cursor-pointer">⭐</span>
                <span onclick="setRating(4)" class="cursor-pointer">⭐</span>
                <span onclick="setRating(5)" class="cursor-pointer">⭐</span>
            </div>
            <input type="hidden" id="ratingValue" value="0">
            <input type="hidden" id="ratingProfileId" value="">
        </div>
        <div>
            <label class="block text-sm font-medium">Review</label>
            <textarea id="reviewText" class="w-full rounded-xl px-4 py-2 glass" rows="3" placeholder="Write your review..."></textarea>
        </div>
        <div class="flex gap-2 mt-4">
            <button onclick="submitRating()" class="bg-indigo-600 text-white px-6 py-2 rounded-2xl">Submit</button>
            <button onclick="closeRateModal()" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-2xl">Close</button>
        </div>
    </div>
</div>

<!-- UPLOAD MEDIA MODAL -->
<div id="uploadModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Upload Media</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium">Select media</label>
                <input type="file" name="media_file" accept="image/*,video/*" class="w-full rounded-xl px-4 py-2 glass" required>
            </div>
            <div class="flex gap-2">
                <button type="submit" name="upload_media" class="bg-indigo-600 text-white px-6 py-2 rounded-2xl">Upload</button>
                <button type="button" onclick="closeUploadModal()" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-2xl">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW MEDIA MODAL -->
<div id="mediaModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <span id="mediaUploader" class="font-semibold">Uploaded by</span>
            <button onclick="closeMediaModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
        </div>
        <div id="mediaDisplay" class="text-center">
            <!-- Media will be displayed here -->
        </div>
        <div class="flex gap-2 mt-4">
            <button onclick="deleteMedia()" class="bg-red-500 text-white px-4 py-2 rounded-2xl">Delete from gallery</button>
            <button onclick="closeMediaModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-2xl">Close</button>
        </div>
    </div>
</div>

<!-- VIEW FUNDERS MODAL -->
<div id="fundersModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Funders</h3>
            <button onclick="closeFundersModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
        </div>
        <div id="fundersList" class="overflow-x-auto">
            <!-- Filled dynamically -->
        </div>
    </div>
</div>

<script>
// ---- Tab Switching ----
function switchTab(n) {
    document.querySelectorAll('.tab').forEach((el, i) => {
        el.classList.toggle('tab-active', i === n);
    });
    document.querySelectorAll('.tab-panel').forEach((el, i) => {
        el.classList.toggle('active', i === n);
    });
}

// ---- Fundraise Form ----
function toggleFundraiseForm() {
    const form = document.getElementById('fundraiseForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleTagFields() {
    const type = document.getElementById('fundraiseType').value;
    document.getElementById('tagFields').style.display = type === 'Contribution' ? 'block' : 'none';
}

function addTagRow() {
    const container = document.getElementById('tagContainer');
    const row = document.createElement('div');
    row.className = 'tag-row flex flex-wrap gap-4 items-end mb-2';
    row.innerHTML = `
        <div><input type="text" name="tag_names[]" class="rounded-xl px-4 py-2 glass" placeholder="Tag Name"></div>
        <div><input type="text" name="tag_details[]" class="rounded-xl px-4 py-2 glass" placeholder="Tag Details"></div>
        <div><input type="number" name="tag_amounts[]" class="rounded-xl px-4 py-2 glass" placeholder="Amount"></div>
        <button type="button" onclick="this.parentElement.remove()" class="bg-red-500 text-white px-4 py-2 rounded-xl">Remove</button>
    `;
    container.appendChild(row);
}

// ---- Terminate Event Modal ----
let terminateStep = 1;
const totalTermSteps = 5;

function openTerminateModal() {
    document.getElementById('terminateModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    terminateStep = 1;
    showTerminateStep(1);
}

function closeTerminateModal() {
    document.getElementById('terminateModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function showTerminateStep(step) {
    document.querySelectorAll('.step').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.step) === step);
    });
    document.getElementById('termIndicator').textContent = `Step ${step} of ${totalTermSteps}`;
    document.getElementById('termBack').style.visibility = step === 1 ? 'hidden' : 'visible';
    document.getElementById('termNext').style.visibility = step === totalTermSteps ? 'hidden' : 'visible';
}

function changeTerminateStep(delta) {
    const newStep = terminateStep + delta;
    if (newStep < 1 || newStep > totalTermSteps) return;
    terminateStep = newStep;
    showTerminateStep(terminateStep);
}

// ---- Attendee Modal ----
let currentAttendeeId = null;

function viewAttendee(participantId, name, phone, badge, position, profilePic) {
    currentAttendeeId = participantId;
    document.getElementById('removeParticipantId').value = participantId;
    document.getElementById('attendeePhoto').src = profilePic || '../assets/images/default.png';
    document.getElementById('attendeeName').textContent = name || 'Name';
    document.getElementById('attendeePhone').textContent = phone || 'Phone';
    document.getElementById('attendeeBadge').textContent = badge || 'Badge';
    document.getElementById('attendeePosition').textContent = position || 'Position';
    document.getElementById('attendeeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAttendeeModal() {
    document.getElementById('attendeeModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ---- Rate Provider Modal ----
let currentProfileId = null;

function rateProvider(profileId) {
    currentProfileId = profileId;
    document.getElementById('ratingProfileId').value = profileId;
    document.getElementById('ratingValue').value = 0;
    document.getElementById('reviewText').value = '';
    document.getElementById('ratingStars').innerHTML = '⭐⭐⭐⭐⭐';
    document.getElementById('rateModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function setRating(val) {
    document.getElementById('ratingValue').value = val;
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<span onclick="setRating(${i})" class="cursor-pointer">${i <= val ? '⭐' : '☆'}</span>`;
    }
    document.getElementById('ratingStars').innerHTML = stars;
}

function submitRating() {
    const rating = document.getElementById('ratingValue').value;
    const review = document.getElementById('reviewText').value;
    const profileId = document.getElementById('ratingProfileId').value;
    if (rating == 0) {
        alert('Please select a rating.');
        return;
    }
    // Submit via AJAX or form
    alert(`Rating submitted: ${rating} stars, Review: ${review}`);
    closeRateModal();
}

function closeRateModal() {
    document.getElementById('rateModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ---- Fire Provider ----
function fireProvider(hireId) {
    if (confirm('Are you sure you want to fire this service provider?')) {
        // Submit via form
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="hire_id" value="${hireId}"><input type="hidden" name="fire_provider" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
}

// ---- Upload Media ----
function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ---- Media View ----
let currentMediaId = null;

function viewMedia(mediaId) {
    currentMediaId = mediaId;
    // In production, fetch via AJAX
    document.getElementById('mediaModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeMediaModal() {
    document.getElementById('mediaModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function deleteMedia() {
    if (confirm('Delete this media from the gallery?')) {
        alert('Media deleted.');
        closeMediaModal();
    }
}

// ---- View Funders ----
function viewFunders(fundraiseId) {
    // In production, fetch via AJAX
    document.getElementById('fundersModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // Populate with sample data
    document.getElementById('fundersList').innerHTML = `
        <p class="text-center text-gray-500">Loading funders...</p>
    `;
}

function closeFundersModal() {
    document.getElementById('fundersModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ---- Fundraise AJAX Functions ----
function compileFundraise(fundraiseId) {
    if (!confirm('Compile this fundraise to budget?')) return;
    
    const formData = new FormData();
    formData.append('fundraise_id', fundraiseId);
    formData.append('compile_fundraise', '1');
    formData.append('ajax', '1');
    
    fetch('control-event.php?id=<?= $event_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function deleteFundraise(fundraiseId) {
    if (!confirm('Delete this fundraise? This will refund all contributors.')) return;
    
    const formData = new FormData();
    formData.append('fundraise_id', fundraiseId);
    formData.append('delete_fundraise', '1');
    formData.append('ajax', '1');
    
    fetch('control-event.php?id=<?= $event_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function stopFunds(fundraiseId) {
    if (!confirm('Stop receiving funds for this fundraise?')) return;
    
    const formData = new FormData();
    formData.append('fundraise_id', fundraiseId);
    formData.append('stop_funds', '1');
    formData.append('ajax', '1');
    
    fetch('control-event.php?id=<?= $event_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Close modals on background click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });
});

// ESC key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(modal => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }
});

// ---- Handle Fire Provider POST ----
<?php
// Fire provider
if (isset($_POST['fire_provider']) && isset($_POST['hire_id'])) {
    $hire_id = $_POST['hire_id'];
    $stmt = $pdo->prepare("UPDATE event_service_hiring SET hire_status = 'Rejected', presence_status = 'Banned' WHERE hire_id = ?");
    $stmt->execute([$hire_id]);
    redirect("control-event.php?id={$event_id}");
}

// Remove attendee
if (isset($_POST['remove_attendee']) && isset($_POST['participant_id'])) {
    $participant_id = $_POST['participant_id'];
    $stmt = $pdo->prepare("UPDATE event_attendees SET participation_status = 'Banned' WHERE participant_id = ? AND event_id = ?");
    $stmt->execute([$participant_id, $event_id]);
    redirect("control-event.php?id={$event_id}");
}

// Upload media
if (isset($_POST['upload_media']) && isset($_FILES['media_file'])) {
    $file = $_FILES['media_file'];
    $upload_dir = '../uploads/event_media/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $filename = time() . '_' . basename($file['name']);
    $path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $path)) {
        $media_type = strpos($file['type'], 'video') !== false ? 'Video' : 'Photo';
        $media_id = 'MEDIA-' . strtoupper(bin2hex(random_bytes(5)));
        
        $stmt = $pdo->prepare("INSERT INTO event_shared_media (media_id, event_id, media_type, uploader_id, media_file) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$media_id, $event_id, $media_type, $user_id, $path]);
    }
    redirect("control-event.php?id={$event_id}");
}
?>
</script>

<!-- STICKY FOOTER - Tab Navigation -->
<footer class="glass fixed bottom-0 left-0 right-0 z-40 border-t border-white/30">
    <div class="flex justify-around max-w-7xl mx-auto">
        <button onclick="switchTab(0)" class="tab-footer flex-1 py-4 text-center text-indigo-700 hover:bg-white/10 transition" id="ft0"><i class="fa fa-wallet"></i><br><span class="text-xs">Fundraises</span></button>
        <button onclick="switchTab(1)" class="tab-footer flex-1 py-4 text-center text-gray-700 hover:bg-white/10 transition" id="ft1"><i class="fa fa-calculator"></i><br><span class="text-xs">Budgeting</span></button>
        <button onclick="switchTab(2)" class="tab-footer flex-1 py-4 text-center text-gray-700 hover:bg-white/10 transition" id="ft2"><i class="fa fa-sliders-h"></i><br><span class="text-xs">Control</span></button>
        <button onclick="switchTab(3)" class="tab-footer flex-1 py-4 text-center text-gray-700 hover:bg-white/10 transition" id="ft3"><i class="fa fa-users"></i><br><span class="text-xs">Crowd</span></button>
        <button onclick="switchTab(4)" class="tab-footer flex-1 py-4 text-center text-gray-700 hover:bg-white/10 transition" id="ft4"><i class="fa fa-images"></i><br><span class="text-xs">Gallery</span></button>
    </div>
</footer>

<script>
// Sync footer tabs with main tabs
function switchTab(n) {
    // Main tabs
    document.querySelectorAll('.tab').forEach((el, i) => {
        el.classList.toggle('tab-active', i === n);
    });
    document.querySelectorAll('.tab-panel').forEach((el, i) => {
        el.classList.toggle('active', i === n);
    });
    // Footer tabs
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
</script>

<!-- Fundraise Modal -->
<div id="fundraiseModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-indigo-700 flex items-center gap-2">
                <i class="fa fa-hand-holding-usd"></i> Create New Fundraise
            </h3>
            <button onclick="closeFundraiseModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times text-xl"></i></button>
        </div>

        <form method="POST" action="control-event.php?id=<?= $event_id ?>">
            <input type="hidden" name="add_fundraise" value="1">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Title of the fundraise</label>
                <input type="text" name="fundraise_title" required placeholder="e.g. Wedding Contribution"
                       class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">
            </div>

            <!-- Goal Toggle -->
            <div class="flex items-center justify-between mb-4 bg-white/30 p-4 rounded-2xl border border-white/40">
                <span class="text-sm font-semibold text-gray-700">Is there a maximum total amount (goal) required for this fundraise?</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="has_goal" id="hasGoalToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <!-- Goal Amount -->
            <div id="goalAmountWrapper" class="mb-4 opacity-50 pointer-events-none transition-all duration-300">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Enter the maximum amount required</label>
                <input type="number" name="required_amount" id="goalAmountInput" step="0.01" min="0" placeholder="0.00" disabled class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
            </div>

            <!-- Duration Dropdown -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">When will the participants be required to contribute/donate for this event?</label>
                <select name="fundraise_duration" class="w-full rounded-2xl px-4 py-3 bg-white/60 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                    <option value="Before the event">Before the event</option>
                    <option value="During the event">During the event</option>
                </select>
            </div>

            <!-- Fixed Amount Toggle -->
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
    document.getElementById('fundraiseModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeFundraiseModal() {
    document.getElementById('fundraiseModal').classList.add('hidden');
    document.body.style.overflow = '';
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
    } else {
        wrapper.classList.add('opacity-50', 'pointer-events-none');
        input.disabled = true;
        input.value = '';
    }
});

// Fixed Amount Toggle Logic
document.getElementById('hasFixedToggle').addEventListener('change', function() {
    const wrapper = document.getElementById('fixedAmountsWrapper');
    const inputs = wrapper.querySelectorAll('.tag-input');
    const addBtn = document.getElementById('addTagRowBtn');
    const removeBtns = wrapper.querySelectorAll('.remove-tag-btn');
    if (this.checked) {
        wrapper.classList.remove('opacity-50', 'pointer-events-none');
        inputs.forEach(input => input.disabled = false);
        addBtn.disabled = false;
        removeBtns.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-0', 'cursor-default');
        });
    } else {
        wrapper.classList.add('opacity-50', 'pointer-events-none');
        inputs.forEach(input => input.disabled = true);
        addBtn.disabled = true;
        removeBtns.forEach(btn => {
            btn.disabled = true;
            btn.classList.add('opacity-0', 'cursor-default');
        });
    }
});

// Add Tag Row
document.getElementById('addTagRowBtn').addEventListener('click', function() {
    const container = document.getElementById('tagsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'tag-row grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-gray-200/50 pb-3 mb-3 relative';
    newRow.innerHTML = `
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Name</label>
            <input type="text" name="tag_name[]" placeholder="e.g. VIP" class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tag Details</label>
            <input type="text" name="tag_details[]" placeholder="e.g. Front row seating" class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Amount paid</label>
            <div class="flex items-center gap-2">
                <input type="number" name="tag_amount[]" step="0.01" min="0" placeholder="0.00" class="tag-input w-full rounded-xl px-3 py-2 bg-white/80 border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 text-gray-800">
                <button type="button" class="remove-tag-btn text-red-500 hover:text-red-700 transition" onclick="removeTagRow(this)"><i class="fa fa-trash"></i></button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
});

// Remove Tag Row
function removeTagRow(btn) {
    const row = btn.closest('.tag-row');
    const container = document.getElementById('tagsContainer');
    if (container.querySelectorAll('.tag-row').length > 1) {
        row.remove();
    }
}
</script>

</body>
</html>