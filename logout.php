<?php
require_once 'config/config.php';

// Destroy all session data
session_unset();
session_destroy();

// Clear any cookies if used
setcookie(session_name(), '', time() - 3600, '/');

// Redirect to home with success message
$_SESSION['success'] = "You have been logged out successfully.";
header("Location: " . BASE_URL . "pages/home.php");
exit();
?>