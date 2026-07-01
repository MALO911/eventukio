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

        .option-card {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(44, 44, 122, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .option-card:hover {
            border-color: rgba(44, 44, 122, 0.4);
            background: rgba(255, 255, 255, 0.8);
            transform: translateY(-1px);
        }
        
        .option-card.active {
            border-color: #2C2C7A;
            background: rgba(44, 44, 122, 0.08);
            box-shadow: 0 0 15px rgba(44, 44, 122, 0.1);
        }

        .step { display: none; }
        .step.active { display: block; }

        .btn-oceanic {
            background-color: #2C2C7A;
            color: #FAF7F2;
            transition: all 0.3s ease;
        }
        
        .btn-oceanic:hover:not(:disabled) {
            background-color: #1E1E54;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(44, 44, 122, 0.2);
        }

        .btn-oceanic:active:not(:disabled) {
            transform: translateY(0);
        }
    </style>
</head>
<body class="oceanic-bg min-h-screen p-4 md:p-8 text-[#1F1F1F] flex items-center justify-center">

    <div class="max-w-2xl w-full relative">
        <!-- Brand / Header -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-2 select-none">
                <div class="w-8 h-8 rounded-lg bg-[#2C2C7A] flex items-center justify-center">
                    <span class="text-md font-black text-[#FAF7F2]">E</span>
                </div>
                <span class="text-xl font-extrabold tracking-wider text-[#2C2C7A]">
                    EVENTUKIO
                </span>
            </div>
            
            <a href="login.php" class="px-4 py-2 bg-white/60 border border-gray-200 text-xs text-[#2C2C7A] font-semibold rounded-full hover:bg-white transition flex items-center gap-1.5">
                Login <i class="fa fa-sign-in text-[10px]"></i>
            </a>
        </div>

        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-[#2C2C7A]">Create Your Account</h2>
            <p class="text-gray-600 text-sm mt-1">Join Eventukio and start coordinating awesome events.</p>
        </div>

        <!-- Glass Stepper Card -->
        <div class="glass-card rounded-3xl p-8 shadow-xl">
            
            <!-- Progress Line Indicator -->
            <div class="relative flex items-center justify-between mb-10 select-none max-w-md mx-auto">
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-[2px] bg-gray-200 rounded-full z-0"></div>
                <div id="progressBar" class="absolute left-0 top-1/2 -translate-y-1/2 h-[2px] bg-[#2C2C7A] rounded-full z-0 transition-all duration-300" style="width: 0%"></div>
                
                <div class="step-node relative z-10 w-9 h-9 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center font-bold text-xs text-gray-400 shadow-sm transition duration-300" id="node1">1</div>
                <div class="step-node relative z-10 w-9 h-9 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center font-bold text-xs text-gray-400 shadow-sm transition duration-300" id="node2">2</div>
                <div class="step-node relative z-10 w-9 h-9 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center font-bold text-xs text-gray-400 shadow-sm transition duration-300" id="node3">3</div>
                <div class="step-node relative z-10 w-9 h-9 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center font-bold text-xs text-gray-400 shadow-sm transition duration-300" id="node4">4</div>
            </div>

            <form id="registerForm" action="register_process.php" method="POST" class="space-y-6">

                <!-- STEP 1: Language Selection -->
                <div class="step active" id="step1">
                    <h3 class="text-xl font-bold text-[#2C2C7A] mb-2">Choose your language</h3>
                    <p class="text-xs text-gray-500 mb-6">Select your default communication preference.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button type="button" onclick="selectLang(this,'en')" class="option-card p-6 rounded-2xl text-center flex flex-col items-center justify-center gap-2">
                            <span class="text-2xl">🇬🇧</span>
                            <span class="font-semibold text-sm text-[#2C2C7A]">English</span>
                        </button>
                        <button type="button" onclick="selectLang(this,'sw')" class="option-card p-6 rounded-2xl text-center flex flex-col items-center justify-center gap-2">
                            <span class="text-2xl">🇹🇿</span>
                            <span class="font-semibold text-sm text-[#2C2C7A]">Kiswahili</span>
                        </button>
                        <button type="button" onclick="selectLang(this,'suk')" class="option-card p-6 rounded-2xl text-center flex flex-col items-center justify-center gap-2">
                            <span class="text-2xl">🛖</span>
                            <span class="font-semibold text-sm text-[#2C2C7A]">Sukuma</span>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Account Type selection -->
                <div class="step" id="step2">
                    <h3 class="text-xl font-bold text-[#2C2C7A] mb-2">Account Type</h3>
                    <p class="text-xs text-gray-500 mb-6">Which type of profile matches your usage?</p>
                    
                    <div class="space-y-4">
                        <button type="button" onclick="selectType(this,'Personal')" class="option-card w-full p-5 rounded-2xl text-left flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#2C2C7A]/5 flex items-center justify-center text-[#2C2C7A] mt-1 flex-shrink-0">
                                <i class="fa fa-user text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-[#2C2C7A]">Personal Account</h4>
                                <p class="text-xs text-gray-600 mt-1">For standard users, attendees, asset owners, and service providers.</p>
                            </div>
                        </button>
                        
                        <button type="button" onclick="selectType(this,'Business')" class="option-card w-full p-5 rounded-2xl text-left flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#2C2C7A]/5 flex items-center justify-center text-[#2C2C7A] mt-1 flex-shrink-0">
                                <i class="fa fa-building text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-[#2C2C7A]">Organization / Business Account</h4>
                                <p class="text-xs text-gray-600 mt-1">For corporate committees, event planning agencies, and organizations.</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Basic Info form (dynamically filled) -->
                <div class="step" id="step3">
                    <h3 class="text-xl font-bold text-[#2C2C7A] mb-2" id="title3">Basic Information</h3>
                    <p class="text-xs text-gray-500 mb-6">Enter your name and contact details.</p>
                    
                    <div id="basicFields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                </div>

                <!-- STEP 4: Auth credentials -->
                <div class="step" id="step4">
                    <h3 class="text-xl font-bold text-[#2C2C7A] mb-2">Authentication & Security</h3>
                    <p class="text-xs text-gray-500 mb-6">Secure your credentials and finalize registration.</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1.5">Recovery Phone Number</label>
                            <input type="tel" name="recovery_phone" placeholder="e.g. 255XXXXXXXXX" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1.5">Password</label>
                            <input type="password" name="password" placeholder="Create robust password" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1.5">Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Repeat password to verify" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm">
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs -->
                <input type="hidden" name="account_type" id="account_type" value="">
                <input type="hidden" name="language" id="language" value="en">

                <!-- Navigation Controls -->
                <div class="flex justify-between pt-6 border-t border-gray-200">
                    <button type="button" id="prevBtn" onclick="prevStep()" class="px-6 py-3.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 rounded-2xl text-xs font-semibold transition hidden">
                        <i class="fa fa-arrow-left mr-1"></i> Previous
                    </button>
                    <button type="button" id="nextBtn" onclick="nextStep()" class="px-8 py-3.5 btn-oceanic font-semibold rounded-2xl text-xs tracking-wider ml-auto">
                        Next <i class="fa fa-arrow-right ml-1"></i>
                    </button>
                </div>

            </form>
        </div>
        
        <p class="text-center text-gray-500 text-xs mt-6 select-none">
            © <?= date('Y') ?> Eventukio - Final Year Project
        </p>
    </div>

    <script>
        let currentStep = 1;
        let nidaValidated = false;

        function showStep(n) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step'+n).classList.add('active');
            
            const progressWidth = ((n - 1) / 3) * 100;
            document.getElementById('progressBar').style.width = progressWidth + '%';
            
            for (let i = 1; i <= 4; i++) {
                const node = document.getElementById('node' + i);
                if (i < n) {
                    node.className = "step-node relative z-10 w-9 h-9 rounded-full bg-[#2C2C7A] border-0 flex items-center justify-center font-bold text-xs text-[#FAF7F2] shadow-sm";
                    node.innerHTML = '<i class="fa fa-check text-[10px]"></i>';
                } else if (i === n) {
                    node.className = "step-node relative z-10 w-9 h-9 rounded-full bg-[#FAF7F2] border-2 border-[#2C2C7A] flex items-center justify-center font-bold text-xs text-[#2C2C7A] shadow-sm ring-4 ring-[#2C2C7A]/10";
                    node.innerHTML = i;
                } else {
                    node.className = "step-node relative z-10 w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center font-bold text-xs text-gray-400";
                    node.innerHTML = i;
                }
            }
            
            document.getElementById('prevBtn').classList.toggle('hidden', n === 1);
            document.getElementById('nextBtn').innerHTML = n === 4 ? 'Create Account &nbsp;<i class="fa fa-circle-check"></i>' : 'Next &nbsp;<i class="fa fa-arrow-right text-[10px]"></i>';
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
                result.textContent = 'Invalid NIDA format. Use YYYYMMDD-12345-12345-12.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
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
                result.textContent = 'Invalid date within NIDA code.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
                return false;
            }

            if (!birthDate) {
                result.textContent = 'Please enter your birth date before validating NIDA.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
                nidaValidated = false;
                return false;
            }

            const enteredBirthDate = new Date(birthDate);
            if (enteredBirthDate.getFullYear() !== year || enteredBirthDate.getMonth() + 1 !== month || enteredBirthDate.getDate() !== day) {
                result.textContent = 'Birth date must match the date encoded in your NIDA number.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
                return false;
            }

            const sequence = parseInt(seqDigits, 10);
            if (sequence < 0 || sequence > 9) {
                result.textContent = 'NIDA sequence must be between 00000 and 00009.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
                return false;
            }

            if (zipPart.length !== 5 || isNaN(Number(zipPart))) {
                result.textContent = 'NIDA ZIP section must be exactly 5 digits.';
                result.className = 'text-xs text-red-700 mt-2 bg-red-50 px-4 py-2.5 rounded-xl border border-red-200';
                return false;
            }

            result.textContent = '✓ NIDA number verified successfully.';
            result.className = 'text-xs text-green-700 mt-2 bg-green-50 px-4 py-2.5 rounded-xl border border-green-200';
            nidaValidated = true;
            return true;
        }

        function resetNidaValidation() {
            nidaValidated = false;
            const result = document.getElementById('nidaValidationResult');
            if (result) {
                result.textContent = '';
                result.className = 'text-xs text-gray-500 mt-2';
            }
        }

        function setupNidaChangeListeners() {
            const birthInput = document.querySelector('input[name="birth_date"]');
            const nidInput = document.querySelector('input[name="national_id"]');
            [birthInput, nidInput].forEach(input => {
                if (!input) return;
                input.addEventListener('input', resetNidaValidation);
            });

            if (nidInput) {
                nidInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, ''); 
                    let formatted = '';
                    
                    if (value.length > 0) {
                        formatted += value.substring(0, 8);
                    }
                    if (value.length > 8) {
                        formatted += '-' + value.substring(8, 13);
                    }
                    if (value.length > 13) {
                        formatted += '-' + value.substring(13, 18);
                    }
                    if (value.length > 18) {
                        formatted += '-' + value.substring(18, 20);
                    }
                    
                    this.value = formatted;
                });
            }
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
                title.textContent = 'Personal Details';
                container.innerHTML = `
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">First Name</label>
                        <input name="first_name" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Surname</label>
                        <input name="surname" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Email Address</label>
                        <input type="email" name="email" required placeholder="name@domain.com" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Phone Number</label>
                        <input name="phone" required placeholder="e.g. 255756XXXXXX" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Birth Date</label>
                        <input type="date" name="birth_date" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">NIDA Number (National ID)</label>
                        <input type="text" name="national_id" required placeholder="YYYYMMDD-12345-12345-12" pattern="\\d{8}-\\d{5}-\\d{5}-\\d{2}" title="Format: 12345678-12345-12345-12" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                        <p class="text-gray-500 text-[10px] mt-1.5 leading-relaxed">Enter your 20-digit NIDA containing date portion YYYYMMDD matching your Birth Date above.</p>
                        <button type="button" onclick="validateNidaNumber()" class="mt-3 px-4 py-2.5 bg-[#2C2C7A]/5 border border-[#2C2C7A]/25 hover:bg-[#2C2C7A]/10 text-[#2C2C7A] text-xs font-semibold rounded-xl transition">Validate ID Card</button>
                        <div id="nidaValidationResult" class="min-h-[1rem]"></div>
                    </div>
                `;
                setupNidaChangeListeners();
            } else {
                title.textContent = 'Organization Details';
                container.innerHTML = `
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Organization / Business Name</label>
                        <input name="full_name" required class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Corporate Email</label>
                        <input type="email" name="email" required placeholder="contact@organization.org" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold mb-1.5">Phone Number</label>
                        <input name="phone" required placeholder="e.g. 255XXXXXXXXX" class="glass-input w-full rounded-2xl px-5 py-3.5 text-sm text-[#1F1F1F]">
                    </div>
                `;
            }
        }

        showStep(1);
    </script>
</body>
</html>