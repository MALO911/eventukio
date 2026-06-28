<?php
/**
 * EVENTUKIO - HELPER FUNCTIONS
 */

require_once __DIR__ . '/config.php';

// ====================== PASSWORD SECURITY ======================
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hashed) {
    return password_verify($password, $hashed);
}

// ====================== USER SESSION HELPERS ======================
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser() {
    global $pdo;
    $user_id = getCurrentUserId();
    if (!$user_id) return null;

    $stmt = $pdo->prepare("SELECT * FROM user_basic_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getPageNameFromUrl($url) {
    if (empty($url)) {
        return 'Unknown page';
    }
    $parsed = parse_url($url);
    if (empty($parsed['path'])) {
        return 'Unknown page';
    }
    $page = basename($parsed['path']);
    $map = [
        'events.php' => 'Events page',
        'home.php' => 'Home page',
        'event-page.php' => 'Event page',
        'account.php' => 'Account page',
        'create-event.php' => 'Create Event page',
        'login.php' => 'Login page',
        'register.php' => 'Register page',
        'manage-event.php' => 'Manage Event page',
        'attend_events.php' => 'Attend Events page',
        'update_profile.php' => 'Update Profile page',
        'ticket_booking.php' => 'Ticket Booking page',
        'notifications.php' => 'Notifications page'
    ];
    return $map[$page] ?? ucfirst(str_replace(['-', '_', '.php'], [' ', ' ', ''], $page));
}

function ensureUserUsageTrackTable() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_usage_track (
            track_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(25) NOT NULL,
            page_exited VARCHAR(100) NOT NULL,
            page_headed VARCHAR(100) NOT NULL,
            tracking_datetime DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        // If table creation fails, do not interrupt page flow.
    }
}

function trackPageUsage($page_exited, $page_headed) {
    global $pdo;
    $user_id = getCurrentUserId();
    if (!$user_id) {
        return;
    }
    ensureUserUsageTrackTable();
    try {
        $stmt = $pdo->prepare("INSERT INTO user_usage_track (user_id, page_exited, page_headed, tracking_datetime) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $page_exited, $page_headed]);
    } catch (Exception $e) {
        // Swallow tracking errors so the form still works.
    }
}

function generateUniqueId($prefix = '') {
    try {
        return $prefix . strtoupper(bin2hex(random_bytes(6)));
    } catch (Exception $e) {
        return $prefix . uniqid();
    }
}

function generateFundraiseId() {
    return generateUniqueId('FUND-');
}

function generateFundraiseTagId() {
    return generateUniqueId('FTAG-');
}

function generateRentalId() {
    return generateUniqueId('RENT-');
}

function generateInviteeId() {
    return generateUniqueId('INV-');
}

function generateHireId() {
    return generateUniqueId('HIRE-');
}

function generateFundingId() {
    return generateUniqueId('FUND-');
}

function generateTransactionId() {
    return generateUniqueId('TXN-');
}

// ====================== DATE & TIME HELPERS ======================
function formatEventDate($date, $time) {
    return date("d F Y", strtotime($date)) . " at " . date("H:i", strtotime($time));
}

function isEventActive($event) {
    // Simple check - full logic will be in event page
    return $event['event_activeness'] === 'Announced' || $event['event_activeness'] === 'In Session';
}

// ====================== CHAT ENCRYPTION (OpenSSL) ======================
function encryptMessage($message) {
    $key = openssl_random_pseudo_bytes(32); // In real production, use a stored per-event key
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($message, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted); // Store this in DB
}

function decryptMessage($encryptedData) {
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    // Use stored key in real implementation
    $key = "EventukioDefaultKeyForDev2026"; // Replace with proper key management
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

// ====================== FILE UPLOAD HELPER ======================
function uploadFile($file, $targetDir, $allowedTypes = ['jpg','png','jpeg','mp4','mkv']) {
    $fileName = basename($file["name"]);
    $targetFile = $targetDir . uniqid() . "_" . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if (!in_array($fileType, $allowedTypes)) {
        return ["success" => false, "message" => "Invalid file type"];
    }

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["success" => true, "path" => str_replace(__DIR__ . '/../', '', $targetFile)];
    }
    return ["success" => false, "message" => "Upload failed"];
}

function commandExists($command) {
    $escaped = escapeshellarg($command);
    $check = shell_exec("where $escaped 2>NUL");
    if (!empty($check)) {
        return true;
    }
    $check = shell_exec("which $escaped 2>/dev/null");
    return !empty($check);
}

function getVideoDurationSeconds($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }

    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath) . " 2>&1";
    $output = shell_exec($cmd);
    if ($output === null) {
        return false;
    }

    $duration = floatval(trim($output));
    return $duration > 0 ? $duration : false;
}

function processVideoUpload($relativePath, $maxSeconds = 60) {
    $absolutePath = __DIR__ . '/../' . $relativePath;
    if (!file_exists($absolutePath)) {
        return ["success" => false, "message" => "Video file not found."];
    }

    if (!commandExists('ffmpeg') || !commandExists('ffprobe')) {
        return ["success" => false, "message" => "Video processing is unavailable on this server."];
    }

    $duration = getVideoDurationSeconds($absolutePath);
    if ($duration === false) {
        return ["success" => false, "message" => "Unable to determine video duration."];
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $needsConversion = $extension !== 'mp4';
    $needsTrimming = $duration > $maxSeconds;

    if (!$needsConversion && !$needsTrimming) {
        return ["success" => true, "path" => $relativePath];
    }

    $outputPath = dirname($absolutePath) . DIRECTORY_SEPARATOR . pathinfo($absolutePath, PATHINFO_FILENAME) . '_processed_' . uniqid() . '.mp4';
    $trimArg = $needsTrimming ? ' -t ' . intval($maxSeconds) : '';
    $cmd = "ffmpeg -y -i " . escapeshellarg($absolutePath) . $trimArg . " -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k " . escapeshellarg($outputPath) . " 2>&1";
    $output = shell_exec($cmd);

    if (!file_exists($outputPath) || filesize($outputPath) === 0) {
        return ["success" => false, "message" => "Video processing failed."];
    }

    @unlink($absolutePath);
    return ["success" => true, "path" => str_replace(__DIR__ . '/../', '', $outputPath)];
}

// ====================== TRANSLATION HELPER (i18next) ======================
function getUserLanguage() {
    // Check session first
    if (isset($_SESSION['user_language'])) {
        return $_SESSION['user_language'];
    }

    // Check database if user is logged in
    $user = getCurrentUser();
    if ($user && isset($user['user_language'])) {
        $_SESSION['user_language'] = $user['user_language'];
        return $user['user_language'];
    }

    // Default to English
    return 'en';
}

function setUserLanguage($lang) {
    $_SESSION['user_language'] = $lang;

    // Update database if user is logged in
    $user = getCurrentUser();
    if ($user) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE user_basic_info SET user_language = ? WHERE user_id = ?");
        $stmt->execute([$lang, $user['user_id']]);
    }
}

function t($key) {
    // This will be replaced with proper i18next JS integration
    // For now, simple fallback - this function is for server-side use only
    // Client-side translations will use i18next JavaScript
    return $key;
}

// ====================== ROLE CHECK HELPERS ======================
function isEventHost($event_id, $user_id = null) {
    global $pdo;
    if (!$user_id) $user_id = getCurrentUserId();
    
    $stmt = $pdo->prepare("SELECT host_id FROM event_basic_info WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $result = $stmt->fetch();
    return $result && $result['host_id'] === $user_id;
}

// ====================== SUCCESS / ERROR MESSAGES ======================
function successMsg($msg) {
    $_SESSION['success'] = $msg;
}

function errorMsg($msg) {
    $_SESSION['error'] = $msg;
}

// ====================== REDIRECT WITH MESSAGE ======================
function redirectWith($page, $type = 'success', $message = '') {
    if ($type === 'success') successMsg($message);
    else errorMsg($message);
    redirect($page);
}

// ====================== GENERATE USER ID ======================
function generateUserId() {
    $random = strtoupper(bin2hex(random_bytes(8)));
    return "EEMS-USER-" . $random;
}

function generateEventId() {
    return rand(100000, 999999); // Will be replaced with proper logic
}

function getProfilePictureUrl($picture, $default = '../assets/images/default.png', $format = 'relative') {
    if ($format === 'absolute' && $default === '../assets/images/default.png') {
        $default = BASE_URL . 'assets/images/default.png';
    } elseif ($format === 'root' && $default === '../assets/images/default.png') {
        $default = 'assets/images/default.png';
    }

    if (empty($picture)) {
        return $default;
    }
    $filename = basename(str_replace('\\', '/', $picture));
    if ($filename === '' || !file_exists(PROFILE_DIR . $filename)) {
        return $default;
    }

    switch ($format) {
        case 'absolute':
            return BASE_URL . 'uploads/profiles/' . rawurlencode($filename);
        case 'root':
            return 'uploads/profiles/' . rawurlencode($filename);
        default:
            return '../uploads/profiles/' . rawurlencode($filename);
    }
}

// Auto-load important files if needed
// require_once __DIR__ . '/../includes/auth.php';
?>