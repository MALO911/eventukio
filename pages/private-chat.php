<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$event_id = (int)($_GET['event_id'] ?? 0);
$receiver_id = clean($_GET['receiver_id'] ?? '');

if ($event_id <= 0 || empty($receiver_id)) {
    errorMsg("Invalid private chat request.");
    redirect('pages/event-page.php?id=' . $event_id);
}

$user_id = getCurrentUserId();

// Prevent chatting with yourself
if ($user_id === $receiver_id) {
    errorMsg("You cannot chat with yourself.");
    redirect('pages/event-page.php?id=' . $event_id);
}

// 1. Check that private chat is unlocked for this event
$check = $pdo->prepare("SELECT event_activeness, groupchat_permission, privatechat_permission FROM event_basic_info WHERE event_id = ?");
$check->execute([$event_id]);
$event = $check->fetch();
if (!$event) {
    errorMsg("Event not found.");
    redirect('pages/events.php');
}
if ($event['privatechat_permission'] !== 'Unlocked') {
    errorMsg("Private chat is currently locked for this event.");
    redirect("pages/event-page.php?id=$event_id");
}
if ($event['event_activeness'] !== 'In Session') {
    errorMsg("This event is not currently active.");
    redirect("pages/event-page.php?id=$event_id");
}

// 2. Check that both users are part of the event and have valid statuses
function getUserRoleInEvent($pdo, $user_id, $event_id) {
    // Check if user is the host
    $stmt = $pdo->prepare("SELECT 1 FROM event_basic_info WHERE event_id = ? AND host_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) return 'Event Host';

    // Check if user is an active attendee
    $stmt = $pdo->prepare("SELECT 1 FROM event_attendees WHERE event_id = ? AND participant_id = ? AND participation_status = 'Active'");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) return 'Normal Attendee';

    // Check if user is an active service provider
    $stmt = $pdo->prepare("SELECT 1 FROM event_service_hiring WHERE event_id = ? AND user_id = ? AND service_status = 'Accepted' AND presence_status = 'Active'");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) return 'Service Provider';

    return false;
}

$current_role = getUserRoleInEvent($pdo, $user_id, $event_id);
if (!$current_role) {
    errorMsg("You are not authorized to access this chat.");
    redirect("pages/event-page.php?id=$event_id");
}

$receiver_role = getUserRoleInEvent($pdo, $receiver_id, $event_id);
if (!$receiver_role) {
    errorMsg("The recipient is not part of this event.");
    redirect("pages/event-page.php?id=$event_id");
}

// 3. Get receiver info
$receiver_stmt = $pdo->prepare("SELECT user_full_name, user_profile_picture FROM user_basic_info WHERE user_id = ?");
$receiver_stmt->execute([$receiver_id]);
$receiver = $receiver_stmt->fetch();
if (!$receiver) {
    errorMsg("User not found.");
    redirect("pages/event-page.php?id=$event_id");
}

// 4. Get or create private chatroom entry
$chat_id = null;
// Try to find existing chat_id from messages
$stmt = $pdo->prepare("SELECT chat_id FROM event_privatechat_messages 
                       WHERE event_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                       LIMIT 1");
$stmt->execute([$event_id, $user_id, $receiver_id, $receiver_id, $user_id]);
$row = $stmt->fetch();
if ($row) {
    $chat_id = $row['chat_id'];
} else {
    // Create new chatroom entry
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO event_chatroom (event_id, chat_type) VALUES (?, 'Private')");
        $stmt->execute([$event_id]);
        $chat_id = $pdo->lastInsertId();
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        errorMsg("Failed to initialize chat. Please try again.");
        redirect("pages/event-page.php?id=$event_id");
    }
}

// 5. Mark all messages sent to current user as read
$update = $pdo->prepare("UPDATE event_privatechat_messages SET message_status = 'Read' 
                         WHERE event_id = ? AND chat_id = ? AND receiver_id = ? AND message_status = 'Unread'");
$update->execute([$event_id, $chat_id, $user_id]);

// Encryption helper functions
function encryptMessage($plaintext) {
    $key = ENCRYPTION_KEY;
    $method = ENCRYPTION_METHOD;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $ciphertext = openssl_encrypt($plaintext, $method, $key, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

function decryptMessage($encrypted) {
    $key = ENCRYPTION_KEY;
    $method = ENCRYPTION_METHOD;
    $data = base64_decode($encrypted);
    $iv_len = openssl_cipher_iv_length($method);
    $iv = substr($data, 0, $iv_len);
    $ciphertext = substr($data, $iv_len);
    return openssl_decrypt($ciphertext, $method, $key, 0, $iv);
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'send') {
        $message = clean($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
            exit;
        }
        // Encrypt before storing
        $encrypted = encryptMessage($message);
        $stmt = $pdo->prepare("INSERT INTO event_privatechat_messages 
                               (event_id, chat_id, sender_id, receiver_id, message_content, sender_participation_position, message_status)
                               VALUES (?, ?, ?, ?, ?, ?, 'Unread')");
        $success = $stmt->execute([$event_id, $chat_id, $user_id, $receiver_id, $encrypted, $current_role]);
        if ($success) {
            // Return the newly created message data for immediate display
            $new_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM event_privatechat_messages WHERE privatechat_message_id = ?");
            $stmt->execute([$new_id]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'message' => [
                    'id' => $msg['privatechat_message_id'],
                    'sender_id' => $msg['sender_id'],
                    'receiver_id' => $msg['receiver_id'],
                    'content' => $message, // plain text for display
                    'date' => $msg['message_date'],
                    'time' => $msg['message_time'],
                    'sender_participation_position' => $msg['sender_participation_position'],
                    'sender_name' => getUserFullName($pdo, $msg['sender_id']),
                    'sender_picture' => getUserProfilePicture($pdo, $msg['sender_id']),
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
        }
        exit;
    }

    if ($_POST['action'] === 'fetch') {
        // Fetch all messages for this chat, decrypt content
        $stmt = $pdo->prepare("SELECT * FROM event_privatechat_messages 
                               WHERE event_id = ? AND chat_id = ? 
                               ORDER BY message_date ASC, message_time ASC");
        $stmt->execute([$event_id, $chat_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($messages as $msg) {
            // Decrypt content for display
            $msg['message_content'] = decryptMessage($msg['message_content']);
            // Add sender details
            $msg['sender_name'] = getUserFullName($pdo, $msg['sender_id']);
            $msg['sender_picture'] = getUserProfilePicture($pdo, $msg['sender_id']);
            $result[] = $msg;
        }
        echo json_encode(['success' => true, 'messages' => $result]);
        exit;
    }
}

// Helper functions to get user details (can be moved to functions.php)
function getUserFullName($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT user_full_name FROM user_basic_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? $row['user_full_name'] : 'Unknown';
}
function getUserProfilePicture($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT user_profile_picture FROM user_basic_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return getProfilePictureUrl($row ? ($row['user_profile_picture'] ?? '') : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Chat with <?= htmlspecialchars($receiver['user_full_name']) ?> - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f0f4ff; }
        .glass {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.25);
        }
        .chat-container {
            height: calc(100vh - 200px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }
        .message {
            max-width: 75%;
            padding: 10px 16px;
            border-radius: 20px;
            margin-bottom: 8px;
            word-wrap: break-word;
            position: relative;
        }
        .own {
            background: #6366f1;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .other {
            background: #f3f4f6;
            color: #111827;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .message-meta {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
        }
        .sender-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 2px;
        }
        .sender-info img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
        }
        .own .sender-info { color: #e0e7ff; }
        .other .sender-info { color: #374151; }
        .header-profile-img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

<header class="glass sticky top-0 z-50 shadow-sm">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button onclick="history.back()" class="text-gray-700 hover:text-indigo-700 text-xl">
                <i class="fa fa-arrow-left"></i>
            </button>
            <img src="<?= htmlspecialchars(getProfilePictureUrl($receiver['user_profile_picture'] ?? '')) ?>"
                 class="header-profile-img" alt="">
            <div>
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($receiver['user_full_name']) ?></p>
                <p class="text-xs text-green-500">Online</p>
            </div>
        </div>
        <div class="text-sm text-gray-500">Private Chat</div>
    </div>
</header>

<div class="flex-1 max-w-4xl mx-auto w-full px-4 py-4 flex flex-col">
    <div id="chatContainer" class="chat-container glass rounded-3xl flex-1 overflow-y-auto flex flex-col gap-2">
        <!-- Messages will be loaded here dynamically -->
    </div>

    <!-- Message Input -->
    <div class="glass mt-4 rounded-3xl p-4 flex gap-3">
        <input type="text" id="messageInput"
               class="flex-1 glass rounded-3xl px-6 py-3 focus:outline-none text-base bg-white/30"
               placeholder="Type your message..."
               autocomplete="off">
        <button id="sendBtn"
                class="bg-indigo-600 hover:bg-indigo-700 w-14 h-14 rounded-3xl flex items-center justify-center text-white transition duration-200">
            <i class="fa fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
    const chatContainer = document.getElementById('chatContainer');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const eventId = <?= (int)$event_id ?>;
    const chatId = <?= (int)$chat_id ?>;
    const currentUserId = '<?= $user_id ?>';
    const receiverId = '<?= $receiver_id ?>';

    // Helper to format time
    function formatTime(date, time) {
        // Simple formatting: "HH:MM" and "DD MMM YYYY"
        const d = new Date(date + 'T' + time);
        return d.toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Render a single message bubble
    function renderMessage(msg, isOwn) {
        const div = document.createElement('div');
        div.className = `message ${isOwn ? 'own' : 'other'}`;
        // Sender info (only for other messages)
        if (!isOwn) {
            const senderDiv = document.createElement('div');
            senderDiv.className = 'sender-info';
            senderDiv.innerHTML = `
                <img src="${msg.sender_picture || '../assets/images/default.png'}" alt="">
                <span>${msg.sender_name}</span>
                <span class="text-xs opacity-75">(${msg.sender_participation_position || 'Attendee'})</span>
            `;
            div.appendChild(senderDiv);
        }
        // Message content
        const contentP = document.createElement('p');
        contentP.textContent = msg.message_content;
        div.appendChild(contentP);
        // Meta info (date/time)
        const meta = document.createElement('div');
        meta.className = 'message-meta';
        meta.innerHTML = `<span>${formatTime(msg.message_date, msg.message_time)}</span>`;
        div.appendChild(meta);
        return div;
    }

    // Load all messages from server
    function loadMessages() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=fetch'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                chatContainer.innerHTML = '';
                data.messages.forEach(msg => {
                    const isOwn = (msg.sender_id === currentUserId);
                    const el = renderMessage(msg, isOwn);
                    chatContainer.appendChild(el);
                });
                chatContainer.scrollTop = chatContainer.scrollHeight;
            } else {
                console.error('Failed to fetch messages:', data.error);
            }
        })
        .catch(err => console.error('Fetch error:', err));
    }

    // Send a new message
    function sendMessage() {
        const msg = messageInput.value.trim();
        if (!msg) return;

        // Disable send button temporarily
        sendBtn.disabled = true;
        sendBtn.classList.add('opacity-50');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send&message=${encodeURIComponent(msg)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Append the new message optimistically
                const newMsg = data.message;
                const isOwn = true; // sender is current user
                const el = renderMessage({
                    message_content: newMsg.content,
                    sender_name: newMsg.sender_name,
                    sender_picture: newMsg.sender_picture,
                    sender_participation_position: newMsg.sender_participation_position,
                    message_date: newMsg.date,
                    message_time: newMsg.time,
                }, isOwn);
                chatContainer.appendChild(el);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                messageInput.value = '';
            } else {
                alert('Failed to send message: ' + data.error);
            }
        })
        .catch(err => {
            console.error('Send error:', err);
            alert('Network error. Please try again.');
        })
        .finally(() => {
            sendBtn.disabled = false;
            sendBtn.classList.remove('opacity-50');
        });
    }

    // Event listeners
    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Initial load
    loadMessages();

    // Poll for new messages every 5 seconds
    setInterval(loadMessages, 5000);
</script>
</body>
</html>