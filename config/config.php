<?php
/*** EVENTUKIO - CONFIGURATION FILE */

session_start();

// ====================== DATABASE CONFIG ======================
define('DB_HOST', 'localhost');
define('DB_NAME', 'eventukio');
define('DB_USER', 'root');           // Default for XAMPP
define('DB_PASS', '');               // Empty for XAMPP

// ====================== BASE URL ======================
define('BASE_URL', 'http://localhost/eventukio/');

// ====================== UPLOAD PATHS ======================
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PROFILE_DIR', UPLOAD_DIR . 'profiles/');
define('EVENT_MEDIA_DIR', UPLOAD_DIR . 'events/');
define('GALLERY_DIR', UPLOAD_DIR . 'gallery/');

// ====================== SECURITY ======================
define('APP_SALT', 'Eventukio2026SecureSalt123!');

// ====================== GOOGLE OAUTH PLACEHOLDERS ======================
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', BASE_URL . 'pages/google-callback.php');

// ====================== OTHER CONSTANTS ======================
define('APP_NAME', 'Eventukio');
define('DEFAULT_LANG', 'en');
define('DEFAULT_THEME', 'Oceanic Blue');
//====================== CHATS ========================
define('ENCRYPTION_KEY', 'your-secret-key-32-bytes-long'); // 32 bytes for AES-256
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// ====================== PDO CONNECTION ======================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// ====================== HELPER FUNCTIONS ======================
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect($page) {
    header("Location: " . BASE_URL . $page);
    exit();
}

function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Include other core files
require_once __DIR__ . '/global_translations.php';
require_once __DIR__ . '/functions.php';

// Determine the user's theme during request startup, while database connection is active
$userTheme = DEFAULT_THEME;
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && isset($user['user_theme'])) {
        $userTheme = $user['user_theme'];
    }
}

// ====================== OUTPUT BUFFERING FOR THEMING ======================
ob_start(function($buffer) use ($userTheme) {
    // Only intercept if this is an HTML response containing </head>
    if (stripos($buffer, '</head>') === false) {
        return $buffer;
    }

    // Generate style & scripts payload using the cached theme string
    $payload = getThemePayload($userTheme);

    // Inject scripts before tailwind CDN if it exists
    $tailwindTag = 'cdn.tailwindcss.com';
    $tagPos = stripos($buffer, $tailwindTag);
    if ($tagPos !== false) {
        $scriptStartPos = strrpos(substr($buffer, 0, $tagPos), '<script');
        if ($scriptStartPos !== false) {
            $buffer = substr($buffer, 0, $scriptStartPos) . $payload['scripts'] . substr($buffer, $scriptStartPos);
        } else {
            $buffer = str_ireplace('</head>', $payload['scripts'] . '</head>', $buffer);
        }
    } else {
        $buffer = str_ireplace('</head>', $payload['scripts'] . '</head>', $buffer);
    }

    // Always inject styles before </head>
    return str_ireplace('</head>', $payload['styles'] . '</head>', $buffer);
});
?>