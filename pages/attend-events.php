<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// 1. Only logged-in users can access
if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();

// 2. Only verified users can access this page (per Blueprint)
$userStmt = $pdo->prepare("SELECT user_validity FROM user_basic_info WHERE user_id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch();

if (!$userData || $userData['user_validity'] !== 'Verified') {
    $_SESSION['error'] = 'Please verify your account to access this page.';
    redirect('pages/account.php');
}

// 3. Fetch events the user can attend: Host OR Active Attendee OR Accepted Service Provider
$stmt = $pdo->prepare("
    SELECT DISTINCT
        e.*,
        u.user_full_name,
        u.user_profile_picture,
        a.asset_location_specifics,
        a.asset_street,
        a.asset_district,
        a.asset_region
    FROM event_basic_info e
    JOIN user_basic_info u ON e.host_id = u.user_id
    LEFT JOIN user_event_asset a ON e.venue_id = a.asset_id
    LEFT JOIN event_attendees ea ON e.event_id = ea.event_id AND ea.participant_id = ?
    LEFT JOIN event_service_hiring esh ON e.event_id = esh.event_id
                                       AND esh.user_id = ?
                                       AND esh.service_status = 'Accepted'
                                       AND esh.presence_status = 'Active'
    WHERE e.event_activeness = 'In Session'
      AND (
            (ea.participation_status = 'Active')        -- Normal Attendee
         OR (e.host_id = ?)                               -- Host
         OR (esh.hire_id IS NOT NULL)                     -- Service Provider
      )
    ORDER BY e.event_date ASC, e.event_time ASC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$events = $stmt->fetchAll();

// 4. Pre-fetch user roles for each event (to enable/disable modal buttons per Blueprint)
$roles_by_event = [];
foreach ($events as $event) {
    $event_id = $event['event_id'];
    $roles = [
        'normal'  => false,
        'service' => false,
        'host'    => false
    ];

    // Check normal attendee
    $stmt2 = $pdo->prepare("SELECT 1 FROM event_attendees WHERE event_id = ? AND participant_id = ? AND participation_status = 'Active'");
    $stmt2->execute([$event_id, $user_id]);
    if ($stmt2->fetchColumn()) {
        $roles['normal'] = true;
    }

    // Check service provider (must be Accepted and Active)
    $stmt3 = $pdo->prepare("
        SELECT 1 FROM event_service_hiring 
        WHERE event_id = ? AND user_id = ? 
          AND service_status = 'Accepted' AND presence_status = 'Active'
    ");
    $stmt3->execute([$event_id, $user_id]);
    if ($stmt3->fetchColumn()) {
        $roles['service'] = true;
    }

    // Check host
    if ($event['host_id'] == $user_id) {
        $roles['host'] = true;
    }

    $roles_by_event[$event_id] = $roles;
}

// (Optional) Log page entrance – not implemented but can be added if user_usage_track exists
// logPageTransition('Events page', 'Attend Events page'); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attend Events - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .event-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .event-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
        .modal-overlay {
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(6px);
        }
        .role-btn {
            transition: all 0.2s;
        }
        .role-btn:hover:not(:disabled) {
            transform: scale(1.02);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
        }
        .role-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Attend Events</h1>
        <a href="events.php" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-arrow-left"></i> All Events
        </a>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800">Your Confirmed Events</h2>
        <p class="text-gray-600">Events you are invited to that are currently in session</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="eventsGrid">
        <?php if (empty($events)): ?>
            <div class="col-span-full glass rounded-3xl p-12 text-center">
                <p class="text-gray-500 text-lg">You have no confirmed events in session right now.</p>
                <a href="events.php" class="inline-block mt-4 text-indigo-600 hover:underline">Browse all events</a>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): 
                $event_id = $event['event_id'];
                $roles = $roles_by_event[$event_id];
                // Build location string as per Blueprint: concatenate specifics, street, district, region
                $location_parts = array_filter([
                    $event['asset_location_specifics'],
                    $event['asset_street'],
                    $event['asset_district'],
                    $event['asset_region']
                ]);
                $location = implode(', ', $location_parts) ?: 'Location not specified';
            ?>
                <div class="event-card glass rounded-3xl overflow-hidden">
                    <div class="h-48 bg-gradient-to-br from-indigo-500 to-purple-600 relative">
                        <div class="absolute top-4 right-4 bg-white/90 text-indigo-700 text-xs font-semibold px-3 py-1 rounded-2xl">
                            <?= htmlspecialchars($event['event_category']) ?>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <img src="<?= htmlspecialchars(getProfilePictureUrl($event['user_profile_picture'] ?? '')) ?>" 
                                 class="w-10 h-10 rounded-full object-cover border-2 border-white" alt="">
                            <div class="text-sm">
                                <p class="font-semibold"><?= htmlspecialchars($event['user_full_name']) ?></p>
                                <p class="text-gray-500">Host</p>
                            </div>
                        </div>

                        <h3 class="font-semibold text-xl leading-tight mb-2"><?= htmlspecialchars($event['event_title']) ?></h3>
                        
                        <div class="space-y-1 text-sm text-gray-600 mb-4">
                            <div><i class="fa fa-calendar w-5"></i> <?= date('d M Y', strtotime($event['event_date'])) ?></div>
                            <div><i class="fa fa-clock w-5"></i> <?= date('H:i', strtotime($event['event_time'])) ?></div>
                            <div><i class="fa fa-map-pin w-5"></i> <?= htmlspecialchars($location) ?></div>
                        </div>

                        <button onclick="openModal(<?= $event_id ?>)" 
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-semibold transition">
                            Proceed
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for role selection (exactly as per Blueprint) -->
<div id="roleModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-sm w-full mx-4 shadow-2xl transform transition-all">
        <h3 class="text-2xl font-bold text-center text-gray-800 mb-2">Enter Event</h3>
        <p class="text-center text-gray-500 text-sm mb-6">Choose your entry role</p>
        <div class="space-y-4">
            <button id="btnNormal" class="role-btn w-full bg-blue-500 hover:bg-blue-600 text-white py-4 rounded-2xl font-semibold transition flex items-center justify-center gap-2" disabled>
                <i class="fa fa-user"></i> Enter as normal invitee
            </button>
            <button id="btnService" class="role-btn w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-2xl font-semibold transition flex items-center justify-center gap-2" disabled>
                <i class="fa fa-briefcase"></i> Enter as service provider
            </button>
            <button id="btnHost" class="role-btn w-full bg-purple-500 hover:bg-purple-600 text-white py-4 rounded-2xl font-semibold transition flex items-center justify-center gap-2" disabled>
                <i class="fa fa-crown"></i> Enter as event host
            </button>
        </div>
        <button onclick="closeModal()" class="mt-6 w-full text-gray-500 hover:text-gray-700 text-sm font-medium">
            Cancel
        </button>
    </div>
</div>

<!-- Sticky footer navigation (matches Events page tabs) -->
<footer class="glass fixed bottom-0 left-0 right-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-around text-sm font-medium">
        <a href="events.php?tab=all" class="text-gray-700 hover:text-indigo-700">All Events</a>
        <a href="events.php?tab=area" class="text-gray-700 hover:text-indigo-700">Your Area</a>
        <a href="events.php?tab=invitations" class="text-gray-700 hover:text-indigo-700">Your Invitations</a>
    </div>
</footer>

<script>
    let currentEventId = null;
    const modal = document.getElementById('roleModal');
    const btnNormal = document.getElementById('btnNormal');
    const btnService = document.getElementById('btnService');
    const btnHost = document.getElementById('btnHost');

    const rolesData = <?= json_encode($roles_by_event) ?>;

    function openModal(eventId) {
        currentEventId = eventId;
        const roles = rolesData[eventId] || { normal: false, service: false, host: false };

        btnNormal.disabled = !roles.normal;
        btnService.disabled = !roles.service;
        btnHost.disabled = !roles.host;

        btnNormal.onclick = function() {
            if (!this.disabled) window.location.href = `event-page.php?id=${eventId}`;
        };
        btnService.onclick = function() {
            if (!this.disabled) window.location.href = `event-page.php?id=${eventId}`;
        };
        btnHost.onclick = function() {
            if (!this.disabled) window.location.href = `event-page.php?id=${eventId}`;
        };

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        currentEventId = null;
    }

    // Close modal on background click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
</script>

</body>
</html>