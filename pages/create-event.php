<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

// Only verified users can create events
$user = getCurrentUser();
if ($user['user_validity'] !== 'Verified') {
    // Show error and redirect or display message
    errorMsg("Please verify your account to access this page.");
    redirect('pages/update-profile.php');
}

// Fetch regions and districts for venue filter (preload)
$regions_stmt = $pdo->query("SELECT DISTINCT asset_region FROM user_event_asset WHERE asset_category = 'Venue' AND asset_status = 'Available' ORDER BY asset_region");
$regions = $regions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Preload all available venues (for Step 4)
$venues_stmt = $pdo->prepare("
    SELECT a.*, u.user_full_name, u.user_id 
    FROM user_event_asset a
    JOIN user_basic_info u ON a.owner_id = u.user_id
    WHERE a.asset_category = 'Venue' 
      AND a.asset_status = 'Available' 
      AND u.user_validity = 'Verified'
");
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

// For step 3, we need categories list
$categories = [
    'Anniversary', 'Baby showers', 'Bachelor party', 'Birthday', 'Bridal shower',
    'Carnival', 'Charity gala', 'Concert', 'Conferences', 'Convention',
    'Corporate mixer', 'Dance party', 'Engagement party', 'Exhibition', 'Fair',
    'Family reunion', 'Festival', 'Funeral', 'Game night', 'Graduation',
    'Grand opening', 'Launch party', 'Mass', 'Music festival', 'Opera',
    'Other parties', 'Picnic', 'Prom', 'Potluck', 'Retirement party',
    'School reunion', 'Sporting event', 'Surprise party', 'Trade show',
    'Wedding', 'Workers party'
];
$participation_types = ['Single', 'Double', 'Triple', 'Quad', 'Quint', 'Sextuple', 'Septuple', 'Octuple', 'Nonuple', 'Decuple'];
$badges = ['Normal', 'VIP', 'VVIP', 'Special VIP', 'Super VIP'];
// Current step and event record tracking
$current_step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$event_id = clean($_GET['event_id'] ?? '');
// Load event data for prefill if available
$event = null;
if ($event_id) {
    $stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE event_id = ? AND host_id = ?");
    $stmt->execute([$event_id, getCurrentUserId()]);
    $event = $stmt->fetch();
    if (!$event) {
        $event_id = '';
    }
}
// Load related data for prefill (ad media, fundraise, tags, rentals)
$ad_images = null;
$ad_video = null;
$fundraise = null;
$fundraise_tags = [];
$existing_rentals = [];
if ($event_id) {
    $imgS = $pdo->prepare("SELECT * FROM event_ad_images WHERE event_id = ? LIMIT 1");
    $imgS->execute([$event_id]);
    $ad_images = $imgS->fetch();

    $vidS = $pdo->prepare("SELECT * FROM event_ad_video WHERE event_id = ? LIMIT 1");
    $vidS->execute([$event_id]);
    $ad_video = $vidS->fetch();

    $fundS = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE event_id = ? LIMIT 1");
    $fundS->execute([$event_id]);
    $fundraise = $fundS->fetch();

    if ($fundraise) {
        $tagS = $pdo->prepare("SELECT * FROM event_fundraise_tags WHERE event_id = ? AND fundraise_id = ?");
        $tagS->execute([$event_id, $fundraise['fundraise_id']]);
        $fundraise_tags = $tagS->fetchAll(PDO::FETCH_ASSOC);
    }

    $rentS = $pdo->prepare("SELECT * FROM event_asset_rentals WHERE event_id = ?");
    $rentS->execute([$event_id]);
    $existing_rentals = $rentS->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.25); }
        .step { display: none; }
        .step.active { display: block; }
        .toggle-switch { position: relative; width: 60px; height: 30px; background: #ccc; border-radius: 30px; cursor: pointer; transition: 0.3s; }
        .toggle-switch.active { background: #4F46E5; }
        .toggle-switch .slider { position: absolute; top: 3px; left: 3px; width: 24px; height: 24px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-switch.active .slider { left: 33px; }
        .repeatable-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
        .repeatable-row select, .repeatable-row input { flex: 1; min-width: 120px; }
        .remove-row { color: red; cursor: pointer; }
        .venue-card { padding: 15px; border-radius: 20px; margin-bottom: 10px; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 2rem; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-box label { display: block; margin-bottom: 0.3rem; font-weight: 500; }
        .modal-box input, .modal-box select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 12px; margin-bottom: 1rem; }
        .modal-box .readonly { background: #f3f4f6; }
        .filter-group { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group input, .filter-group select { flex: 1; min-width: 150px; padding: 10px; border-radius: 12px; border: 1px solid #ddd; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Create New Event</h1>
        <button onclick="history.back()" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-times"></i>
        </button>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
    <form id="create-event-form" method="POST" action="create-event_process.php" enctype="multipart/form-data">
        <!-- Hidden fields to track step and data -->
        <input type="hidden" name="current_step" id="current_step" value="<?= $current_step ?>">
        <input type="hidden" name="event_id" id="event_id" value="<?= htmlspecialchars($event_id) ?>">
        <input type="hidden" name="participation_fee" id="participation_fee" value="<?= $feeOn ? 'Present' : 'Absent' ?>">
        <input type="hidden" name="venue_rentals" id="venue_rentals" value="">

        <!-- Progress Indicator -->
        <div class="flex justify-between items-center mb-8">
            <span class="text-sm text-gray-600">Step <span id="step-number"><?= $current_step ?></span> of 4</span>
            <div class="flex-1 mx-4 h-1 bg-gray-300 rounded">
                <div id="progress-bar" class="h-1 bg-indigo-600 rounded" style="width: <?= ($current_step / 4) * 100 ?>%;"></div>
            </div>
        </div>

        <!-- STEP 1 -->
        <div id="step-1" class="step<?= $current_step === 1 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 1: Event Basics</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Event Type</label>
                    <select name="event_type" required class="glass w-full rounded-2xl px-5 py-4">
                        <option value="Public"<?= (isset($event['event_type']) && $event['event_type']==='Public')? ' selected' : '' ?>>Public</option>
                        <option value="Private"<?= (isset($event['event_type']) && $event['event_type']==='Private')? ' selected' : '' ?>>Private</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Event Category</label>
                    <select name="event_category" required class="glass w-full rounded-2xl px-5 py-4">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"<?= (isset($event['event_category']) && $event['event_category']=== $cat) ? ' selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end mt-8">
                <button type="button" onclick="saveAndProceed(1)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Save and proceed</button>
            </div>
        </div>

        <!-- STEP 2 -->
        <div id="step-2" class="step<?= $current_step === 2 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 2: Event Details</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Event Title</label>
                    <input type="text" name="event_title" required class="glass w-full rounded-2xl px-5 py-4" placeholder="e.g. Birthday ya Mjukuu" value="<?= htmlspecialchars($event['event_title'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Extra Details (optional, you can @mention users)</label>
                    <textarea name="event_extra_detail" rows="3" class="glass w-full rounded-2xl px-5 py-4" placeholder="More details..."><?= htmlspecialchars($event['event_extra_detail'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Advertisement Media</label>
                    <select name="event_ad_media" id="event_ad_media" class="glass w-full rounded-2xl px-5 py-4 mb-2">
                        <option value="Image"<?= (isset($event['event_ad_media']) && $event['event_ad_media']==='Image')? ' selected' : '' ?>>Image / Slideshow (up to 4 images)</option>
                        <option value="Video"<?= (isset($event['event_ad_media']) && $event['event_ad_media']==='Video')? ' selected' : '' ?>>Video (1 video)</option>
                    </select>
                    <input type="file" name="ad_media[]" id="ad_media" accept="image/*,video/*,.mkv" multiple class="glass w-full rounded-2xl px-5 py-4">
                    <p class="text-xs text-gray-500 mt-1">If Image, select up to 4 images; if Video, select one .mp4 or .mkv file.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Event Date (must be at least 1 week from now)</label>
                        <input type="date" name="event_date" id="event_date" required class="glass w-full rounded-2xl px-5 py-4" value="<?= htmlspecialchars($event['event_date'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Starting Time</label>
                        <input type="time" name="event_time" required class="glass w-full rounded-2xl px-5 py-4" value="<?= htmlspecialchars($event['event_time'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Event Duration</label>
                    <select name="event_duration" id="event_duration" class="glass w-full rounded-2xl px-5 py-4">
                        <option value="1">1 day</option>
                        <option value="2">2 days</option>
                        <option value="3">3 days</option>
                        <option value="4">4 days</option>
                        <option value="5">5 days</option>
                    </select>
                    <input type="hidden" name="termination_date" id="termination_date">
                    <input type="hidden" name="termination_time" id="termination_time" value="">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Number of Tickets</label>
                    <input type="number" name="event_tickets" required min="1" value="<?= htmlspecialchars($event['event_tickets'] ?? 100) ?>" class="glass w-full rounded-2xl px-5 py-4">
                </div>
            </div>
            <div class="flex justify-end mt-8">
                <button type="button" onclick="saveAndProceed(2)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Save and proceed</button>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step-3" class="step<?= $current_step === 3 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 3: Entry Fee & Participation Options</h2>
            <?php
                $feeOn = false;
                if ((isset($event['participation_fee']) && $event['participation_fee'] === 'Present') || !empty($fundraise)) {
                    $feeOn = true;
                }
            ?>
            <div class="mb-6">
                <p class="text-gray-700">Will this event have an entry fee for the participants?</p>
                <div class="flex items-center gap-4 mt-2">
                    <span class="text-sm">No</span>
                    <div id="fee-toggle" class="toggle-switch <?= $feeOn ? 'active' : '' ?>" onclick="toggleFee()">
                        <div class="slider"></div>
                    </div>
                    <span class="text-sm">Yes</span>
                </div>
            </div>
            <div id="fee-options" style="display: <?= $feeOn ? 'block' : 'none' ?>;">
                <p class="text-sm text-gray-600 mb-4">Define participation packages (repeatable)</p>
                <div id="fee-rows">
                    <?php if (!empty($fundraise_tags)): ?>
                        <?php foreach ($fundraise_tags as $tag): ?>
                            <div class="repeatable-row">
                                <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
                                    <option value="">Type</option>
                                    <?php foreach ($participation_types as $pt): ?>
                                        <option value="<?= htmlspecialchars($pt) ?>"<?= ($tag['tag_name'] === $pt) ? ' selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
                                    <option value="">Badge</option>
                                    <?php foreach ($badges as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>"<?= ($tag['tag_details'] === $b) ? ' selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" class="glass rounded-xl px-4 py-2" step="0.01" value="<?= htmlspecialchars($tag['required_amount'] ?? '') ?>">
                                <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="repeatable-row">
                            <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
                                <option value="">Type</option>
                                <?php foreach ($participation_types as $pt): ?>
                                    <option value="<?= htmlspecialchars($pt) ?>"><?= htmlspecialchars($pt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
                                <option value="">Badge</option>
                                <?php foreach ($badges as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" class="glass rounded-xl px-4 py-2" step="0.01">
                            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addFeeRow()" class="text-indigo-600 text-sm mt-2"><i class="fa fa-plus"></i> Add participation type</button>
            </div>
            <div class="flex justify-end mt-8">
                <button type="button" onclick="saveAndProceed(3)" class="bg-indigo-600 text-white px-6 py-3 rounded-full">Save and proceed</button>
            </div>
        </div>

        <!-- STEP 4 -->
        <div id="step-4" class="step<?= $current_step === 4 ? ' active' : '' ?>">
            <h2 class="text-xl font-semibold mb-4">Step 4: Venue Selection (Optional)</h2>
            <p class="text-sm text-gray-600 mb-4">You may select a venue for your event. You can also skip this step.</p>
            <!-- Filter -->
            <div class="filter-group">
                <input type="text" id="venue-search" placeholder="Search by owner name..." class="glass rounded-xl px-4 py-2">
                <select id="venue-region" class="glass rounded-xl px-4 py-2">
                    <option value="">All Regions</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="venue-district" class="glass rounded-xl px-4 py-2">
                    <option value="">All Districts</option>
                </select>
                <button type="button" onclick="filterVenues()" class="bg-indigo-600 text-white px-4 py-2 rounded-full">Filter</button>
            </div>
            <!-- Venues list -->
            <div id="venue-list" class="space-y-3">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="flex justify-end mt-8">
                <button type="button" onclick="saveAndProceed(4)" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-full font-semibold">Save and proceed</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal for Place Order / Negotiate -->
<div id="rental-modal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modal-title" class="text-xl font-bold mb-4">Place Order</h3>
        <form id="rental-form">
            <input type="hidden" id="rental-asset-id">
            <input type="hidden" id="rental-action" value="order">
            <label>Renting Price per Unit (TZS)</label>
            <input type="text" id="rental-price" class="readonly" readonly>
            <label>Available Quantity</label>
            <input type="text" id="rental-available" class="readonly" readonly>
            <label>Renting Quantity</label>
            <input type="number" id="rental-quantity" min="1" required>
            <label>Total Amount (TZS)</label>
            <input type="text" id="rental-total" class="readonly" readonly>
            <div id="negotiate-field" style="display:none;">
                <label>Suggest New Renting Price per Unit (TZS)</label>
                <input type="number" id="rental-suggest-price" step="0.01">
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeRentalModal()" class="px-4 py-2 bg-gray-200 rounded-full">Cancel</button>
                <button type="button" id="rental-submit-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-full">Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Submit the current step and save into database
    function saveAndProceed(step) {
        if (step < 1 || step > 4) return;
        if (!validateStep(step)) return;
        document.getElementById('current_step').value = step;
        document.getElementById('create-event-form').submit();
    }

    function validateStep(step) {
        // Basic validation per step
        if (step === 1) {
            const cat = document.querySelector('select[name="event_category"]').value;
            if (!cat) { alert('Please select an event category.'); return false; }
            return true;
        }
        if (step === 2) {
            const title = document.querySelector('input[name="event_title"]').value.trim();
            if (!title) { alert('Please enter an event title.'); return false; }
            const date = document.getElementById('event_date').value;
            if (!date) { alert('Please select an event date.'); return false; }
            // Check if date is at least 1 day from now (changed from 1 week)
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            const selected = new Date(date + 'T00:00:00');
            const diffDays = Math.ceil((selected - now) / (1000 * 60 * 60 * 24));
            if (diffDays < 1) { alert('Event date must be at least 1 day from today.'); return false; }
            const time = document.querySelector('input[name="event_time"]').value;
            if (!time) { alert('Please select starting time.'); return false; }
            // Set termination date & time based on duration
            const duration = parseInt(document.getElementById('event_duration').value);
            const termDate = new Date(selected);
            termDate.setDate(termDate.getDate() + duration - 1);
            document.getElementById('termination_date').value = termDate.toISOString().split('T')[0];
            document.getElementById('termination_time').value = time;
            return true;
        }
        if (step === 3) {
            // If fee toggle is on, validate that at least one row has data
            const feeOn = document.getElementById('fee-toggle').classList.contains('active');
            if (feeOn) {
                const rows = document.querySelectorAll('#fee-rows .repeatable-row');
                let valid = false;
                rows.forEach(row => {
                    const type = row.querySelector('select[name="participation_type[]"]').value;
                    const badge = row.querySelector('select[name="participation_badge[]"]').value;
                    const amount = row.querySelector('input[name="participation_amount[]"]').value;
                    if (type && badge && amount) valid = true;
                });
                if (!valid) { alert('Please add at least one complete participation package.'); return false; }
            }
            return true;
        }
        return true;
    }

    // Fee toggle
    function toggleFee() {
        const toggle = document.getElementById('fee-toggle');
        toggle.classList.toggle('active');
        const options = document.getElementById('fee-options');
        if (toggle.classList.contains('active')) {
            options.style.display = 'block';
            document.getElementById('participation_fee').value = 'Present';
        } else {
            options.style.display = 'none';
            document.getElementById('participation_fee').value = 'Absent';
        }
    }

    // Add fee row
    function addFeeRow() {
        const container = document.getElementById('fee-rows');
        const row = document.createElement('div');
        row.className = 'repeatable-row';
        row.innerHTML = `
            <select name="participation_type[]" class="glass rounded-xl px-4 py-2">
                <option value="">Type</option>
                <?php foreach ($participation_types as $pt): ?>
                    <option value="<?= htmlspecialchars($pt) ?>"><?= htmlspecialchars($pt) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="participation_badge[]" class="glass rounded-xl px-4 py-2">
                <option value="">Badge</option>
                <?php foreach ($badges as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="participation_amount[]" placeholder="Amount (TZS)" class="glass rounded-xl px-4 py-2" step="0.01">
            <span class="remove-row" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></span>
        `;
        container.appendChild(row);
    }

    // Venue population and filtering
    let allVenues = <?= json_encode($venues) ?>;

    function populateVenues() {
        filterVenues();
        // Also populate district dropdown based on region selection
        document.getElementById('venue-region').addEventListener('change', function() {
            const region = this.value;
            const districtSelect = document.getElementById('venue-district');
            districtSelect.innerHTML = '<option value="">All Districts</option>';
            if (region) {
                const districts = [...new Set(allVenues.filter(v => v.asset_region === region).map(v => v.asset_district))];
                districts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    districtSelect.appendChild(opt);
                });
            }
            filterVenues();
        });
        document.getElementById('venue-search').addEventListener('input', filterVenues);
        document.getElementById('venue-district').addEventListener('change', filterVenues);
    }

    function filterVenues() {
        const search = document.getElementById('venue-search').value.toLowerCase();
        const region = document.getElementById('venue-region').value;
        const district = document.getElementById('venue-district').value;
        const filtered = allVenues.filter(v => {
            const nameMatch = v.user_full_name.toLowerCase().includes(search);
            const regionMatch = region === '' || v.asset_region === region;
            const districtMatch = district === '' || v.asset_district === district;
            return nameMatch && regionMatch && districtMatch;
        });
        renderVenues(filtered);
    }

    function renderVenues(venues) {
        const container = document.getElementById('venue-list');
        if (venues.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-4">No venues available matching your filters.</p>';
            return;
        }
        container.innerHTML = '';
        venues.forEach(v => {
            const card = document.createElement('div');
            card.className = 'glass venue-card';
            card.innerHTML = `
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-semibold">${v.user_full_name}</p>
                        <p class="text-sm">${v.asset_name} (${v.asset_quality})</p>
                        <p class="text-sm text-gray-600">Price: TZS ${parseFloat(v.asset_price).toFixed(2)} per unit</p>
                        <p class="text-xs text-gray-500">${v.asset_region}, ${v.asset_district}, ${v.asset_street}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="openRentalModal('${v.asset_id}', '${v.asset_price}', ${v.asset_quantity}, 'order')" class="bg-indigo-600 text-white px-4 py-2 rounded-full text-sm">Place Order</button>
                        <button type="button" onclick="openRentalModal('${v.asset_id}', '${v.asset_price}', ${v.asset_quantity}, 'negotiate')" class="bg-yellow-500 text-white px-4 py-2 rounded-full text-sm">Negotiate</button>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    // Rental Modal
    let currentRentalAssetId = null;

    function openRentalModal(assetId, price, quantity, action) {
        currentRentalAssetId = assetId;
        document.getElementById('rental-asset-id').value = assetId;
        document.getElementById('rental-action').value = action;
        document.getElementById('rental-price').value = parseFloat(price).toFixed(2);
        document.getElementById('rental-available').value = quantity;
        document.getElementById('rental-quantity').value = 1;
        document.getElementById('rental-total').value = price;
        if (action === 'negotiate') {
            document.getElementById('modal-title').textContent = 'Negotiate Price';
            document.getElementById('negotiate-field').style.display = 'block';
            document.getElementById('rental-submit-btn').textContent = 'Plead';
            document.getElementById('rental-suggest-price').value = price;
        } else {
            document.getElementById('modal-title').textContent = 'Place Order';
            document.getElementById('negotiate-field').style.display = 'none';
            document.getElementById('rental-submit-btn').textContent = 'Request';
        }
        document.getElementById('rental-modal').classList.add('active');
        // Update total when quantity changes
        document.getElementById('rental-quantity').addEventListener('input', updateRentalTotal);
        if (action === 'negotiate') {
            document.getElementById('rental-suggest-price').addEventListener('input', updateRentalTotal);
        }
        updateRentalTotal();
    }

    function updateRentalTotal() {
        const qty = parseInt(document.getElementById('rental-quantity').value) || 0;
        const action = document.getElementById('rental-action').value;
        let pricePerUnit;
        if (action === 'negotiate') {
            pricePerUnit = parseFloat(document.getElementById('rental-suggest-price').value) || 0;
        } else {
            pricePerUnit = parseFloat(document.getElementById('rental-price').value) || 0;
        }
        const total = qty * pricePerUnit;
        document.getElementById('rental-total').value = total.toFixed(2);
    }

    function closeRentalModal() {
        document.getElementById('rental-modal').classList.remove('active');
    }

    // Submit rental (adds to hidden field)
    document.getElementById('rental-submit-btn').addEventListener('click', function() {
        const assetId = document.getElementById('rental-asset-id').value;
        const qty = parseInt(document.getElementById('rental-quantity').value);
        const available = parseInt(document.getElementById('rental-available').value);
        if (qty > available) {
            alert('Please enter a quantity less than or equal to the available quantity.');
            return;
        }
        const action = document.getElementById('rental-action').value;
        let pricePerUnit;
        if (action === 'negotiate') {
            pricePerUnit = parseFloat(document.getElementById('rental-suggest-price').value);
            if (!pricePerUnit || pricePerUnit <= 0) { alert('Please enter a valid suggested price.'); return; }
        } else {
            pricePerUnit = parseFloat(document.getElementById('rental-price').value);
        }
        const total = qty * pricePerUnit;
        // Build rental data object
        const rental = {
            asset_id: assetId,
            action: action,
            renting_price: pricePerUnit,
            rented_quantity: qty,
            total_renting_price: total
        };
        // Append to hidden field as JSON array
        let rentals = [];
        const hidden = document.getElementById('venue_rentals');
        if (hidden.value) {
            try { rentals = JSON.parse(hidden.value); } catch(e) { rentals = []; }
        }
        rentals.push(rental);
        hidden.value = JSON.stringify(rentals);
        closeRentalModal();
        alert('Rental request added successfully!');
    });

    // Close modal on overlay click
    document.getElementById('rental-modal').addEventListener('click', function(e) {
        if (e.target === this) closeRentalModal();
    });

    // Handle file input restriction (images vs video)
    document.getElementById('event_ad_media').addEventListener('change', function() {
        const input = document.getElementById('ad_media');
        if (this.value === 'Image') {
            input.setAttribute('accept', 'image/*');
            input.removeAttribute('multiple');
            // Actually we need multiple for images, but we'll handle via JS
            input.setAttribute('multiple', 'multiple');
            input.setAttribute('data-max', '4');
        } else {
            input.setAttribute('accept', 'video/*');
            input.removeAttribute('multiple');
            input.removeAttribute('data-max');
        }
    });

    // Validate file upload on submit
    document.getElementById('create-event-form').addEventListener('submit', function(e) {
        const mediaType = document.getElementById('event_ad_media').value;
        const files = document.getElementById('ad_media').files;
        if (mediaType === 'Image') {
            if (files.length > 4) {
                alert('You can upload a maximum of 4 images.');
                e.preventDefault();
                return;
            }
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    if (!files[i].type.startsWith('image/')) {
                        alert('Please select only image files.');
                        e.preventDefault();
                        return;
                    }
                }
            }
        } else if (mediaType === 'Video') {
            if (files.length > 1) {
                alert('You can upload only 1 video.');
                e.preventDefault();
                return;
            }
            if (files.length === 1 && !files[0].type.startsWith('video/')) {
                alert('Please select a video file.');
                e.preventDefault();
                return;
            }
        }
    });

    // No client-side step initialization needed.
    // Initialize UI from server-side data
    (function initFromServer(){
        // participation fee
        var feeOn = <?= $feeOn ? 'true' : 'false' ?>;
        var feeToggle = document.getElementById('fee-toggle');
        var feeHidden = document.getElementById('participation_fee');
        if (feeOn) {
            feeToggle.classList.add('active');
            document.getElementById('fee-options').style.display = 'block';
            feeHidden.value = 'Present';
        } else {
            feeToggle.classList.remove('active');
            document.getElementById('fee-options').style.display = 'none';
            feeHidden.value = 'Absent';
        }

        // set duration if termination_date present
        var evtDate = document.getElementById('event_date').value;
        var termDate = document.getElementById('termination_date').value;
        if (evtDate && termDate) {
            var d1 = new Date(evtDate);
            var d2 = new Date(termDate);
            var diff = Math.round((d2 - d1) / (1000*60*60*24)) + 1;
            var sel = document.getElementById('event_duration');
            for (var i=0;i<sel.options.length;i++){
                if (parseInt(sel.options[i].value) === diff) sel.selectedIndex = i;
            }
        }

        // populate existing rentals if any
        var existing = <?= json_encode($existing_rentals) ?>;
        if (existing && existing.length) {
            document.getElementById('venue_rentals').value = JSON.stringify(existing.map(function(r){
                return {asset_id: r.asset_id, renting_price: r.renting_price, rented_quantity: r.rented_quantity, total_renting_price: r.total_renting_price};
            }));
        }
    })();
</script>
</body>
</html>