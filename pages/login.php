<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Handle language switch - must be AFTER config.php loads but BEFORE any output
if (isset($_GET['set_language']) && in_array($_GET['set_language'], ['en', 'sw', 'suk'])) {
    $_SESSION['user_language'] = $_GET['set_language'];
    error_log("Language set to: " . $_GET['set_language']);
    error_log("Session language after set: " . ($_SESSION['user_language'] ?? 'not set'));
    header('Location: login.php');
    exit;
}

error_log("Current session language on page load: " . ($_SESSION['user_language'] ?? 'not set'));

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('pages/events.php');
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Prefill after registration
$prefillEmail = $_SESSION['registered_email'] ?? '';
$prefillPassword = $_SESSION['registered_password'] ?? '';
unset($_SESSION['registered_email'], $_SESSION['registered_password']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .oceanic-bg {
            background-color: #FAF7F2;
        }
        
        .glass-card {
            background: rgba(255, 248, 240, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(44, 44, 122, 0.15);
            box-shadow: 0 15px 35px rgba(44, 44, 122, 0.08);
        }
        
        .glass-input {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(44, 44, 122, 0.2);
            color: #1F1F1F;
            transition: all 0.3s ease;
        }
        
        .glass-input:focus {
            background: rgba(255, 255, 255, 0.85);
            border-color: #2C2C7A;
            box-shadow: 0 0 10px rgba(44, 44, 122, 0.15);
            outline: none;
        }

        .btn-oceanic {
            background-color: #2C2C7A;
            color: #FAF7F2;
            transition: all 0.3s ease;
        }
        
        .btn-oceanic:hover {
            background-color: #1E1E54;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(44, 44, 122, 0.2);
        }

        .btn-oceanic:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="oceanic-bg min-h-screen flex items-center justify-center p-4 text-[#1F1F1F]">

    <div class="max-w-md w-full">
        <!-- Logo / Brand Header -->
        <div class="text-center mb-8 select-none">
            <h1 class="text-4xl font-bold tracking-tight text-[#2C2C7A]">
                <span class="text-5xl">E</span>VENTUKIO
            </h1>
            <p class="text-gray-600 text-sm mt-1"><?= t('sign_in_to_continue') ?></p>
        </div>

        <div class="glass-card rounded-3xl p-8 shadow-xl">
            
            <!-- Language Selector & Title -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-[#2C2C7A]"><?= t('login') ?></h3>
                
                <!-- Language Switcher -->
                <div class="relative">
                    <button onclick="toggleLanguageDropdown()" class="text-gray-600 hover:text-[#2C2C7A] text-xs flex items-center gap-1.5 px-3 py-1.5 bg-white/50 rounded-full border border-gray-200 transition">
                        <i class="fa fa-globe"></i> <?= strtoupper($_SESSION['user_language'] ?? 'en') ?> <i class="fa fa-chevron-down text-[10px]"></i>
                    </button>
                    <div id="languageDropdown" class="hidden absolute right-0 mt-2 w-32 bg-white rounded-2xl overflow-hidden shadow-lg border border-gray-100 z-20">
                        <a href="?set_language=en" class="block px-4 py-2.5 text-gray-700 hover:bg-gray-50 text-xs transition">English</a>
                        <a href="?set_language=sw" class="block px-4 py-2.5 text-gray-700 hover:bg-gray-50 text-xs transition">Kiswahili</a>
                        <a href="?set_language=suk" class="block px-4 py-2.5 text-gray-700 hover:bg-gray-50 text-xs transition">Sukuma</a>
                    </div>
                </div>
            </div>

            <!-- Validation Messages -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-xs flex items-center gap-2">
                    <i class="fa fa-triangle-exclamation text-sm"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-2xl mb-6 text-xs flex items-center gap-2">
                    <i class="fa fa-circle-check text-sm"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="login_process.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold mb-2"><?= t('email_or_phone') ?></label>
                    <div class="relative">
                        <i class="fa fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="identifier" required
                               class="w-full glass-input rounded-2xl pl-11 pr-5 py-3.5 text-sm"
                               placeholder="<?= t('email_or_phone_placeholder') ?>"
                               value="<?= htmlspecialchars($prefillEmail) ?>">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-gray-700 text-xs font-semibold"><?= t('password') ?></label>
                        <a href="#" onclick="showForgotPassword()" class="text-[#2C2C7A] hover:underline text-xs">
                            <?= t('forgot_password') ?>
                        </a>
                    </div>
                    <div class="relative">
                        <i class="fa fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="password" name="password" required
                               class="w-full glass-input rounded-2xl pl-11 pr-5 py-3.5 text-sm"
                               placeholder="<?= t('enter_password') ?>"
                               value="<?= htmlspecialchars($prefillPassword) ?>">
                    </div>
                </div>

                <button type="submit"
                        class="w-full btn-oceanic font-semibold py-3.5 rounded-2xl text-sm tracking-wide mt-2">
                    <?= t('login') ?> &nbsp; <i class="fa fa-sign-in-alt text-xs"></i>
                </button>
            </form>

            <!-- Google OAuth -->
            <div class="mt-4">
                <button onclick="loginWithGoogle()"
                        class="w-full flex items-center justify-center gap-2 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 font-medium py-3.5 rounded-2xl transition text-xs">
                    <i class="fa-brands fa-google text-sm text-[#2C2C7A]"></i>
                    <span><?= t('continue_with_google') ?></span>
                </button>
            </div>

            <!-- Divider -->
            <div class="flex items-center gap-4 my-6">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-gray-400 text-xs">OR</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Bottom Navigation -->
            <div class="flex justify-between items-center text-xs">
                <a href="../index.php" class="text-gray-600 hover:text-[#2C2C7A] flex items-center gap-1.5 transition">
                    <i class="fa fa-arrow-left"></i> <?= t('back') ?> to Home
                </a>
                <a href="../pages/register.php" class="px-4 py-2 bg-white/60 hover:bg-white border border-gray-200 text-[#2C2C7A] font-semibold rounded-full transition">
                    <?= t('create_account') ?> &nbsp; <i class="fa fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>
        
        <p class="text-center text-gray-500 text-xs mt-6 select-none">
            © <?= date('Y') ?> Eventukio - Final Year Project
        </p>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl border border-gray-100">
            <h3 class="text-xl font-bold text-[#2C2C7A] mb-2">Reset Password</h3>
            <p class="text-xs text-gray-500 mb-6">Enter your registered email address to request an OTP code.</p>
            
            <input type="email" id="resetEmail" placeholder="name@domain.com"
                   class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-5 py-4 text-sm text-[#1F1F1F] placeholder-gray-400 focus:outline-none focus:border-[#2C2C7A] mb-6">
            
            <div class="flex gap-3">
                <button onclick="sendOTP()" 
                        class="flex-1 btn-oceanic py-3 rounded-2xl font-semibold text-xs">
                    Send OTP
                </button>
                <button onclick="closeModal()" 
                        class="flex-1 bg-gray-100 hover:bg-gray-250 text-gray-700 py-3 rounded-2xl font-semibold text-xs transition border border-gray-200">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function toggleLanguageDropdown() {
            const dropdown = document.getElementById('languageDropdown');
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('languageDropdown');
            const button = e.target.closest('button');
            if (button && button.getAttribute('onclick') && button.getAttribute('onclick').includes('toggleLanguageDropdown')) {
                return;
            }
            if (dropdown && !dropdown.classList.contains('hidden') && !e.target.closest('#languageDropdown')) {
                dropdown.classList.add('hidden');
            }
        });

        function loginWithGoogle() {
            alert("Google OAuth will be implemented soon (using Google Client ID from config)");
        }

        function showForgotPassword() {
            document.getElementById('forgotModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('forgotModal').classList.add('hidden');
        }

        function sendOTP() {
            const email = document.getElementById('resetEmail').value;
            if (!email) {
                alert("Please enter your email");
                return;
            }
            alert("OTP has been sent to " + email + " (Demo - real email system coming soon)");
            closeModal();
        }
    </script>
</body>
</html>