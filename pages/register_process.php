<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/register.php');
}

try {
    $account_type = clean($_POST['account_type'] ?? 'Personal');
    $language     = clean($_POST['language'] ?? 'en');

    $email        = clean($_POST['email'] ?? '');
    $phone        = clean($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $national_id  = clean($_POST['national_id'] ?? '');
    $recovery_phone = clean($_POST['recovery_phone'] ?? '');

    // Build full_name and set birthdate
    $birth_date = null;
    if ($account_type === 'Personal') {
        $first_name = clean($_POST['first_name'] ?? '');
        $surname    = clean($_POST['surname'] ?? '');
        $full_name  = trim($first_name . ' ' . $surname);
        $birth_date = clean($_POST['birth_date'] ?? '');
    } else {
        $full_name = clean($_POST['full_name'] ?? '');
    }

    // Validation
    if (empty($email) || empty($phone) || empty($full_name) || empty($password) || $password !== $confirm_pass) {
        errorMsg("Please fill all required fields correctly.");
        redirect('pages/register.php');
    }

    if ($account_type === 'Personal') {
        $birth_date = clean($_POST['birth_date'] ?? '');
        if (empty($birth_date) || empty($national_id)) {
            errorMsg("Birth date and NIDA are required for Personal accounts.");
            redirect('pages/register.php');
        }

        if (!preg_match('/^(\d{8})-(\d{5})-(\d{5})-(\d{2})$/', $national_id, $parts)) {
            errorMsg("NIDA must use the format 12345678-12345-12345-12.");
            redirect('pages/register.php');
        }

        $dobDigits = $parts[1];
        $zip_part = $parts[2];
        $sequence = $parts[3];
        $year = (int) substr($dobDigits, 0, 4);
        $month = (int) substr($dobDigits, 4, 2);
        $day = (int) substr($dobDigits, 6, 2);

        if (!checkdate($month, $day, $year)) {
            errorMsg("The birth date portion of NIDA is invalid.");
            redirect('pages/register.php');
        }

        if ($birth_date !== date('Y-m-d', strtotime($dobDigits))) {
            errorMsg("Birth date must match the date encoded in the NIDA number.");
            redirect('pages/register.php');
        }

        if (!preg_match('/^\d{5}$/', $zip_part)) {
            errorMsg("The NIDA ZIP section must contain exactly 5 digits.");
            redirect('pages/register.php');
        }

        if ((int)$sequence < 0 || (int)$sequence > 9) {
            errorMsg("The NIDA sequence must be between 00000 and 00009.");
            redirect('pages/register.php');
        }

        $check = $pdo->prepare("SELECT zip_code FROM zip_validation WHERE zip_code = ?");
        $check->execute([$zip_part]);
        if ($check->rowCount() == 0) {
            errorMsg("Invalid NIDA ZIP code segment.");
            redirect('pages/register.php');
        }
    }

    // Check duplicate email
    $check = $pdo->prepare("SELECT user_id FROM user_basic_info WHERE user_email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        errorMsg("Email already registered.");
        redirect('pages/register.php');
    }

    $user_id = generateUserId();
    $hashed  = hashPassword($password);

    // INSERT USER
    $stmt = $pdo->prepare("INSERT INTO user_basic_info 
        (user_id, user_full_name, user_type, user_email, user_phone_number, national_id, birthdate,
         recovery_phone_number, user_password, user_language, user_validity, registration_date_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Registered', NOW())");

    $stmt->execute([$user_id, $full_name, $account_type, $email, $phone, $national_id, $birth_date ?? '', $recovery_phone, $hashed, $language]);

    // Create Wallet
    $account_id = "WALLET-" . strtoupper(bin2hex(random_bytes(6)));
    $pdo->prepare("INSERT INTO user_wallet_info (account_id, user_id, account_balance) VALUES (?, ?, 0.00)")
         ->execute([$account_id, $user_id]);

    successMsg("✅ Account created successfully! Please login.");
        // Save registered credentials temporarily to prefill login form
        $_SESSION['registered_email'] = $email;
        // Storing plain password briefly to prefill login (will be cleared on next page)
        $_SESSION['registered_password'] = $password;
        redirect('pages/login.php');

} catch (Exception $e) {
    errorMsg("❌ Registration failed: " . $e->getMessage());
    redirect('pages/register.php');
}
?>