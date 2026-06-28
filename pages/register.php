<?php
require_once '../config/config.php';
require_once '../config/functions.php';
if (isLoggedIn()) redirect('pages/events.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.25); }
        .step { display: none; }
        .step.active { display: block; }
        .option-card.active { border-color: #6366f1; background: rgba(99,102,241,0.15); }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-blue-900 min-h-screen p-4">

<div class="max-w-2xl mx-auto">
    <div class="flex justify-end mb-4">
        <a href="login.php" class="px-4 py-2 bg-white/10 text-white rounded-2xl hover:bg-white/20 transition">
            Login
        </a>
    </div>
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white"><span class="text-indigo-300">E</span>VENTUKIO</h1>
        <p class="text-indigo-200">Create Account - Step <span id="stepNum">1</span>/4</p>
    </div>

    <div class="glass rounded-3xl p-8">
        <div class="h-2 bg-white/20 rounded-full mb-8">
            <div id="progressBar" class="h-2 bg-indigo-500 rounded-full transition-all" style="width:25%"></div>
        </div>

        <form id="registerForm" action="register_process.php" method="POST" class="space-y-8">

            <!-- STEP 1: Language -->
            <div class="step active" id="step1">
                <h2 class="text-2xl font-semibold text-white mb-6">1. Choose your language</h2>
                <div class="grid grid-cols-3 gap-4">
                    <button type="button" onclick="selectLang(this,'en')" class="option-card p-8 rounded-3xl border-2 text-center">🇬🇧 English</button>
                    <button type="button" onclick="selectLang(this,'sw')" class="option-card p-8 rounded-3xl border-2 text-center">🇹🇿 Kiswahili</button>
                    <button type="button" onclick="selectLang(this,'suk')" class="option-card p-8 rounded-3xl border-2 text-center">Sukuma</button>
                </div>
            </div>

            <!-- STEP 2: Account Type -->
            <div class="step" id="step2">
                <h2 class="text-2xl font-semibold text-white mb-6">2. Account Type</h2>
                <button type="button" onclick="selectType(this,'Personal')" class="option-card w-full p-6 rounded-3xl border-2 text-left mb-4">Personal Account</button>
                <button type="button" onclick="selectType(this,'Business')" class="option-card w-full p-6 rounded-3xl border-2 text-left">Organization / Business Account</button>
            </div>

            <!-- STEP 3: Basic Information -->
            <div class="step" id="step3">
                <h2 class="text-2xl font-semibold text-white mb-6" id="title3">3. Basic Information</h2>
                <div id="basicFields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
            </div>

            <!-- STEP 4: Authentication -->
            <div class="step" id="step4">
                <h2 class="text-2xl font-semibold text-white mb-6">4. Account Authentication & Activation</h2>
                <!-- National ID moved to Step 3 for Personal accounts -->
                <input type="tel" name="recovery_phone" placeholder="Recovery Phone Number" class="glass w-full rounded-2xl px-5 py-4 mb-4">
                <input type="password" name="password" placeholder="Create Password" required class="glass w-full rounded-2xl px-5 py-4 mb-4">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required class="glass w-full rounded-2xl px-5 py-4">
            </div>
            <input type="hidden" name="account_type" id="account_type" value="">
            <input type="hidden" name="language" id="language" value="en">

            <div class="flex justify-between pt-8">
                <button type="button" id="prevBtn" onclick="prevStep()" class="px-8 py-4 text-white border border-white/30 rounded-2xl hidden">Previous</button>
                <button type="button" id="nextBtn" onclick="nextStep()" class="px-10 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-2xl">Next</button>
            </div>

        </form>
    </div>
</div>

<script>
    let currentStep = 1;
    let nidaValidated = false;

    function showStep(n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step'+n).classList.add('active');
        document.getElementById('progressBar').style.width = (n * 25) + '%';
        document.getElementById('prevBtn').classList.toggle('hidden', n === 1);
        document.getElementById('nextBtn').textContent = n === 4 ? 'Create Account' : 'Next';
        document.getElementById('stepNum').textContent = n;
        currentStep = n;
    }

    function validateNidaNumber() {
        const accountType = document.getElementById('account_type').value;
        if (accountType !== 'Personal') return true;

        const result = document.getElementById('nidaValidationResult');
        const nidInput = document.querySelector('input[name="national_id"]');
        const birthDateInput = document.querySelector('input[name="birth_date"]');
        if (!nidInput || !birthDateInput) return true;

        const nid = nidInput.value.trim();
        const birthDate = birthDateInput.value;
        const regex = /^(\d{8})-(\d{5})-(\d{5})-(\d{2})$/;
        const matches = nid.match(regex);
        if (!matches) {
            result.textContent = 'Invalid NIDA format. Use 12345678-12345-12345-12.';
            result.className = 'text-sm text-red-300 mt-3';
            return false;
        }

        const dobDigits = matches[1];
        const zipPart = matches[2];
        const seqDigits = matches[3];
        const year = parseInt(dobDigits.slice(0, 4), 10);
        const month = parseInt(dobDigits.slice(4, 6), 10);
        const day = parseInt(dobDigits.slice(6, 8), 10);

        const parsedDate = new Date(year, month - 1, day);
        if (parsedDate.getFullYear() !== year || parsedDate.getMonth() + 1 !== month || parsedDate.getDate() !== day) {
            result.textContent = 'Invalid date within NIDA. Please check the birth date portion.';
            result.className = 'text-sm text-red-300 mt-3';
            return false;
        }

        if (!birthDate) {
            result.textContent = 'Please enter your birth date before validating NIDA.';
            result.className = 'text-sm text-red-300 mt-3';
            nidaValidated = false;
            return false;
        }

        const enteredBirthDate = new Date(birthDate);
        if (enteredBirthDate.getFullYear() !== year || enteredBirthDate.getMonth() + 1 !== month || enteredBirthDate.getDate() !== day) {
            result.textContent = 'Birth date must match the date encoded in the NIDA number.';
            result.className = 'text-sm text-red-300 mt-3';
            return false;
        }

        const sequence = parseInt(seqDigits, 10);
        if (sequence < 0 || sequence > 9) {
            result.textContent = 'NIDA sequence must be between 00000 and 00009.';
            result.className = 'text-sm text-red-300 mt-3';
            return false;
        }

        if (zipPart.length !== 5 || isNaN(Number(zipPart))) {
            result.textContent = 'NIDA ZIP section must be exactly 5 digits.';
            result.className = 'text-sm text-red-300 mt-3';
            return false;
        }

        result.textContent = 'NIDA looks valid. Proceed to the next step or submit the form.';
        result.className = 'text-sm text-green-300 mt-3';
        nidaValidated = true;
        return true;
    }

    function resetNidaValidation() {
        nidaValidated = false;
        const result = document.getElementById('nidaValidationResult');
        if (result) {
            result.textContent = '';
            result.className = 'text-sm text-white/70 mt-3';
        }
    }

    function setupNidaChangeListeners() {
        const birthInput = document.querySelector('input[name="birth_date"]');
        const nidInput = document.querySelector('input[name="national_id"]');
        [birthInput, nidInput].forEach(input => {
            if (!input) return;
            input.addEventListener('input', resetNidaValidation);
        });
    }

    function nextStep() {
        if (currentStep === 3 && document.getElementById('account_type').value === 'Personal') {
            if (!nidaValidated) {
                if (!validateNidaNumber()) {
                    return;
                }
            }
        }
        if (currentStep < 4) showStep(currentStep + 1);
        else document.getElementById('registerForm').submit();
    }

    function prevStep() {
        if (currentStep > 1) showStep(currentStep - 1);
    }

function selectType(btn, type) {
    document.querySelectorAll('#step2 .option-card').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('account_type').value = type;
    renderBasicFields(type);
    nextStep();
}

function selectLang(btn, lang) {
    document.querySelectorAll('#step1 .option-card').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('language').value = lang;
    nextStep();
}
    function renderBasicFields(type) {
        const container = document.getElementById('basicFields');
        const title = document.getElementById('title3');
        container.innerHTML = '';

        if (type === 'Personal') {
            title.textContent = '3. Personal Basic Information';
            container.innerHTML = `
                <div><label class="text-white text-sm">First Name</label><input name="first_name" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div><label class="text-white text-sm">Surname</label><input name="surname" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div class="md:col-span-2"><label class="text-white text-sm">Email</label><input type="email" name="email" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div><label class="text-white text-sm">Phone Number</label><input name="phone" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div><label class="text-white text-sm">Birth Date</label><input type="date" name="birth_date" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div class="md:col-span-2">
                    <label class="text-white text-sm">NIDA Number</label>
                    <input type="text" name="national_id" required placeholder="12345678-12345-12345-12" pattern="\d{8}-\d{5}-\d{5}-\d{2}" title="Format: 12345678-12345-12345-12" class="glass w-full rounded-2xl px-5 py-4 text-white mt-1">
                    <p class="text-white/60 text-xs mt-2">Enter your 20-digit NIDA in the format <span class="font-semibold">YYYYMMDD-12345-12345-12</span>.</p>
                    <button type="button" onclick="validateNidaNumber()" class="mt-3 px-5 py-3 bg-indigo-500 hover:bg-indigo-600 text-white rounded-2xl transition">Validate NIDA</button>
                    <p id="nidaValidationResult" class="text-sm text-white/70 mt-3 min-h-[1.25rem]"></p>
                </div>
            `;
            setupNidaChangeListeners();
        } else {
            title.textContent = '3. Organization Basic Information';
            container.innerHTML = `
                <div class="md:col-span-2"><label class="text-white text-sm">Organization Name</label><input name="full_name" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div><label class="text-white text-sm">Email</label><input type="email" name="email" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
                <div><label class="text-white text-sm">Phone Number</label><input name="phone" required class="glass w-full rounded-2xl px-5 py-4 text-white mt-1"></div>
            `;
        }
    }

    showStep(1);
</script>
</body>
</html>