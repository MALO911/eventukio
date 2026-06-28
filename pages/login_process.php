<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = clean($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        errorMsg("Email/Phone and Password are required");
        redirect('pages/login.php');
    }

    try {
        // Check by email or phone number
        $stmt = $pdo->prepare("SELECT * FROM user_basic_info 
                              WHERE (user_email = ? OR user_phone_number = ?) 
                              LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['user_password'])) {
            
            // Check validity status
            if (!in_array($user['user_validity'], ['Registered', 'Verified'])) {
                errorMsg("Your account is not active. Please contact support.");
                redirect('pages/login.php');
            }

            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_full_name'] = $user['user_full_name'];
            $_SESSION['user_type'] = $user['user_type'];

            // Log usage (optional)
            successMsg("Welcome back, " . $user['user_full_name'] . "!");
            
            // Redirect to Events Page as per Blueprint
            redirect('pages/events.php');

        } else {
            errorMsg("Invalid email/phone or password");
            redirect('pages/login.php');
        }

    } catch (PDOException $e) {
        errorMsg("System error. Please try again later.");
        redirect('pages/login.php');
    }
} else {
    // Direct access not allowed
    redirect('pages/login.php');
}
?>