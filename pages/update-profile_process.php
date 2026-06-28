<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();
$user = getCurrentUser();

try {
    $pdo->beginTransaction();

    $current_step = (int) ($_POST['current_step'] ?? 4);

    if ($current_step === 1) {
        // === BASIC INFO (Step 1) ===
        $full_name = clean($_POST['user_full_name'] ?? $user['user_full_name']);
        $email     = clean($_POST['user_email'] ?? $user['user_email']);
        $phone     = clean($_POST['user_phone_number'] ?? $user['user_phone_number']);
        $gender    = clean($_POST['user_gender'] ?? $user['user_gender'] ?? 'None');
        $bio       = clean($_POST['user_bio'] ?? $user['user_bio'] ?? '');

        $profile_picture = $user['user_profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $upload = uploadFile($_FILES['profile_picture'], PROFILE_DIR, ['jpg','jpeg','png','gif']);
            if ($upload['success']) {
                $profile_picture = $upload['path'];
            }
        }

        $stmt = $pdo->prepare("UPDATE user_basic_info 
            SET user_full_name = ?, user_email = ?, user_phone_number = ?, 
                user_gender = ?, user_bio = ?, user_profile_picture = ? 
            WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $phone, $gender, $bio, $profile_picture, $user_id]);

        $validity = !empty($profile_picture) ? 'Verified' : 'Registered';
        $pdo->prepare("UPDATE user_basic_info SET user_validity = ? WHERE user_id = ?")
            ->execute([$validity, $user_id]);
    }

    if ($current_step === 2) {
        // === RESIDENCE (Step 2) ===
        $home_region   = clean($_POST['home_region'] ?? '');
        $home_district = clean($_POST['home_district'] ?? '');
        $home_street   = clean($_POST['home_street'] ?? '');
        $pdo->prepare("UPDATE user_basic_info SET home_region = ?, home_district = ?, home_street = ? WHERE user_id = ?")
             ->execute([$home_region, $home_district, $home_street, $user_id]);

        // === JOBS (Step 2) ===
        $job_categories = $_POST['job_category'] ?? [];
        $job_titles     = $_POST['job_title'] ?? [];
        if (!empty($job_categories)) {
            $job_stmt = $pdo->prepare("INSERT INTO user_event_jobs 
                (profile_id, user_id, profession_category, profession_title, job_status) 
                VALUES (?, ?, ?, ?, 'Valid')");
            foreach ($job_categories as $i => $cat) {
                if (!empty($cat) && !empty($job_titles[$i])) {
                    $profile_id = generateUniqueId('JOB-');
                    $job_stmt->execute([$profile_id, $user_id, $cat, $job_titles[$i]]);
                }
            }
        }
    }

    if ($current_step === 3) {
        // === ASSETS (Step 3) ===
        $asset_categories = $_POST['asset_category'] ?? [];
        if (!empty($asset_categories)) {
            $asset_stmt = $pdo->prepare("INSERT INTO user_event_asset 
                (asset_id, owner_id, asset_category, asset_name, asset_quality, asset_quantity, 
                 asset_region, asset_district, asset_street, asset_location_specifics, 
                 asset_price, asset_status, asset_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', 'Rental')");
            foreach ($asset_categories as $i => $cat) {
                if (!empty($cat)) {
                    $asset_id = generateUniqueId('ASSET-');
                    $asset_stmt->execute([
                        $asset_id,
                        $user_id,
                        $cat,
                        $_POST['asset_name'][$i] ?? '',
                        $_POST['asset_quality'][$i] ?? '',
                        (int)($_POST['asset_quantity'][$i] ?? 0),
                        $_POST['asset_region'][$i] ?? '',
                        $_POST['asset_district'][$i] ?? '',
                        $_POST['asset_street'][$i] ?? '',
                        $_POST['asset_location_specifics'][$i] ?? '',
                        (float)($_POST['asset_price'][$i] ?? 0)
                    ]);
                }
            }
        }
    }

    if ($current_step === 4) {
        // === GATEWAYS (Step 4) ===
        $gw_methods = $_POST['gateway_method'] ?? [];
        if (!empty($gw_methods)) {
            // Ensure user has a wallet/account_id
            $wallet_stmt = $pdo->prepare("SELECT account_id FROM user_wallet_info WHERE user_id = ?");
            $wallet_stmt->execute([$user_id]);
            $wallet = $wallet_stmt->fetch();
            if (!$wallet) {
                $account_id = generateUniqueId('WALLET-');
                $pdo->prepare("INSERT INTO user_wallet_info (account_id, user_id, account_balance, gateways_number, account_activity) VALUES (?, ?, 0, 0, 'Active')")
                    ->execute([$account_id, $user_id]);
            } else {
                $account_id = $wallet['account_id'];
            }

            $gw_stmt = $pdo->prepare("INSERT INTO user_gateway_info 
                (gateway_id, user_id, account_id, creation_date, gateway_method, gateway_account_number, gateway_brand, account_status)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'Active')");
            foreach ($gw_methods as $i => $method) {
                if (!empty($method)) {
                    $gateway_id = generateUniqueId('GW-');
                    $gw_stmt->execute([
                        $gateway_id,
                        $user_id,
                        $account_id,
                        $method,
                        $_POST['gateway_account_number'][$i] ?? '',
                        $_POST['gateway_brand'][$i] ?? ''
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    $formAction = $_POST['form_submit_action'] ?? 'submit';
    if ($formAction === 'next' && $current_step >= 1 && $current_step < 4) {
        $nextStep = $current_step + 1;
        successMsg("✅ Profile updated successfully!");
        redirect("pages/update-profile.php?step={$nextStep}");
    }

    successMsg("✅ Profile updated successfully!");
    redirect('pages/account.php');

} catch (Exception $e) {
    $pdo->rollBack();
    errorMsg("❌ Update failed: " . $e->getMessage());
    redirect('pages/update-profile.php');
}
?>