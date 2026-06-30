<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();
$event_id = (int)($_GET['id'] ?? 0);

// Count unread notifications
$notificationCount = 0;

// 1. Verification notification
$stmt = $pdo->prepare("SELECT user_validity FROM user_basic_info WHERE user_id = ?");
$stmt->execute([$user_id]);
$userValidity = $stmt->fetchColumn();
if ($userValidity == 'Registered') {
    $notificationCount++;
}

// 2. Asset rental notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM event_asset_rentals er
    JOIN user_event_asset uea ON er.asset_id = uea.asset_id
    WHERE uea.owner_id = ? AND er.lending_status = 'Pending'
");
$stmt->execute([$user_id]);
$notificationCount += $stmt->fetchColumn();

// 3. Service hiring notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM event_service_hiring
    WHERE user_id = ? AND hire_status = 'Requested' AND service_status = 'Pending'
");
$stmt->execute([$user_id]);
$notificationCount += $stmt->fetchColumn();

// 4. Incoming funds notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM fundraise_user_transactions
    WHERE user_id = ? AND transaction_permission = 'Allowed' AND acceptance_status = 'Waiting'
");
$stmt->execute([$user_id]);
$notificationCount += $stmt->fetchColumn();

// 5. Asset return notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM event_asset_returns eret
    JOIN event_asset_rentals er ON eret.rental_id = er.rental_id
    JOIN user_event_asset uea ON er.asset_id = uea.asset_id
    WHERE uea.owner_id = ? AND eret.reception_status = 'Waiting'
");
$stmt->execute([$user_id]);
$notificationCount += $stmt->fetchColumn();

if ($event_id <= 0) {
    errorMsg("Invalid Event");
    redirect('pages/events.php');
}

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM event_basic_info WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    errorMsg("Event not found");
    redirect('pages/events.php');
}

// Must be In Session
if ($event['event_activeness'] != 'In Session') {
    errorMsg("This event is not currently in session.");
    redirect('pages/events.php');
}

// Determine user role for this event
$isHost   = ($event['host_id'] == $user_id);
$isService = false;
$isNormal  = false;

// 1. Normal Attendee
$stmt = $pdo->prepare("
    SELECT 1 FROM event_attendees 
    WHERE event_id = ? AND participant_id = ? AND participation_status = 'Active'
");
$stmt->execute([$event_id, $user_id]);
if ($stmt->fetchColumn()) {
    $isNormal = true;
}

// 2. Service Provider (Accepted + Active) — Simplified per Blueprint practicality
$stmt = $pdo->prepare("
    SELECT 1 FROM event_service_hiring 
    WHERE event_id = ? AND user_id = ? 
      AND service_status = 'Accepted' AND presence_status = 'Active'
");
$stmt->execute([$event_id, $user_id]);
if ($stmt->fetchColumn()) {
    $isService = true;
}

// 3. Host
if ($isHost) {
    // Host always has full access
}

// If none of the roles, redirect
if (!$isHost && !$isService && !$isNormal) {
    errorMsg("You are not authorized to access this event page.");
    redirect('pages/attend-events.php');
}

// Determine which header buttons to show
$showServices = $isNormal;
$showManage   = $isHost;
$showServe    = $isService;

// Fetch announcements for Venue tab
$stmt = $pdo->prepare("
    SELECT a.*, u.user_full_name, ei.invitation_badge 
    FROM event_announcements a
    JOIN user_basic_info u ON a.user_id = u.user_id
    LEFT JOIN event_invitees ei ON a.invitee_id = ei.invitee_id
    WHERE a.event_id = ?
    ORDER BY a.announcing_datetime DESC
");
$stmt->execute([$event_id]);
$announcements = $stmt->fetchAll();

// Fetch schedule for Venue tab (for modal)
$stmt = $pdo->prepare("
    SELECT s.*, GROUP_CONCAT(u.user_full_name SEPARATOR ', ') as participants
    FROM event_schedule_timetable s
    LEFT JOIN event_schedule_participants sp ON s.schedule_id = sp.schedule_id
    LEFT JOIN user_basic_info u ON sp.user_id = u.user_id
    WHERE s.event_id = ? AND s.schedule_status = 'On schedule'
    GROUP BY s.schedule_id
    ORDER BY s.schedule_start_time
");
$stmt->execute([$event_id]);
$scheduleItems = $stmt->fetchAll();

// Fetch fundraises for Fundraises tab (Active, Mid-event)
$stmt = $pdo->prepare("
    SELECT * FROM event_fundraise_info 
    WHERE event_id = ? 
      AND fundraise_status = 'Active' 
      AND fundraise_duration = 'Mid-event'
");
$stmt->execute([$event_id]);
$fundraises = $stmt->fetchAll();

// Fetch chat data for Chats tab
// Group chat
$groupChatId = $event['groupchat_id'];
$groupChatEnabled = ($event['groupchat_permission'] == 'Unlocked' && $groupChatId !== null);
$groupUnread = 0;
if ($groupChatEnabled) {
    // Count unread messages (WhatsApp technique)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM event_groupchat_messages 
        WHERE chat_id = ? AND sender_id != ? 
          AND groupchat_message_id > COALESCE(
              (SELECT MAX(last_read_message_id) FROM event_groupchat_participants 
               WHERE chat_id = ? AND reader_id = ?), 0)
    ");
    $stmt->execute([$groupChatId, $user_id, $groupChatId, $user_id]);
    $groupUnread = $stmt->fetchColumn();
}

// Private chats – users who have sent messages to this user (unread) or have existing chat history
// We'll show cards for users with unread messages, and also search functionality.
// For simplicity, we'll list users who have unread messages for the current user.
// Private chats – users who have sent messages to this user
$privateChats = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END as other_user_id,
        (SELECT COUNT(*) FROM event_privatechat_messages 
         WHERE (sender_id = ? AND receiver_id = other_user_id 
            OR sender_id = other_user_id AND receiver_id = ?) 
           AND message_status = 'Unread') as unread_count
    FROM event_privatechat_messages 
    WHERE (sender_id = ? OR receiver_id = ?) 
      AND event_id = ?
    GROUP BY other_user_id
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $event_id]);
$privateChatRows = $stmt->fetchAll();

// Get user details
$otherUserIds = array_column($privateChatRows, 'other_user_id');
if (!empty($otherUserIds)) {
    $placeholders = implode(',', array_fill(0, count($otherUserIds), '?'));
    $stmt = $pdo->prepare("SELECT user_id, user_full_name, user_profile_picture FROM user_basic_info WHERE user_id IN ($placeholders)");
    $stmt->execute($otherUserIds);
    $userInfoMap = [];
    while ($row = $stmt->fetch()) {
        $userInfoMap[$row['user_id']] = $row;
    }
    foreach ($privateChatRows as &$pc) {
        $pc['user'] = $userInfoMap[$pc['other_user_id']] ?? null;
    }
    unset($pc);
    $privateChats = array_filter($privateChatRows, function($pc) { 
        return $pc['user'] !== null; 
    });
}

// For search, we'll need all event participants for the search bar; we'll handle via AJAX, but we can also preload a list.
// We'll just provide a search input and let JS handle dynamic filtering from the list of chat cards.

// Fetch gallery media
$stmt = $pdo->prepare("
    SELECT esm.*, u.user_full_name 
    FROM event_shared_media esm
    JOIN user_basic_info u ON esm.uploader_id = u.user_id
    WHERE esm.event_id = ? AND esm.media_validity = 'Valid'
    ORDER BY esm.upload_datetime DESC
");
$stmt->execute([$event_id]);
$galleryMedia = $stmt->fetchAll();

// Handle media upload
if (isset($_POST['upload_media']) && isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
    $file = $_FILES['media_file'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
    if (in_array($file['type'], $allowed)) {
        $upload_dir = '../uploads/event_media/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $destination = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $media_type = (strpos($file['type'], 'video') !== false) ? 'Video' : 'Photo';
            $media_id = 'MEDIA-' . strtoupper(bin2hex(random_bytes(5)));
            $stmt = $pdo->prepare("INSERT INTO event_shared_media (media_id, event_id, media_type, uploader_id, media_file) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$media_id, $event_id, $media_type, $user_id, $destination]);
            // Redirect to avoid re-submission
            redirect("pages/event-page.php?id=" . $event_id);
        }
    } else {
        errorMsg("Invalid file type. Only images and videos are allowed.");
    }
}

// Handle AJAX search for event participants
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $search_term = clean($_GET['search_term'] ?? '');
    
    if (strlen($search_term) < 2) {
        echo json_encode([]);
        exit;
    }
    
    // Search for active attendees and confirmed invitees
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.user_full_name, u.user_profile_picture
        FROM user_basic_info u
        WHERE u.user_id != ?
          AND u.user_validity = 'Verified'
          AND (
              -- Active attendees
              u.user_id IN (
                  SELECT participant_id FROM event_attendees 
                  WHERE event_id = ? AND participation_status = 'Active'
              )
              OR
              -- Confirmed invitees
              u.user_id IN (
                  SELECT user_id FROM event_invitees 
                  WHERE event_id = ? AND attendance_status = 'Confirmed'
              )
          )
          AND u.user_full_name LIKE ?
        ORDER BY u.user_full_name
        LIMIT 10
    ");
    $stmt->execute([$user_id, $event_id, $event_id, "%$search_term%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    exit;
}

// Handle AJAX actions (if needed) – but we can keep it simple with POST forms

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['event_title']) ?> - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .tab-active { border-bottom: 4px solid #6366f1; color: #6366f1; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .chat-card { transition: all 0.2s; }
        .chat-card:hover { transform: scale(1.01); }
        .badge-unread { background: #ef4444; color: white; border-radius: 9999px; padding: 0.1rem 0.5rem; font-size: 0.75rem; }
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .schedule-modal { max-width: 600px; width: 90%; }
        .media-grid img, .media-grid video { width: 100%; height: 200px; object-fit: cover; border-radius: 1rem; }
        .floating-upload { position: fixed; bottom: 100px; right: 20px; z-index: 30; }
        .upload-btn { width: 60px; height: 60px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: 0.2s; }
        .upload-btn:hover { transform: scale(1.1); }
        .upload-options { display: none; }
        .upload-options.show { display: flex; flex-direction: column; gap: 0.5rem; position: absolute; bottom: 70px; right: 0; }
        .upload-options button { background: white; padding: 0.5rem 1rem; border-radius: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: none; cursor: pointer; }
        .upload-options button:hover { background: #f0f0f0; }
        @media (max-width: 640px) { .floating-upload { bottom: 80px; right: 10px; } }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen pb-20">

<!-- HEADER -->
<header class="glass sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-indigo-700">EVENTUKIO</h1>
            <span class="text-sm text-gray-600 hidden sm:inline">• <?= htmlspecialchars($event['event_title']) ?></span>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <button onclick="location.href='notifications.php'" class="text-gray-700 hover:text-indigo-700 relative flex items-center gap-2">
                <i class="fa fa-bell"></i>
                Notifications
                <?php if ($notificationCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                        <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <?php if ($showServices): ?>
                <a href="rate-service.php?event_id=<?= $event_id ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-2xl text-sm">Services</a>
            <?php endif; ?>
            <?php if ($showManage): ?>
                <a href="control-event.php?id=<?= $event_id ?>" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-2xl text-sm">Manage</a>
            <?php endif; ?>
            <?php if ($showServe): ?>
                <a href="serve.php?event_id=<?= $event_id ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-2xl text-sm">Serve</a>
            <?php endif; ?>
            <button onclick="location.href='attend-events.php'" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-2xl text-sm">Leave Event</button>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 pb-2 text-center">
        <span class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($event['event_title']) ?></span>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- TABS -->
    <!-- TAB 0: Venue -->
    <div id="panel0" class="tab-panel active">
        <!-- Announcements -->
        <div class="space-y-4">
            <?php if (empty($announcements)): ?>
                <div class="glass rounded-3xl p-6 text-center text-gray-500">No announcements yet.</div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="glass rounded-3xl p-4 flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            <i class="fa fa-bullhorn text-indigo-600"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold"><?= htmlspecialchars($ann['user_full_name']) ?></span>
                                <?php if ($ann['invitation_badge']): ?>
                                    <span class="text-xs bg-gray-200 px-2 py-0.5 rounded-full"><?= htmlspecialchars($ann['invitation_badge']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1 text-gray-700"><?= nl2br(htmlspecialchars($ann['announcement_content'])) ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?= date('d M Y H:i', strtotime($ann['announcing_datetime'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Schedule button (floating) -->
        <button onclick="openScheduleModal()" class="fixed bottom-24 right-6 z-40 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-full shadow-lg flex items-center gap-2">
            <i class="fa fa-list"></i> Schedule
        </button>
    </div>

    <!-- TAB 1: Fundraises -->
    <div id="panel1" class="tab-panel">
        <div class="mb-4">
            <input type="text" id="fundraiseSearch" placeholder="Search fundraises..." class="glass w-full rounded-3xl px-6 py-3 text-lg focus:outline-none">
        </div>
        <div id="fundraiseList" class="space-y-4">
            <?php if (empty($fundraises)): ?>
                <div class="glass rounded-3xl p-6 text-center text-gray-500">No active fundraises.</div>
            <?php else: ?>
                <?php foreach ($fundraises as $fund): ?>
                    <a href="fundraise.php?event_id=<?= $event_id ?>&fundraise_id=<?= $fund['fundraise_id'] ?>" class="block glass rounded-3xl p-4 transition hover:shadow-lg">
                        <h3 class="font-semibold"><?= htmlspecialchars($fund['fundraise_title']) ?></h3>
                        <p class="text-sm text-gray-500">Type: <?= $fund['fundraise_type'] ?></p>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: Chats -->
    <div id="panel2" class="tab-panel">
        <div class="mb-4">
            <input type="text" id="chatSearch" placeholder="Search participants..." class="glass w-full rounded-3xl px-6 py-3 text-lg focus:outline-none">
        </div>
        <!-- Search Results Dropdown -->
        <div id="searchResults" class="hidden glass rounded-3xl p-2 mb-4 max-h-64 overflow-y-auto"></div>
        <div id="chatList" class="space-y-3">
            <!-- Group Chat -->
            <?php if ($groupChatEnabled): ?>
                <a href="group-chat.php?id=<?= $event_id ?>" class="chat-card glass rounded-3xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-indigo-200 flex items-center justify-center">
                            <i class="fa fa-users text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="font-semibold">Event Group Chat</p>
                        </div>
                    </div>
                    <?php if ($groupUnread > 0): ?>
                        <span class="badge-unread"><?= $groupUnread ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <div class="glass rounded-3xl p-4 text-center text-gray-500">Group chat is currently locked.</div>
            <?php endif; ?>

            <!-- Private Chats -->
            <?php if ($event['privatechat_permission'] == 'Unlocked'): ?>
                <?php if (empty($privateChats)): ?>
                    <div class="glass rounded-3xl p-4 text-center text-gray-500">No private chats yet.</div>
                <?php else: ?>
                    <?php foreach ($privateChats as $pc): ?>
                        <?php if ($pc['user']): ?>
                            <a href="private-chat.php?event_id=<?= $event_id ?>&receiver_id=<?= $pc['other_user_id'] ?>" class="chat-card glass rounded-3xl p-4 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <img src="<?= htmlspecialchars(getProfilePictureUrl($pc['user']['user_profile_picture'] ?? '')) ?>" class="w-12 h-12 rounded-full object-cover">
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($pc['user']['user_full_name']) ?></p>
                                    </div>
                                </div>
                                <?php if ($pc['unread_count'] > 0): ?>
                                    <span class="badge-unread"><?= $pc['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="glass rounded-3xl p-4 text-center text-gray-500">Private chats are currently locked.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: Gallery -->
    <div id="panel3" class="tab-panel">
        <div class="media-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php if (empty($galleryMedia)): ?>
                <div class="col-span-full glass rounded-3xl p-6 text-center text-gray-500">No media yet.</div>
            <?php else: ?>
                <?php foreach ($galleryMedia as $media): ?>
                    <div class="relative group rounded-xl overflow-hidden glass">
                        <?php if ($media['media_type'] == 'Photo'): ?>
                            <img src="<?= htmlspecialchars($media['media_file']) ?>" alt="Media" class="w-full h-48 object-cover">
                        <?php else: ?>
                            <video src="<?= htmlspecialchars($media['media_file']) ?>" class="w-full h-48 object-cover" controls></video>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs p-2">
                            <?= htmlspecialchars($media['user_full_name']) ?>
                        </div>
                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="viewMedia('<?= $media['media_id'] ?>')" class="bg-white/80 text-black p-1 rounded-full text-xs">View</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Upload button (floating) -->
        <div class="floating-upload">
            <button onclick="toggleUploadOptions()" class="upload-btn">
                <i class="fa fa-plus text-2xl"></i>
            </button>
            <div id="uploadOptions" class="upload-options">
                <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-2">
                    <input type="file" name="media_file" accept="image/*,video/*" style="display:none;" id="mediaFileInput">
                    <button type="button" onclick="document.getElementById('mediaFileInput').click()" class="bg-white px-4 py-2 rounded-full shadow">From Storage</button>
                    <button type="button" onclick="captureFromCamera()" class="bg-white px-4 py-2 rounded-full shadow">From Camera</button>
                    <!-- Hidden submit for file input -->
                    <button type="submit" name="upload_media" style="display:none;" id="uploadSubmit"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SCHEDULE MODAL -->
<div id="scheduleModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 schedule-modal max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold">Event Schedule</h3>
            <button onclick="closeScheduleModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times text-2xl"></i></button>
        </div>
        <?php if (empty($scheduleItems)): ?>
            <p class="text-gray-500">No schedule items.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($scheduleItems as $item): ?>
                    <div class="glass p-4 rounded-2xl">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold"><?= htmlspecialchars($item['schedule_title']) ?></span>
                            <span class="text-sm text-gray-500"><?= $item['schedule_start_time'] ?> – <?= $item['schedule_end_time'] ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Participants: <?= htmlspecialchars($item['participants'] ?? 'None') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button onclick="closeScheduleModal()" class="mt-6 w-full bg-indigo-600 text-white py-3 rounded-2xl">Close</button>
    </div>
</div>

<!-- MEDIA VIEW MODAL (for gallery) -->
<div id="mediaModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="glass rounded-3xl p-8 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <span id="mediaUploaderName" class="font-semibold"></span>
            <button onclick="closeMediaModal()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times text-2xl"></i></button>
        </div>
        <div id="mediaDisplay" class="text-center">
            <!-- Filled by JS -->
        </div>
        <div class="flex gap-2 mt-4">
            <a id="downloadMediaLink" href="#" download class="bg-indigo-600 text-white px-4 py-2 rounded-2xl">Download</a>
            <button onclick="closeMediaModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-2xl">Close</button>
        </div>
    </div>
</div>

<script>
    // ---------- Tab switching ----------
    function switchTab(n) {
        document.querySelectorAll('.tab').forEach((el, i) => {
            el.classList.toggle('tab-active', i === n);
        });
        document.querySelectorAll('.tab-panel').forEach((el, i) => {
            el.classList.toggle('active', i === n);
        });
    }

    // ---------- Schedule modal ----------
    function openScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    // Close modal on background click
    document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) closeScheduleModal();
    });

    // ---------- Fundraise search ----------
    document.getElementById('fundraiseSearch')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#fundraiseList > a').forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    });

    // ---------- Chat search (AJAX search for event participants) ----------
    let searchTimeout;
    document.getElementById('chatSearch')?.addEventListener('input', function() {
        const term = this.value.trim();
        const searchResults = document.getElementById('searchResults');
        
        clearTimeout(searchTimeout);
        
        if (term.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`event-page.php?id=<?= $event_id ?>&ajax_search=1&search_term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        searchResults.innerHTML = '<p class="text-gray-500 text-center py-2">No participants found.</p>';
                    } else {
                        searchResults.innerHTML = data.map(user => `
                            <a href="private-chat.php?event_id=<?= $event_id ?>&receiver_id=${user.user_id}" 
                               class="flex items-center gap-3 p-2 hover:bg-white/20 rounded-xl transition">
                                <img src="${user.user_profile_picture ? user.user_profile_picture : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.user_full_name) + '&background=random'}" 
                                     class="w-10 h-10 rounded-full object-cover">
                                <span class="font-medium">${user.user_full_name}</span>
                            </a>
                        `).join('');
                    }
                    searchResults.classList.remove('hidden');
                })
                .catch(err => {
                    console.error('Search error:', err);
                    searchResults.innerHTML = '<p class="text-red-500 text-center py-2">Error searching participants.</p>';
                    searchResults.classList.remove('hidden');
                });
        }, 300); // Debounce 300ms
    });
    
    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        const searchInput = document.getElementById('chatSearch');
        const searchResults = document.getElementById('searchResults');
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    // ---------- Gallery upload ----------
    let uploadVisible = false;
    function toggleUploadOptions() {
        uploadVisible = !uploadVisible;
        document.getElementById('uploadOptions').classList.toggle('show', uploadVisible);
    }

    // Camera capture (use input with capture attribute)
    function captureFromCamera() {
        const input = document.getElementById('mediaFileInput');
        input.setAttribute('capture', 'environment'); // or 'user'
        input.click();
    }

    // Auto-submit when file selected
    document.getElementById('mediaFileInput')?.addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('uploadSubmit').click();
        }
    });

    // ---------- Media view modal ----------
    let currentMedia = null;
    function viewMedia(mediaId) {
        // In a real app, fetch media data via AJAX. For demo, we'll use PHP data passed to JS.
        // We'll embed media data in a JS object.
        const mediaData = <?= json_encode($galleryMedia) ?>;
        const media = mediaData.find(m => m.media_id === mediaId);
        if (!media) return;
        currentMedia = media;
        document.getElementById('mediaUploaderName').textContent = media.user_full_name;
        const display = document.getElementById('mediaDisplay');
        if (media.media_type === 'Photo') {
            display.innerHTML = `<img src="${media.media_file}" class="max-h-96 mx-auto rounded-xl">`;
        } else {
            display.innerHTML = `<video src="${media.media_file}" controls class="max-h-96 mx-auto rounded-xl"></video>`;
        }
        document.getElementById('downloadMediaLink').href = media.media_file;
        document.getElementById('mediaModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeMediaModal() {
        document.getElementById('mediaModal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    document.getElementById('mediaModal').addEventListener('click', function(e) {
        if (e.target === this) closeMediaModal();
    });

    // ---------- Closing other modals on ESC ----------
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeScheduleModal();
            closeMediaModal();
        }
    });

    // default tab
    switchTab(0);
</script>
<!-- Fixed Bottom Navigation Tabs -->
<footer class="glass fixed bottom-0 left-0 right-0 z-50 border-t border-white/30">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-around items-center py-3 text-xs md:text-sm font-medium">
            <button onclick="switchTab(0)" id="tab0" class="tab flex flex-col items-center text-indigo-600 tab-active">
                <i class="fa fa-home text-xl"></i>
                <span class="mt-1">Venue</span>
            </button>
            <button onclick="switchTab(1)" id="tab1" class="tab flex flex-col items-center text-gray-600">
                <i class="fa fa-wallet text-xl"></i>
                <span class="mt-1">Fundraises</span>
            </button>
            <button onclick="switchTab(2)" id="tab2" class="tab flex flex-col items-center text-gray-600">
                <i class="fa fa-comments text-xl"></i>
                <span class="mt-1">Chats</span>
            </button>
            <button onclick="switchTab(3)" id="tab3" class="tab flex flex-col items-center text-gray-600">
                <i class="fa fa-images text-xl"></i>
                <span class="mt-1">Gallery</span>
            </button>
        </div>
    </div>
</footer>
</body>
</html>