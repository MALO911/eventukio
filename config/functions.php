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
    static $cachedUser = null;
    static $queried = false;

    if ($queried) {
        return $cachedUser;
    }

    $user_id = getCurrentUserId();
    if (!$user_id) {
        $queried = true;
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM user_basic_info WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cachedUser = $stmt->fetch() ?: null;
    } catch (Exception $e) {
        $cachedUser = null;
    }

    $queried = true;
    return $cachedUser;
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
    global $translations, $lang;
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
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

// ====================== DYNAMIC THEMES ======================
function getThemePayload($theme) {
    $valid_themes = ['Oceanic Blue', 'Warm Glow', 'Luxe Jewel', 'Soft Pastel'];
    if (!in_array($theme, $valid_themes)) {
        $theme = 'Oceanic Blue';
    }

    $variables = '';
    $body_class = '';

    switch ($theme) {
        case 'Warm Glow':
            $variables = '
                --body-bg: #FF9A8B;
                --glass-bg: rgba(255, 248, 240, 0.15);
                --glass-border: rgba(255, 255, 255, 0.25);
                --glass-shadow: 0 8px 32px 0 rgba(232, 91, 117, 0.15);
                --text-normal: #2D2D2D;
                
                --color-indigo-50: #FF9A8B;
                --color-indigo-100: #FF8080;
                --color-indigo-200: #FF6B6B;
                --color-indigo-500: #FF8585;
                --color-indigo-600: #FF6B6B;
                --color-indigo-700: #FAF7F2;
                --color-indigo-800: #E5BD75;
                --color-indigo-900: #7A2828;
                
                --color-blue-50: #FF9A8B;
                --color-blue-100: #FF8080;
                --color-blue-200: #FF6B6B;
                --color-blue-500: #FF8585;
                --color-blue-600: #FF6B6B;
                --color-blue-700: #FAF7F2;
                --color-blue-900: #5C1D1D;
                
                --color-purple-900: #6B2222;
                
                --color-gray-100: #F3EFE9;
                --color-gray-200: #E5E5E5;
                --color-gray-300: #CCCCCC;
                --color-gray-400: #888888;
                --color-gray-500: #555555;
                --color-gray-600: #2D2D2D;
                --color-gray-700: #2D2D2D;
                --color-gray-800: #1A1A1A;
                --color-gray-900: #111111;
            ';
            $body_class = 'theme-warm-glow';
            break;

        case 'Luxe Jewel':
            $variables = '
                --body-bg: #2A1B4D;
                --glass-bg: rgba(30, 30, 50, 0.35);
                --glass-border: rgba(232, 185, 35, 0.3);
                --glass-shadow: 0 8px 32px 0 rgba(123, 77, 255, 0.25), 0 0 12px rgba(232, 185, 35, 0.15);
                --text-normal: #F0E6FF;
                
                --color-indigo-50: #2A1B4D;
                --color-indigo-100: #1E1238;
                --color-indigo-200: #150B28;
                --color-indigo-500: #9C6FFF;
                --color-indigo-600: #E8B923;
                --color-indigo-700: #F0E6FF;
                --color-indigo-800: #7B4DFF;
                --color-indigo-900: #150B28;
                
                --color-blue-50: #2A1B4D;
                --color-blue-100: #1E1238;
                --color-blue-200: #150B28;
                --color-blue-500: #9C6FFF;
                --color-blue-600: #E8B923;
                --color-blue-700: #F0E6FF;
                --color-blue-900: #150B28;
                
                --color-purple-900: #1E1238;
                
                --color-gray-100: #2A1B4D;
                --color-gray-200: #3D2D63;
                --color-gray-300: #4B397A;
                --color-gray-400: #888888;
                --color-gray-500: #CCCCCC;
                --color-gray-600: #F0E6FF;
                --color-gray-700: #F0E6FF;
                --color-gray-800: #FAF7F2;
                --color-gray-900: #FFFFFF;
            ';
            $body_class = 'theme-luxe-jewel';
            break;

        case 'Soft Pastel':
            $variables = '
                --body-bg: #FCE7F3;
                --glass-bg: rgba(255, 255, 255, 0.25);
                --glass-border: rgba(255, 255, 255, 0.45);
                --glass-shadow: 0 8px 32px 0 rgba(230, 230, 250, 0.2);
                --text-normal: #2D2D2D;
                
                --color-indigo-50: #FCE7F3;
                --color-indigo-100: #E6E6FA;
                --color-indigo-200: #D6D6F5;
                --color-indigo-500: #FF8FA3;
                --color-indigo-600: #FF6B9D;
                --color-indigo-700: #2D2D2D;
                --color-indigo-800: #4ECDC4;
                --color-indigo-900: #1A1A1A;
                
                --color-blue-50: #FCE7F3;
                --color-blue-100: #E6E6FA;
                --color-blue-200: #D6D6F5;
                --color-blue-500: #FF8FA3;
                --color-blue-600: #FF6B9D;
                --color-blue-700: #2D2D2D;
                --color-blue-900: #2A2A2A;
                
                --color-purple-900: #2D2D2D;
                
                --color-gray-100: #F3EFE9;
                --color-gray-200: #E5E5E5;
                --color-gray-300: #CCCCCC;
                --color-gray-400: #888888;
                --color-gray-500: #555555;
                --color-gray-600: #2D2D2D;
                --color-gray-700: #2D2D2D;
                --color-gray-800: #1A1A1A;
                --color-gray-900: #111111;
            ';
            $body_class = 'theme-soft-pastel';
            break;

        case 'Oceanic Blue':
        default:
            $variables = '
                --body-bg: #FAF7F2;
                --glass-bg: rgba(255, 248, 240, 0.75);
                --glass-border: rgba(44, 44, 122, 0.15);
                --glass-shadow: 0 8px 32px 0 rgba(44, 44, 122, 0.08);
                --text-normal: #1F1F1F;
                
                --color-indigo-50: #FAF7F2;
                --color-indigo-100: #F3EFE9;
                --color-indigo-200: #E8E3D7;
                --color-indigo-500: #33CCFF;
                --color-indigo-600: #00BFFF;
                --color-indigo-700: #2C2C7A;
                --color-indigo-800: #1E1E54;
                --color-indigo-900: #111130;
                
                --color-blue-50: #FAF7F2;
                --color-blue-100: #F3EFE9;
                --color-blue-200: #E8E3D7;
                --color-blue-500: #33CCFF;
                --color-blue-600: #00BFFF;
                --color-blue-700: #2C2C7A;
                --color-blue-900: #2C2C7A;
                
                --color-purple-900: #2C2C7A;
                
                --color-gray-100: #F3EFE9;
                --color-gray-200: #E5E5E5;
                --color-gray-300: #CCCCCC;
                --color-gray-400: #888888;
                --color-gray-500: #555555;
                --color-gray-600: #1F1F1F;
                --color-gray-700: #1F1F1F;
                --color-gray-800: #111111;
                --color-gray-900: #000000;
            ';
            $body_class = 'theme-oceanic-blue';
            break;
    }

    $styles = '
    <!-- Eventukio Theme Styles -->
    <style>
    :root {
        ' . $variables . '
    }
    body {
        background: var(--body-bg) !important;
        color: var(--text-normal) !important;
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    .glass {
        background: var(--glass-bg) !important;
        border: 1px solid var(--glass-border) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        box-shadow: var(--glass-shadow) !important;
        transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    /* Ensure solid background cards / modals retain dark readable text */
    .modal-box, .bg-white, [class*="bg-white"] {
        background-color: #ffffff !important;
        color: #1f1f1f !important;
    }
    .modal-box h1, .modal-box h2, .modal-box h3, .modal-box h4, .modal-box h5, .modal-box h6,
    .bg-white h1, .bg-white h2, .bg-white h3, .bg-white h4, .bg-white h5, .bg-white h6 {
        color: #1f1f1f !important;
    }
    .modal-box p, .bg-white p {
        color: #4b5563 !important;
    }
    .modal-box label, .bg-white label {
        color: #374151 !important;
    }
    .modal-box input, .modal-box select, .modal-box textarea,
    .bg-white input, .bg-white select, .bg-white textarea {
        color: #1f1f1f !important;
        border-color: #d1d5db !important;
    }
    
    /* Soft Pastel dots pattern background */
    body.theme-soft-pastel {
        background-color: var(--body-bg) !important;
        background-image: 
            radial-gradient(circle, rgba(255, 107, 157, 0.12) 8%, transparent 9%),
            radial-gradient(circle, rgba(78, 205, 196, 0.12) 8%, transparent 9%) !important;
        background-size: 32px 32px !important;
        background-position: 0 0, 16px 16px !important;
    }
    </style>
    ';

    $scripts = '
    <!-- Eventukio Tailwind configuration override script -->
    <script>
    (function() {
        // Apply body theme class
        document.addEventListener("DOMContentLoaded", function() {
            document.body.classList.add("' . $body_class . '");
        });
        
        // Define function to configure Tailwind dynamically if Tailwind CDN is loaded
        function configureTailwind() {
            if (typeof tailwind !== "undefined") {
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                indigo: {
                                    50: "var(--color-indigo-50)",
                                    100: "var(--color-indigo-100)",
                                    200: "var(--color-indigo-200)",
                                    500: "var(--color-indigo-500)",
                                    600: "var(--color-indigo-600)",
                                    700: "var(--color-indigo-700)",
                                    800: "var(--color-indigo-800)",
                                    900: "var(--color-indigo-900)"
                                },
                                blue: {
                                    50: "var(--color-blue-50)",
                                    100: "var(--color-blue-100)",
                                    200: "var(--color-blue-200)",
                                    500: "var(--color-blue-500)",
                                    600: "var(--color-blue-600)",
                                    700: "var(--color-blue-700)",
                                    900: "var(--color-blue-900)"
                                },
                                purple: {
                                    900: "var(--color-purple-900)"
                                },
                                gray: {
                                    100: "var(--color-gray-100)",
                                    200: "var(--color-gray-200)",
                                    300: "var(--color-gray-300)",
                                    400: "var(--color-gray-400)",
                                    500: "var(--color-gray-500)",
                                    600: "var(--color-gray-600)",
                                    700: "var(--color-gray-700)",
                                    800: "var(--color-gray-800)",
                                    900: "var(--color-gray-900)"
                                }
                            }
                        }
                    }
                };
            }
        }
        
        // Run Tailwind configuration
        configureTailwind();
    })();
    </script>
    ';

    return [
        'styles' => $styles,
        'scripts' => $scripts
    ];
}
?>