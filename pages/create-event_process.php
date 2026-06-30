<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// ============================================================
// AUTHENTICATION & VALIDATION
// ============================================================

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/create-event.php');
}

$user = getCurrentUser();
if ($user['user_validity'] !== 'Verified') {
    errorMsg("Please verify your account to access this page.");
    redirect('pages/update-profile.php');
}

$user_id = getCurrentUserId();
$current_step = (int)($_POST['current_step'] ?? 1);
$event_id = clean($_POST['event_id'] ?? '');

try {
    $pdo->beginTransaction();

    // ============================================================
    // STEP 1: Event Basics
    // ============================================================
    if ($current_step === 1) {
        $event_type = clean($_POST['event_type'] ?? 'Public');
        $event_category = clean($_POST['event_category'] ?? '');

        if (empty($event_category)) {
            throw new Exception('Please select an event category.');
        }

        if (empty($event_id)) {
            // Insert new event with 'Created' status
            $stmt = $pdo->prepare("
                INSERT INTO event_basic_info 
                (host_id, event_type, event_category, event_activeness, participation_fee) 
                VALUES (?, ?, ?, 'Created', 'Absent')
            ");
            $stmt->execute([$user_id, $event_type, $event_category]);
            $event_id = $pdo->lastInsertId();
        } else {
            // Update existing event
            $stmt = $pdo->prepare("
                UPDATE event_basic_info 
                SET event_type = ?, event_category = ? 
                WHERE event_id = ? AND host_id = ?
            ");
            $stmt->execute([$event_type, $event_category, $event_id, $user_id]);
        }

        $pdo->commit();
        successMsg("Step 1 saved successfully!");
        redirect("pages/create-event.php?step=2&event_id=" . urlencode($event_id));
    }

    // ============================================================
    // STEP 2: Event Details
    // ============================================================
    if ($current_step === 2) {
        if (empty($event_id)) {
            throw new Exception('Missing event ID. Please start over.');
        }

        // Get form data
        $event_title = clean($_POST['event_title'] ?? '');
        $event_extra_detail = clean($_POST['event_extra_detail'] ?? '');
        $event_date = clean($_POST['event_date'] ?? '');
        $event_time = clean($_POST['event_time'] ?? '');
        $event_tickets = max(1, (int)($_POST['event_tickets'] ?? 100));
        $event_ad_media = clean($_POST['event_ad_media'] ?? 'Image');
        $participation_fee = clean($_POST['participation_fee'] ?? 'Absent');
        $duration = max(1, (int)($_POST['event_duration'] ?? 1));

        // Validate required fields
        if (empty($event_title) || empty($event_date) || empty($event_time)) {
            throw new Exception('Event Title, Date, and Time are required.');
        }

        // Validate date is at least 1 day from now
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $selected = new DateTime($event_date);
        $diff = $now->diff($selected)->days;
        if ($selected < $now || $diff < 1) {
            throw new Exception('Event date must be at least 1 day from today.');
        }

        // Calculate termination date
        $term_date = new DateTime($event_date);
        $term_date->modify('+' . ($duration - 1) . ' days');
        $termination_date = $term_date->format('Y-m-d');
        $termination_time = $event_time;

        // Update event_basic_info
        $stmt = $pdo->prepare("
            UPDATE event_basic_info SET 
                event_title = ?,
                event_extra_detail = ?,
                event_date = ?,
                event_time = ?,
                termination_date = ?,
                termination_time = ?,
                event_tickets = ?,
                event_ad_media = ?,
                participation_fee = ?
            WHERE event_id = ? AND host_id = ?
        ");
        $stmt->execute([
            $event_title, $event_extra_detail, $event_date, $event_time,
            $termination_date, $termination_time, $event_tickets,
            $event_ad_media, $participation_fee, $event_id, $user_id
        ]);

        // ============================================================
        // MEDIA UPLOAD (per Blueprint: 4 images OR 1 video)
        // ============================================================
        if (isset($_FILES['ad_media']) && !empty($_FILES['ad_media']['name'][0])) {
            $upload_dir = '../uploads/events/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Clear previous media for this event
            $pdo->prepare("DELETE FROM event_ad_images WHERE event_id = ?")->execute([$event_id]);
            $pdo->prepare("DELETE FROM event_ad_video WHERE event_id = ?")->execute([$event_id]);

            $files = $_FILES['ad_media'];
            $image_paths = [];
            $video_path = null;

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    error_log("Upload error for file {$files['name'][$i]}: code {$files['error'][$i]}");
                    continue;
                }

                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $new_name = 'EVENT-MEDIA-' . uniqid() . '-' . time() . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $db_path = 'uploads/events/' . $new_name;
                    error_log("File uploaded successfully: $db_path");

                    // Determine file type using MIME content type (more reliable than extension alone)
                    $mime_type = mime_content_type($destination);
                    $is_image = (strpos($mime_type, 'image/') === 0);
                    $is_video = (strpos($mime_type, 'video/') === 0);

                    // Fallback: check extension if MIME detection fails or returns unexpected type
                    if ($mime_type === false || (!$is_image && !$is_video)) {
                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif', 'svg', 'avif', 'heic', 'heif', 'jfif', 'ico']);
                        $is_video = in_array($ext, ['mp4', 'mkv', 'webm', 'avi', 'mov', 'flv', 'wmv', 'ogv']);
                    }

                    if ($is_image) {
                        if (count($image_paths) < 4) {
                            $image_paths[] = $db_path;
                            error_log("Added image path: $db_path (total: " . count($image_paths) . ")");
                        }
                    } elseif ($is_video) {
                        $video_path = $db_path;
                        error_log("Added video path: $db_path");
                    } else {
                        error_log("File {$files['name'][$i]} could not be classified as image or video (MIME: $mime_type, ext: $ext)");
                    }
                } else {
                    error_log("Failed to move uploaded file: {$files['tmp_name'][$i]} to $destination");
                }
            }

            error_log("Total images to save: " . count($image_paths));
            error_log("Image paths: " . print_r($image_paths, true));

            // Save Images (up to 4) into image_a, image_b, image_c, image_d
            // Use empty string '' for unfilled slots to comply with NOT NULL columns
            if (!empty($image_paths)) {
                $vals = array_pad($image_paths, 4, '');
                try {
                    // First, delete any existing record for this event to avoid duplicates
                    $delStmt = $pdo->prepare("DELETE FROM event_ad_images WHERE event_id = ?");
                    $delStmt->execute([$event_id]);
                    
                    $imgStmt = $pdo->prepare("
                        INSERT INTO event_ad_images 
                        (event_id, image_a, image_b, image_c, image_d, images_upload_date, images_upload_time) 
                        VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())
                    ");
                    $result = $imgStmt->execute([$event_id, $vals[0], $vals[1], $vals[2], $vals[3]]);
                    error_log("Image insert result: " . ($result ? 'SUCCESS' : 'FAILED'));
                    error_log("Inserted values: event_id=$event_id, image_a={$vals[0]}, image_b={$vals[1]}, image_c={$vals[2]}, image_d={$vals[3]}");
                    
                    if (!$result) {
                        error_log("PDO Error Info: " . print_r($imgStmt->errorInfo(), true));
                        throw new Exception("Failed to insert image paths into database");
                    }
                } catch (Exception $e) {
                    error_log("Image insert error: " . $e->getMessage());
                    throw $e;
                }
            } else {
                error_log("No images to insert - image_paths array is empty");
            }

            // Save Video
            if ($video_path) {
                try {
                    $vidStmt = $pdo->prepare("
                        INSERT INTO event_ad_video 
                        (event_id, video_uploaded, video_upload_date, video_upload_time) 
                        VALUES (?, ?, CURDATE(), CURTIME())
                    ");
                    $vidStmt->execute([$event_id, $video_path]);
                    error_log("Video inserted successfully: $video_path");
                } catch (Exception $e) {
                    error_log("Video insert error: " . $e->getMessage());
                    throw $e;
                }
            }
        } else {
            error_log("No files uploaded or empty file array");
        }

        $pdo->commit();
        successMsg("Event details saved successfully!");
        redirect("pages/create-event.php?step=3&event_id=" . urlencode($event_id));
    }

    // ============================================================
    // STEP 3: Entry Fee & Participation Options
    // ============================================================
    if ($current_step === 3) {
        if (empty($event_id)) {
            throw new Exception('Missing event ID. Please start over.');
        }

        $participation_fee = clean($_POST['participation_fee'] ?? 'Absent');
        $feeOn = ($participation_fee === 'Present');

        // Update participation_fee in event_basic_info
        $stmt = $pdo->prepare("UPDATE event_basic_info SET participation_fee = ? WHERE event_id = ? AND host_id = ?");
        $stmt->execute([$participation_fee, $event_id, $user_id]);

        // If fee is ON, process participation types
        if ($feeOn) {
            $types = $_POST['participation_type'] ?? [];
            $badges = $_POST['participation_badge'] ?? [];
            $amounts = $_POST['participation_amount'] ?? [];

            // Delete existing participation fundraise & tags for this event
            $existingFund = $pdo->prepare("SELECT fundraise_id FROM event_fundraise_info WHERE event_id = ? AND fundraise_type = 'Contribution'");
            $existingFund->execute([$event_id]);
            $fundraise = $existingFund->fetch();

            if ($fundraise) {
                // Delete existing tags
                $pdo->prepare("DELETE FROM event_fundraise_tags WHERE event_id = ? AND fundraise_id = ?")->execute([$event_id, $fundraise['fundraise_id']]);
                // Delete existing fundraise
                $pdo->prepare("DELETE FROM event_fundraise_info WHERE event_id = ? AND fundraise_type = 'Contribution'")->execute([$event_id]);
            }

            // Create new fundraise_info (per Blueprint)
            $eventTitle = '';
            $titleStmt = $pdo->prepare("SELECT event_title FROM event_basic_info WHERE event_id = ?");
            $titleStmt->execute([$event_id]);
            $evt = $titleStmt->fetch();
            if ($evt) {
                $eventTitle = $evt['event_title'];
            }

            $fundraise_id = 'EEMS-EFI-' . strtoupper(bin2hex(random_bytes(4)));

            $fundStmt = $pdo->prepare("
                INSERT INTO event_fundraise_info 
                (fundraise_id, event_id, fundraise_title, fundraise_type, fundraise_category, fundraise_duration, fundraise_status, collected_amount, required_amount, spent_amount) 
                VALUES (?, ?, ?, 'Contribution', 'Unlimited', 'Pre-event', 'Active', 0, 0, 0)
            ");
            $fundStmt->execute([
                $fundraise_id,
                $event_id,
                "The participation contribution for {$eventTitle}"
            ]);

            // Update booking_fundraise_id in event_basic_info
            $pdo->prepare("UPDATE event_basic_info SET booking_fundraise_id = ? WHERE event_id = ?")->execute([$fundraise_id, $event_id]);

            // Insert fundraise tags
            $participant_count_map = [
                'Single' => 1, 'Double' => 2, 'Triple' => 3, 'Quad' => 4, 'Quint' => 5,
                'Sextuple' => 6, 'Septuple' => 7, 'Octuple' => 8, 'Nonuple' => 9, 'Decuple' => 10
            ];

            for ($i = 0; $i < count($types); $i++) {
                $type = clean($types[$i]);
                $badge = clean($badges[$i]);
                $amount = (float)($amounts[$i] ?? 0);

                if (empty($type) || empty($badge) || $amount <= 0) continue;

                $tag_id = 'EEMS-EFT-' . strtoupper(bin2hex(random_bytes(4)));
                $participant_count = $participant_count_map[$type] ?? 1;

                $tagStmt = $pdo->prepare("
                    INSERT INTO event_fundraise_tags 
                    (fundraise_tag_id, event_id, fundraise_id, tag_name, tag_details, required_amount, participant_count, tag_validity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Valid')
                ");
                $tagStmt->execute([$tag_id, $event_id, $fundraise_id, $type, $badge, $amount, $participant_count]);
            }
        } else {
            // Fee is OFF - ensure booking_fundraise_id is cleared and any existing contribution fundraise is removed
            $existingFund = $pdo->prepare("SELECT fundraise_id FROM event_fundraise_info WHERE event_id = ? AND fundraise_type = 'Contribution'");
            $existingFund->execute([$event_id]);
            $fundraise = $existingFund->fetch();
            if ($fundraise) {
                $pdo->prepare("DELETE FROM event_fundraise_tags WHERE event_id = ? AND fundraise_id = ?")->execute([$event_id, $fundraise['fundraise_id']]);
                $pdo->prepare("DELETE FROM event_fundraise_info WHERE event_id = ? AND fundraise_type = 'Contribution'")->execute([$event_id]);
            }
            $pdo->prepare("UPDATE event_basic_info SET booking_fundraise_id = NULL WHERE event_id = ?")->execute([$event_id]);
        }

        $pdo->commit();
        successMsg("Participation options saved successfully!");
        redirect("pages/create-event.php?step=4&event_id=" . urlencode($event_id));
    }

    // ============================================================
    // STEP 4: Venue Selection & FINAL SUBMISSION
    // ============================================================
    if ($current_step === 4) {
        if (empty($event_id)) {
            throw new Exception('Missing event ID. Please start over.');
        }

        // ============================================================
        // Process Venue Rentals (if any)
        // ============================================================
        $venue_rentals_json = $_POST['venue_rentals'] ?? '';
        if (!empty($venue_rentals_json)) {
            $rentals = json_decode($venue_rentals_json, true);
            
            if (is_array($rentals) && !empty($rentals)) {
                foreach ($rentals as $rental) {
                    $asset_id = clean($rental['asset_id'] ?? '');
                    $action = clean($rental['action'] ?? 'order');
                    $renting_price = (float)($rental['renting_price'] ?? 0);
                    $rented_quantity = (int)($rental['rented_quantity'] ?? 1);
                    $total_price = (float)($rental['total_renting_price'] ?? 0);

                    if (empty($asset_id) || $renting_price <= 0 || $rented_quantity <= 0) continue;

                    // Generate rental_id
                    $rental_id = 'RENT-' . strtoupper(bin2hex(random_bytes(5)));

                    // Determine renting_status based on action
                    $renting_status = ($action === 'negotiate') ? 'Pleaded' : 'Requested';

                    $rentStmt = $pdo->prepare("
                        INSERT INTO event_asset_rentals 
                        (rental_id, event_id, asset_id, renting_price, total_renting_price, rented_quantity, renting_date, renting_time, renting_status, lending_status) 
                        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'Pending')
                    ");
                    $rentStmt->execute([
                        $rental_id, $event_id, $asset_id, $renting_price, $total_price, $rented_quantity, $renting_status
                    ]);
                }
            }
        }

        // ============================================================
        // BACKGROUND PROCESSES (per Blueprint)
        // ============================================================
        
        // 1. Add the host to event_invitees with 'Host' badge
        $invitee_id = 'EEMS-INVI-' . strtoupper(bin2hex(random_bytes(5)));
        $invStmt = $pdo->prepare("
            INSERT INTO event_invitees 
            (invitee_id, event_id, user_id, attendance_status, invitation_badge, invitation_position, invitation_category, attendance_date, attendance_time) 
            VALUES (?, ?, ?, 'Confirmed', 'Host', 'Main Host', 'Non-paying', CURDATE(), CURTIME())
        ");
        $invStmt->execute([$invitee_id, $event_id, $user_id]);

        // 2. Create event_chatroom entry with 'Group' type
        $chatStmt = $pdo->prepare("INSERT INTO event_chatroom (event_id, chat_type) VALUES (?, 'Group')");
        $chatStmt->execute([$event_id]);
        $groupchat_id = $pdo->lastInsertId();

        // Update groupchat_id in event_basic_info
        $pdo->prepare("UPDATE event_basic_info SET groupchat_id = ? WHERE event_id = ?")->execute([$groupchat_id, $event_id]);

        // 3. Create Event Budgeting Pocket (Post-event fundraise)
        $pocket_id = 'EEMS-EFI-' . strtoupper(bin2hex(random_bytes(4)));
        $pocketStmt = $pdo->prepare("
            INSERT INTO event_fundraise_info 
            (fundraise_id, event_id, fundraise_title, fundraise_type, fundraise_category, fundraise_duration, fundraise_status, collected_amount, required_amount, spent_amount) 
            VALUES (?, ?, 'Event Budgeting Pocket', 'Donation', 'Unlimited', 'Post-event', 'Compiled', 0, 0, 0)
        ");
        $pocketStmt->execute([$pocket_id, $event_id]);

        // Update pocket_id in event_basic_info
        $pdo->prepare("UPDATE event_basic_info SET pocket_id = ? WHERE event_id = ?")->execute([$pocket_id, $event_id]);

        // Ensure event_activeness is 'Created'
        $pdo->prepare("UPDATE event_basic_info SET event_activeness = 'Created' WHERE event_id = ?")->execute([$event_id]);

        $pdo->commit();
        successMsg("✅ Event created successfully! Please wait for responses from venue owners.");
        redirect("pages/events.php");
    }

    // If we get here, something went wrong
    throw new Exception('Invalid step or missing data.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    errorMsg('Error: ' . $e->getMessage());
    redirect('pages/create-event.php?step=' . $current_step . ($event_id ? '&event_id=' . urlencode($event_id) : ''));
}
?>