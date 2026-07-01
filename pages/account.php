<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user = getCurrentUser();

// Get Wallet Balance
$wallet_stmt = $pdo->prepare("SELECT account_balance FROM user_wallet_info WHERE user_id = ?");
$wallet_stmt->execute([$user['user_id']]);
$wallet = $wallet_stmt->fetch();
$balance = $wallet ? $wallet['account_balance'] : 0;

// Get event stats
$hosted_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_basic_info WHERE host_id = ?");
$hosted_stmt->execute([$user['user_id']]);
$hosted_count = $hosted_stmt->fetchColumn();

$attended_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_attendees WHERE participant_id = ? AND participation_status = 'Active'");
$attended_stmt->execute([$user['user_id']]);
$attended_count = $attended_stmt->fetchColumn();

// Handle Language Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lang') {
    $new_lang = $_POST['language'] ?? 'en';
    $valid_languages = ['en', 'sw', 'suk'];

    if (in_array($new_lang, $valid_languages)) {
        // Use the new setUserLanguage function
        setUserLanguage($new_lang);

        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => true]);
            exit;
        }

        // Regular form submission - redirect back to refresh page with new language
        successMsg("Language updated successfully");
        redirect('pages/account.php');
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Invalid language']);
            exit;
        }
        errorMsg("Invalid language selected");
        redirect('account.php');
    }
}

// Handle Theme Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_theme') {
    $new_theme = $_POST['theme'] ?? 'Oceanic Blue';
    $update_stmt = $pdo->prepare("UPDATE user_basic_info SET user_theme = ? WHERE user_id = ?");
    $update_stmt->execute([$new_theme, $user['user_id']]);
    // Refresh user data
    $user = getCurrentUser();
    header('Location: account.php');
    exit;
}

// Handle Account Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $user['user_password_hash'])) { // Assuming passwords are hashed
        // Ban the user
        $ban_stmt = $pdo->prepare("UPDATE user_basic_info SET user_validity = 'Banned' WHERE user_id = ?");
        $ban_stmt->execute([$user['user_id']]);
        // Log out
        session_destroy();
        redirect('../index.php');
    } else {
        $error = "Incorrect password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/i18next@21/dist/umd/i18next.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/i18next-http-backend@1/dist/i18nextHttpBackend.min.js"></script>
    <script src="assets/js/i18next-init.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .card-hover:hover { background: rgba(255,255,255,0.25); }
        .settings-sub { transition: max-height 0.3s ease; overflow: hidden; max-height: 0; }
        .settings-sub.open { max-height: 200px; }
        /* Modal overlay */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 2rem; padding: 2rem; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-box label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .modal-box input[type="radio"] { margin-right: 0.5rem; }
        .modal-box .radio-group { margin: 1rem 0; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700"><?= t('my_account') ?></h1>
        <a href="events.php" class="text-gray-700 hover:text-indigo-600"><i class="fa fa-arrow-left mr-1"></i><?= t('home') ?></a>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Profile Header -->
    <div class="glass rounded-3xl p-8 text-center mb-8">
        <img src="<?= htmlspecialchars(getProfilePictureUrl($user['user_profile_picture'] ?? '', BASE_URL . 'assets/images/default.png', 'absolute')) ?>" 
             class="w-28 h-28 rounded-full mx-auto border-4 border-white object-cover mb-4" alt="">
        <h2 class="text-2xl font-semibold" data-i18n="account.profile_name"><?= htmlspecialchars($user['user_full_name']) ?></h2>
        <p class="text-indigo-600" data-i18n="account.account_type"><?= htmlspecialchars($user['user_type']) ?> Account</p>
        <p class="text-sm text-gray-500 mt-2" data-i18n="account.email"><?= htmlspecialchars($user['user_email']) ?></p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="glass rounded-3xl p-6 text-center">
            <p class="text-3xl font-bold text-indigo-600"><?= $hosted_count ?></p>
            <p class="text-xs text-gray-500"><?= t('events_hosted') ?></p>
        </div>
        <div class="glass rounded-3xl p-6 text-center">
            <p class="text-3xl font-bold text-indigo-600"><?= $attended_count ?></p>
            <p class="text-xs text-gray-500"><?= t('events_attended') ?></p>
        </div>
        <div class="glass rounded-3xl p-6 text-center">
            <p class="text-3xl font-bold text-green-500">TZS <?= number_format($balance, 0) ?></p>
            <p class="text-xs text-gray-500"><?= t('wallet_balance') ?></p>
        </div>
    </div>

    <!-- Menu Options -->
    <div class="space-y-3">
        <!-- Update Profile -->
        <a href="update-profile.php" class="glass flex items-center justify-between p-6 rounded-3xl card-hover transition">
            <div class="flex items-center gap-4">
                <i class="fa fa-user text-2xl text-indigo-600"></i>
                <div>
                    <p class="font-semibold"><?= t('update_profile') ?></p>
                    <p class="text-sm text-gray-500"><?= t('update_profile_desc') ?></p>
                </div>
            </div>
            <i class="fa fa-chevron-right"></i>
        </a>

        <!-- Manage Wallet -->
        <a href="manage-wallet.php" class="glass flex items-center justify-between p-6 rounded-3xl card-hover transition">
            <div class="flex items-center gap-4">
                <i class="fa fa-wallet text-2xl text-indigo-600"></i>
                <div>
                    <p class="font-semibold"><?= t('manage_wallet') ?></p>
                    <p class="text-sm text-gray-500"><?= t('manage_wallet_desc') ?></p>
                </div>
            </div>
            <i class="fa fa-chevron-right"></i>
        </a>

        <!-- Settings (Expandable) -->
        <div class="glass rounded-3xl overflow-hidden">
            <button id="settings-toggle" class="w-full flex items-center justify-between p-6 hover:bg-white/10 transition text-left">
                <div class="flex items-center gap-4">
                    <i class="fa fa-cog text-2xl text-indigo-600"></i>
                    <div>
                        <p class="font-semibold"><?= t('settings') ?></p>
                        <p class="text-sm text-gray-500"><?= t('settings_desc') ?></p>
                    </div>
                </div>
                <i id="settings-arrow" class="fa fa-chevron-down transition-transform duration-300"></i>
            </button>
            <div id="settings-sub" class="settings-sub">
                <div class="px-6 pb-4 space-y-3">
                    <!-- Language card -->
                    <button onclick="openModal('languageModal')" class="w-full glass flex items-center justify-between p-4 rounded-2xl hover:bg-white/20 transition">
                        <div class="flex items-center gap-3">
                            <i class="fa fa-language text-xl text-indigo-600"></i>
                            <span class="font-medium"><?= t('language') ?></span>
                        </div>
                        <i class="fa fa-chevron-right text-sm"></i>
                    </button>
                    <!-- Theme card -->
                    <button onclick="openModal('themeModal')" class="w-full glass flex items-center justify-between p-4 rounded-2xl hover:bg-white/20 transition">
                        <div class="flex items-center gap-3">
                            <i class="fa fa-palette text-xl text-indigo-600"></i>
                            <span class="font-medium"><?= t('theme') ?></span>
                        </div>
                        <i class="fa fa-chevron-right text-sm"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- History -->
        <a href="history.php" class="glass flex items-center justify-between p-6 rounded-3xl card-hover transition">
            <div class="flex items-center gap-4">
                <i class="fa fa-history text-2xl text-indigo-600"></i>
                <div>
                    <p class="font-semibold"><?= t('history') ?></p>
                    <p class="text-sm text-gray-500"><?= t('history_desc') ?></p>
                </div>
            </div>
            <i class="fa fa-chevron-right"></i>
        </a>

        <!-- Delete Account -->
        <button onclick="openModal('deleteModal')" class="glass flex items-center justify-between p-6 rounded-3xl text-red-600 hover:bg-white/10 transition w-full">
            <div class="flex items-center gap-4">
                <i class="fa fa-trash-alt text-2xl"></i>
                <div>
                    <p class="font-semibold"><?= t('delete_account') ?></p>
                    <p class="text-sm text-gray-500"><?= t('delete_account_desc') ?></p>
                </div>
            </div>
            <i class="fa fa-chevron-right"></i>
        </button>

        <!-- Logout -->
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"
           class="glass flex items-center justify-between p-6 rounded-3xl text-red-600 hover:bg-white/10 transition">
            <div class="flex items-center gap-4">
                <i class="fa fa-sign-out-alt text-2xl"></i>
                <div>
                    <p class="font-semibold"><?= t('logout') ?></p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Language Modal -->
<div id="languageModal" class="modal-overlay" onclick="if(event.target===this) closeModal('languageModal')">
    <div class="modal-box">
        <h3 class="text-xl font-bold mb-4"><?= t('select_language') ?></h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_lang">
            <div class="radio-group">
                <label><input type="radio" name="language" value="en" <?= ($user['user_language'] ?? 'en') == 'en' ? 'checked' : '' ?>> English</label>
                <label><input type="radio" name="language" value="sw" <?= ($user['user_language'] ?? 'en') == 'sw' ? 'checked' : '' ?>> Swahili</label>
                <label><input type="radio" name="language" value="suk" <?= ($user['user_language'] ?? 'en') == 'suk' ? 'checked' : '' ?>> Sukuma</label>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('languageModal')" class="px-4 py-2 bg-gray-200 rounded-full">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-full">Choose Language</button>
            </div>
        </form>
    </div>
</div>

<!-- Theme Modal -->
<div id="themeModal" class="modal-overlay" onclick="if(event.target===this) closeModal('themeModal')">
    <div class="modal-box">
        <h3 class="text-xl font-bold mb-4" data-i18n="account.select_theme">Select Theme</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_theme">
            <div class="radio-group">
                <label><input type="radio" name="theme" value="Oceanic Blue" <?= ($user['user_theme'] ?? 'Oceanic Blue') == 'Oceanic Blue' ? 'checked' : '' ?>> Oceanic Blue (Default)</label>
                <label><input type="radio" name="theme" value="Warm Glow" <?= ($user['user_theme'] ?? 'Oceanic Blue') == 'Warm Glow' ? 'checked' : '' ?>> Warm Glow</label>
                <label><input type="radio" name="theme" value="Luxe Jewel" <?= ($user['user_theme'] ?? 'Oceanic Blue') == 'Luxe Jewel' ? 'checked' : '' ?>> Luxe Jewel</label>
                <label><input type="radio" name="theme" value="Soft Pastel" <?= ($user['user_theme'] ?? 'Oceanic Blue') == 'Soft Pastel' ? 'checked' : '' ?>> Soft Pastel</label>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('themeModal')" class="px-4 py-2 bg-gray-200 rounded-full">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-full">Choose Theme</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="deleteModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deleteModal')">
    <div class="modal-box">
        <h3 class="text-xl font-bold mb-2 text-red-600" data-i18n="account.delete_account">Delete Account</h3>
        <p class="text-gray-600 mb-4" data-i18n="account.delete_account_desc">Are you sure you want to permanently delete your account? This action cannot be undone.</p>
        <?php if (isset($error)): ?>
            <p class="text-red-500 text-sm mb-2"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete_account">
            <div class="mb-4">
                <label for="delete-password" class="block text-sm font-medium text-gray-700">Enter your login password to proceed</label>
                <input type="password" name="password" id="delete-password" required class="mt-1 block w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-200 rounded-full">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-full">Delete Account</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Toggle Settings expand
    const toggleBtn = document.getElementById('settings-toggle');
    const sub = document.getElementById('settings-sub');
    const arrow = document.getElementById('settings-arrow');

    toggleBtn.addEventListener('click', function() {
        sub.classList.toggle('open');
        arrow.classList.toggle('rotate-180');
    });

    // Modal functions
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(el => {
                el.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
    // Refresh translations after language change
if (typeof changeLanguage === 'function') {
    // Trigger re-init if needed
}
</script>

</body>
</html>