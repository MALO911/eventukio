<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = $_SESSION['user_id'];
$event_id = (int)($_GET['id'] ?? 0);

if ($event_id <= 0) {
    errorMsg("Invalid Event");
    redirect('pages/events.php');
}

// Get event details and verify user participation
$event_query = $pdo->prepare("
    SELECT 
        ebi.event_id,
        ebi.event_title,
        ebi.host_id,
        ebi.groupchat_permission,
        ebi.groupchat_id,
        ebi.event_activeness,
        ec.chat_id,
        ec.chat_type
    FROM event_basic_info ebi
    LEFT JOIN event_chatroom ec ON ebi.groupchat_id = ec.chat_id
    WHERE ebi.event_id = ?
");
$event_query->execute([$event_id]);
$event = $event_query->fetch();

if (!$event) {
    errorMsg("Event not found");
    redirect('pages/events.php');
}

// Check if event is In Session
if ($event['event_activeness'] !== 'In Session') {
    errorMsg("This event is no longer active");
    redirect('pages/events.php');
}

// Check if user is authorized to access this page
$is_authorized = false;

// Check if user is the host
if ($event['host_id'] === $user_id) {
    $is_authorized = true;
}

// Check if user is a normal attendee
if (!$is_authorized) {
    $attendee_query = $pdo->prepare("
        SELECT attendee_id
        FROM event_attendees
        WHERE event_id = ? AND participant_id = ? AND participation_status = 'Active'
    ");
    $attendee_query->execute([$event_id, $user_id]);
    if ($attendee_query->fetch()) {
        $is_authorized = true;
    }
}

// Check if user is a service provider
if (!$is_authorized) {
    $service_query = $pdo->prepare("
        SELECT hire_id
        FROM event_service_hiring
        WHERE event_id = ? AND user_id = ? AND service_status = 'Accepted' AND presence_status = 'Active'
    ");
    $service_query->execute([$event_id, $user_id]);
    if ($service_query->fetch()) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    errorMsg("You are not authorized to access this chat");
    redirect('pages/event-page.php?id=' . $event_id);
}

// Check if group chat is unlocked
if ($event['groupchat_permission'] !== 'Unlocked') {
    errorMsg("Group chat is locked by the event host");
    redirect('pages/event-page.php?id=' . $event_id);
}

// Check if group chat exists
if ($event['groupchat_id'] === null || $event['chat_type'] !== 'Group') {
    errorMsg("Group chat is not available for this event");
    redirect('pages/event-page.php?id=' . $event_id);
}

$chat_id = $event['groupchat_id'];

// Determine user's participation position
$participation_position = 'Normal Attendee';
if ($event['host_id'] === $user_id) {
    $participation_position = 'Event Host';
} else {
    $service_check = $pdo->prepare("
        SELECT hire_id FROM event_service_hiring 
        WHERE event_id = ? AND user_id = ? AND service_status = 'Accepted' AND presence_status = 'Active'
    ");
    $service_check->execute([$event_id, $user_id]);
    if ($service_check->fetch()) {
        $participation_position = 'Service Provider';
    }
}

// Get user details
$user_query = $pdo->prepare("
    SELECT user_full_name, user_profile_picture 
    FROM user_basic_info 
    WHERE user_id = ?
");
$user_query->execute([$user_id]);
$user_data = $user_query->fetch();

// Update unread messages for this user (WhatsApp technique)
// Mark messages as read by updating last_read_message_id
$update_read = $pdo->prepare("
    INSERT INTO event_groupchat_participants (event_id, chat_id, reader_id, last_read_message_id, reading_timestamp)
    SELECT ?, ?, ?, COALESCE(MAX(groupchat_message_id), 0), NOW()
    FROM event_groupchat_messages
    WHERE chat_id = ?
    ON DUPLICATE KEY UPDATE
        last_read_message_id = COALESCE((SELECT MAX(groupchat_message_id) FROM event_groupchat_messages WHERE chat_id = ?), 0),
        reading_timestamp = NOW()
");
$update_read->execute([$event_id, $chat_id, $user_id, $chat_id, $chat_id]);

// Get messages with sender details
$messages_query = $pdo->prepare("
    SELECT 
        egm.groupchat_message_id,
        egm.sender_id,
        egm.participation_position,
        egm.message_content,
        egm.message_date,
        egm.message_time,
        egm.sender_permission,
        egm.message_visibility,
        ubi.user_full_name,
        ubi.user_profile_picture
    FROM event_groupchat_messages egm
    JOIN user_basic_info ubi ON egm.sender_id = ubi.user_id
    WHERE egm.chat_id = ? AND egm.event_id = ? AND egm.message_visibility = 'Visible' AND egm.sender_permission = 'Allowed'
    ORDER BY egm.message_date ASC, egm.message_time ASC
");
$messages_query->execute([$chat_id, $event_id]);
$messages = $messages_query->fetchAll();

// Count unread messages for this user
$unread_query = $pdo->prepare("
    SELECT COUNT(*) as unread_count
    FROM event_groupchat_messages egm
    LEFT JOIN event_groupchat_participants egp ON egp.chat_id = egm.chat_id AND egp.reader_id = ?
    WHERE egm.chat_id = ? AND egm.sender_id != ? 
        AND egm.message_visibility = 'Visible' 
        AND egm.sender_permission = 'Allowed'
        AND (egp.last_read_message_id IS NULL OR egm.groupchat_message_id > egp.last_read_message_id)
");
$unread_query->execute([$user_id, $chat_id, $user_id]);
$unread_result = $unread_query->fetch();
$unread_count = $unread_result['unread_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chat - <?php echo htmlspecialchars($event['event_title']); ?> - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.25); }
        .chat-container { height: calc(100vh - 180px); overflow-y: auto; padding: 1rem; }
        .chat-container::-webkit-scrollbar { width: 6px; }
        .chat-container::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); border-radius: 10px; }
        .chat-container::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.4); border-radius: 10px; }
        .message {
            max-width: 80%;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-own {
            background: #6366f1;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .message-other {
            background: rgba(255,255,255,0.85);
            color: #1f2937;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .message-other .sender-name {
            font-weight: 600;
            font-size: 0.8rem;
            color: #4f46e5;
            margin-bottom: 2px;
        }
        .message-time {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        .profile-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .position-badge {
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(99,102,241,0.15);
            color: #4f46e5;
            margin-left: 6px;
        }
        .message-input-container {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.2);
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
        }
        .chat-container {
            padding-bottom: 120px;
        }
        .message-input-container input {
            background: rgba(255,255,255,0.1);
            border: none;
            outline: none;
            color: #1f2937;
            padding: 0.75rem 1.25rem;
            border-radius: 2rem;
            width: 100%;
        }
        .message-input-container input::placeholder {
            color: #9ca3af;
        }
        .message-input-container input:focus {
            ring: 2px solid #6366f1;
        }
        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #6366f1;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .send-btn:hover {
            background: #4f46e5;
            transform: scale(1.05);
        }
        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            padding: 1px 8px;
            border-radius: 10px;
            margin-left: 6px;
        }
        .typing-indicator {
            display: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.15);
            border-radius: 1rem;
            align-self: flex-start;
            margin-bottom: 8px;
        }
        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
            margin: 0 2px;
            animation: typing 1.4s infinite both;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-6px); opacity: 1; }
        }
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9ca3af;
            text-align: center;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex flex-col">

    <!-- Header -->
    <header class="glass sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0">
                    <span class="text-xl font-bold text-indigo-700">EVENTUKIO</span>
                </div>
                <div class="hidden sm:block h-6 w-px bg-gray-300"></div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        <?php echo htmlspecialchars($event['event_title']); ?>
                    </p>
                    <p class="text-xs text-gray-500">Group Chat <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo $unread_count; ?> new</span><?php endif; ?></p>
                </div>
            </div>
            <button onclick="history.back()" class="text-gray-600 hover:text-indigo-700 transition p-2 rounded-full hover:bg-white/20">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
    </header>

    <!-- Chat Body -->
    <div class="flex-1 flex flex-col max-w-4xl mx-auto w-full px-4 py-4">
        <div id="chatContainer" class="chat-container glass rounded-3xl flex-1 overflow-y-auto flex flex-col">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments text-indigo-300"></i>
                    <p class="text-gray-400 font-medium">No messages yet</p>
                    <p class="text-gray-400 text-sm">Be the first to start the conversation!</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col space-y-3 w-full">
                    <?php foreach ($messages as $msg): 
                        $is_own = ($msg['sender_id'] === $user_id);
                        $profile_img = htmlspecialchars(getProfilePictureUrl($msg['user_profile_picture']));
                    ?>
                        <div class="message flex items-start gap-3 <?php echo $is_own ? 'message-own ml-auto' : 'message-other mr-auto'; ?> rounded-2xl px-4 py-3">
                            <?php if (!$is_own): ?>
                                <img src="<?php echo $profile_img; ?>" alt="<?php echo htmlspecialchars($msg['user_full_name']); ?>" class="profile-pic mt-1">
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <?php if (!$is_own): ?>
                                    <div class="sender-name flex items-center flex-wrap">
                                        <?php echo htmlspecialchars($msg['user_full_name']); ?>
                                        <span class="position-badge"><?php echo htmlspecialchars($msg['participation_position']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="break-words text-sm md:text-base">
                                    <?php echo htmlspecialchars($msg['message_content']); ?>
                                </div>
                                <div class="message-time flex items-center gap-2 <?php echo $is_own ? 'text-indigo-200' : 'text-gray-400'; ?>">
                                    <span><?php echo date('g:i A', strtotime($msg['message_time'])); ?></span>
                                    <span>•</span>
                                    <span><?php echo date('M d, Y', strtotime($msg['message_date'])); ?></span>
                                    <?php if ($is_own): ?>
                                        <span class="text-xs opacity-50"><i class="fas fa-check"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($is_own): ?>
                                <img src="<?php echo htmlspecialchars(getProfilePictureUrl($user_data['user_profile_picture'])); ?>" alt="You" class="profile-pic mt-1 order-first">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <!-- Typing indicator -->
            <div id="typingIndicator" class="typing-indicator">
                <span></span><span></span><span></span>
                <span class="text-xs text-gray-400 ml-2">Someone is typing...</span>
            </div>
        </div>

        <!-- Footer Input -->
        <div class="message-input-container rounded-3xl p-3 mt-4 flex items-center gap-3">
            <input type="text" id="messageInput" 
                   class="flex-1" 
                   placeholder="Type a message..." 
                   onkeypress="if(event.key === 'Enter' && !event.shiftKey){ event.preventDefault(); sendMessage(); }"
                   maxlength="500">
            <button onclick="sendMessage()" id="sendBtn" class="send-btn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        const chatContainer = document.getElementById('chatContainer');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const eventId = <?php echo $event_id; ?>;
        const chatId = <?php echo $chat_id; ?>;
        const userId = '<?php echo $user_id; ?>';
        const participation_position = '<?php echo $participation_position; ?>';
        const userFullName = '<?php echo htmlspecialchars($user_data['user_full_name'] ?? 'User'); ?>';
        const userProfilePicture = '<?php echo htmlspecialchars(getProfilePictureUrl($user_data['user_profile_picture'])); ?>';

        // Scroll to bottom on load
        function scrollToBottom() {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        window.onload = scrollToBottom;

        // Send message via AJAX
        function sendMessage() {
            const msg = messageInput.value.trim();
            if (!msg) return;

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Optimistically show message immediately
            const tempMessage = {
                groupchat_message_id: 'temp-' + Date.now(),
                sender_id: userId,
                participation_position: participation_position,
                message_content: msg,
                message_date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                message_time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }),
                user_full_name: userFullName,
                user_profile_picture: userProfilePicture
            };
            appendMessage(tempMessage);
            messageInput.value = '';
            scrollToBottom();

            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('chat_id', chatId);
            formData.append('message', msg);
            formData.append('action', 'send');

            fetch('ajax-group-chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Replace temp message with real one
                    const tempEl = chatContainer.querySelector(`[data-message-id="${tempMessage.groupchat_message_id}"]`);
                    if (tempEl) {
                        tempEl.remove();
                    }
                    appendMessage(data.message);
                    scrollToBottom();
                } else {
                    console.error('Send failed:', data.message);
                    // Remove temp message on failure
                    const tempEl = chatContainer.querySelector(`[data-message-id="${tempMessage.groupchat_message_id}"]`);
                    if (tempEl) {
                        tempEl.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Remove temp message on error
                const tempEl = chatContainer.querySelector(`[data-message-id="${tempMessage.groupchat_message_id}"]`);
                if (tempEl) {
                    tempEl.remove();
                }
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            });
        }

        // Append a message to the chat
        function appendMessage(msgData) {
            const isOwn = msgData.sender_id === userId;
            const profilePic = msgData.user_profile_picture || '../assets/images/default.png';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message flex items-start gap-3 ${isOwn ? 'message-own ml-auto' : 'message-other mr-auto'} rounded-2xl px-4 py-3`;
            messageDiv.dataset.messageId = msgData.groupchat_message_id;
            
            let html = '';
            if (!isOwn) {
                html += `<img src="${profilePic}" alt="${msgData.user_full_name}" class="profile-pic mt-1">`;
            }
            
            html += `<div class="flex-1 min-w-0">`;
            if (!isOwn) {
                html += `<div class="sender-name flex items-center flex-wrap">
                            ${msgData.user_full_name}
                            <span class="position-badge">${msgData.participation_position}</span>
                        </div>`;
            }
            html += `<div class="break-words text-sm md:text-base">${msgData.message_content}</div>
                        <div class="message-time flex items-center gap-2 ${isOwn ? 'text-indigo-200' : 'text-gray-400'}">
                            <span>${msgData.message_time}</span>
                            <span>•</span>
                            <span>${msgData.message_date}</span>
                            ${isOwn ? `<span class="text-xs opacity-50"><i class="fas fa-check"></i></span>` : ''}
                        </div>
                    </div>`;
            
            if (isOwn) {
                html += `<img src="${msgData.user_profile_picture || '../assets/images/default.png'}" alt="You" class="profile-pic mt-1 order-first">`;
            }
            
            messageDiv.innerHTML = html;
            
            // Remove empty state if it exists
            const emptyState = chatContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Ensure we have a flex container
            let wrapper = chatContainer.querySelector('.flex-col');
            if (!wrapper) {
                wrapper = document.createElement('div');
                wrapper.className = 'flex flex-col space-y-3 w-full';
                chatContainer.appendChild(wrapper);
            }
            
            wrapper.appendChild(messageDiv);
            scrollToBottom();
        }

        // Poll for new messages
        let lastMessageId = 0;
        let isPolling = false;

        function getLatestMessageId() {
            const messages = chatContainer.querySelectorAll('.message');
            if (messages.length > 0) {
                // Get the last message's ID from data attribute
                const lastMsg = messages[messages.length - 1];
                return lastMsg.dataset.messageId || 0;
            }
            return 0;
        }

        function pollNewMessages() {
            if (isPolling) return;
            isPolling = true;

            const currentLastId = getLatestMessageId();

            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('chat_id', chatId);
            formData.append('last_message_id', currentLastId);
            formData.append('action', 'poll');

            fetch('ajax-group-chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        // Check if message already exists (prevent duplicates)
                        const exists = chatContainer.querySelector(`[data-message-id="${msg.groupchat_message_id}"]`);
                        if (!exists) {
                            appendMessageFromPoll(msg);
                        }
                    });
                }
                // Update unread count
                if (data.unread_count !== undefined) {
                    updateUnreadBadge(data.unread_count);
                }
            })
            .catch(error => {
                console.error('Polling error:', error);
            })
            .finally(() => {
                isPolling = false;
            });
        }

        function appendMessageFromPoll(msgData) {
            const isOwn = msgData.sender_id === userId;
            const profilePic = msgData.user_profile_picture || '../assets/images/default.png';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message flex items-start gap-3 ${isOwn ? 'message-own ml-auto' : 'message-other mr-auto'} rounded-2xl px-4 py-3`;
            messageDiv.dataset.messageId = msgData.groupchat_message_id;
            
            let html = '';
            if (!isOwn) {
                html += `<img src="${profilePic}" alt="${msgData.user_full_name}" class="profile-pic mt-1">`;
            }
            
            html += `<div class="flex-1 min-w-0">`;
            if (!isOwn) {
                html += `<div class="sender-name flex items-center flex-wrap">
                            ${msgData.user_full_name}
                            <span class="position-badge">${msgData.participation_position}</span>
                        </div>`;
            }
            html += `<div class="break-words text-sm md:text-base">${msgData.message_content}</div>
                        <div class="message-time flex items-center gap-2 ${isOwn ? 'text-indigo-200' : 'text-gray-400'}">
                            <span>${msgData.message_time}</span>
                            <span>•</span>
                            <span>${msgData.message_date}</span>
                            ${isOwn ? `<span class="text-xs opacity-50"><i class="fas fa-check"></i></span>` : ''}
                        </div>
                    </div>`;
            
            if (isOwn) {
                html += `<img src="${msgData.user_profile_picture || '../assets/images/default.png'}" alt="You" class="profile-pic mt-1 order-first">`;
            }
            
            messageDiv.innerHTML = html;
            
            // Remove empty state if it exists
            const emptyState = chatContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            const wrapper = chatContainer.querySelector('.flex-col');
            if (wrapper) {
                wrapper.appendChild(messageDiv);
            } else {
                const newWrapper = document.createElement('div');
                newWrapper.className = 'flex flex-col space-y-3 w-full';
                chatContainer.appendChild(newWrapper);
                newWrapper.appendChild(messageDiv);
            }
            scrollToBottom();
        }

        function updateUnreadBadge(count) {
            const header = document.querySelector('header .text-xs');
            if (header) {
                if (count > 0) {
                    const badge = header.querySelector('.unread-badge');
                    if (badge) {
                        badge.textContent = count + ' new';
                    } else {
                        header.innerHTML += `<span class="unread-badge">${count} new</span>`;
                    }
                } else {
                    const badge = header.querySelector('.unread-badge');
                    if (badge) badge.remove();
                }
            }
        }

        // Poll every 3 seconds
        setInterval(pollNewMessages, 3000);

        // Simulate typing indicator (for demo)
        let typingTimeout = null;
        messageInput.addEventListener('input', function() {
            // In real implementation, send typing status via WebSocket or AJAX
            // For demo, we'll just show/hide based on local activity
            if (typingTimeout) clearTimeout(typingTimeout);
            typingIndicator.style.display = 'flex';
            // Auto-hide typing indicator after 2 seconds of inactivity
            typingTimeout = setTimeout(() => {
                typingIndicator.style.display = 'none';
            }, 2000);
        });

        // Handle visibility change for better polling
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Page became visible - poll immediately
                pollNewMessages();
            }
        });
    </script>
</body>
</html>