<?php
require_once '../config/config.php';
require_once '../config/functions.php';

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
        .glass {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-blue-900 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-10">
            <h1 class="text-5xl font-bold text-white tracking-tighter">
                <span class="text-6xl text-indigo-300">E</span>VENTUKIO
            </h1>
            <p class="text-indigo-200 mt-2">Sign in to continue</p>
        </div>

        <div class="glass rounded-3xl p-8 shadow-2xl">
            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-400 text-red-200 px-4 py-3 rounded-2xl mb-6 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-500/20 border border-green-400 text-green-200 px-4 py-3 rounded-2xl mb-6 text-sm">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login_process.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-white text-sm mb-2">Email or Phone Number</label>
                      <input type="text" name="identifier" required
                          class="w-full glass border border-white/30 rounded-2xl px-5 py-4 text-white placeholder-gray-400 focus:outline-none focus:border-indigo-400"
                          placeholder="example@email.com or 255712345678"
                          value="<?= htmlspecialchars($prefillEmail) ?>">
                </div>

                <div>
                    <label class="block text-white text-sm mb-2">Password</label>
                      <input type="password" name="password" required
                          class="w-full glass border border-white/30 rounded-2xl px-5 py-4 text-white placeholder-gray-400 focus:outline-none focus:border-indigo-400"
                          placeholder="Enter your password"
                          value="<?= htmlspecialchars($prefillPassword) ?>">
                </div>

                <div class="flex justify-end">
                    <a href="#" onclick="showForgotPassword()" class="text-indigo-300 hover:text-white text-sm">
                        Forgot Password?
                    </a>
                </div>

                <button type="submit"
                        class="w-full bg-white text-indigo-700 hover:bg-indigo-100 font-semibold py-4 rounded-2xl transition text-lg">
                    Login
                </button>
            </form>

            <!-- Google OAuth -->
            <div class="mt-6">
                <button onclick="loginWithGoogle()" 
                        class="w-full flex items-center justify-center gap-3 bg-white/10 hover:bg-white/20 border border-white/30 text-white font-medium py-4 rounded-2xl transition">
                    <i class="fa-brands fa-google text-xl"></i>
                    <span>Continue with Google</span>
                </button>
            </div>

            <!-- Divider -->
            <div class="flex items-center gap-4 my-8">
                <div class="flex-1 h-px bg-white/20"></div>
                <span class="text-white/50 text-sm">OR</span>
                <div class="flex-1 h-px bg-white/20"></div>
            </div>

            <!-- Links -->
            <div class="flex justify-between text-sm">
                <a href="../index.php" class="text-indigo-200 hover:text-white flex items-center gap-2">
                    <i class="fa fa-arrow-left"></i> Back to Home
                </a>
                <a href="../pages/register.php" class="text-indigo-200 hover:text-white">
                    Create new account →
                </a>
            </div>
        </div>

        <p class="text-center text-white/60 text-xs mt-8">
            © <?= date('Y') ?> Eventukio - Final Year Project
        </p>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
        <div class="glass rounded-3xl p-8 max-w-sm w-full mx-4">
            <h3 class="text-xl font-semibold text-white mb-6">Reset Password</h3>
            <input type="email" id="resetEmail" placeholder="Enter your registered email"
                   class="w-full glass border border-white/30 rounded-2xl px-5 py-4 text-white mb-6">
            <div class="flex gap-4">
                <button onclick="sendOTP()" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3.5 rounded-2xl">
                    Send OTP
                </button>
                <button onclick="closeModal()" 
                        class="flex-1 bg-white/10 hover:bg-white/20 text-white py-3.5 rounded-2xl">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function loginWithGoogle() {
            alert("Google OAuth will be implemented soon (using Google Client ID from config)");
            // window.location.href = "google-callback.php";
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
            // In future: redirect to OTP verification page
        }
    </script>
</body>
</html>