<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getCurrentUserId();
$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $event_id = (int)$_POST['event_id'];
    $chat_id = (int)$_POST['chat_id'];
    $message = clean($_POST['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }
    
    // Verify user is authorized
    $check = $pdo->prepare("
        SELECT e.host_id, e.groupchat_permission
        FROM event_basic_info e
        WHERE e.event_id = ? AND e.groupchat_id = ?
    ");
    $check->execute([$event_id, $chat_id]);
    $event = $check->fetch();
    
    if (!$event || $event['groupchat_permission'] !== 'Unlocked') {
        echo json_encode(['success' => false, 'message' => 'Chat not available']);
        exit;
    }
    
    // Check authorization
    $is_authorized = ($event['host_id'] === $user_id);
    if (!$is_authorized) {
        $attendee_check = $pdo->prepare("
            SELECT 1 FROM event_attendees ea
            JOIN event_invitees ei ON ea.invitee_id = ei.invitee_id
            WHERE ea.event_id = ? AND ea.participant_id = ? AND ea.participation_status = 'Active' AND ei.attendance_status = 'Confirmed'
        ");
        $attendee_check->execute([$event_id, $user_id]);
        if ($attendee_check->fetch()) {
            $is_authorized = true;
        }
    }
    if (!$is_authorized) {
        $service_check = $pdo->prepare("
            SELECT 1 FROM event_service_hiring
            WHERE event_id = ? AND user_id = ? AND service_status = 'Accepted' AND presence_status = 'Active'
        ");
        $service_check->execute([$event_id, $user_id]);
        if ($service_check->fetch()) {
            $is_authorized = true;
        }
    }
    
    if (!$is_authorized) {
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    }
    
    // Determine participation position
    $participation_position = 'Normal Attendee';
    if ($event['host_id'] === $user_id) {
        $participation_position = 'Event Host';
    } else {
        $service_check = $pdo->prepare("
            SELECT 1 FROM event_service_hiring 
            WHERE event_id = ? AND user_id = ? AND service_status = 'Accepted' AND presence_status = 'Active'
        ");
        $service_check->execute([$event_id, $user_id]);
        if ($service_check->fetch()) {
            $participation_position = 'Service Provider';
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO event_groupchat_messages 
                (event_id, chat_id, sender_id, participation_position, message_content, message_date, message_time, sender_permission, message_visibility)
            VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'Allowed', 'Visible')
        ");
        $stmt->execute([$event_id, $chat_id, $user_id, $participation_position, $message]);
        
        $message_id = $pdo->lastInsertId();
        
        // Get user details
        $user_stmt = $pdo->prepare("
            SELECT user_full_name, user_profile_picture 
            FROM user_basic_info 
            WHERE user_id = ?
        ");
        $user_stmt->execute([$user_id]);
        $user_data = $user_stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => [
                'groupchat_message_id' => $message_id,
                'sender_id' => $user_id,
                'participation_position' => $participation_position,
                'message_content' => $message,
                'message_date' => date('M d, Y'),
                'message_time' => date('g:i A'),
                'user_full_name' => $user_data['user_full_name'],
                'user_profile_picture' => getProfilePictureUrl($user_data['user_profile_picture'])
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'poll') {
    $event_id = (int)$_POST['event_id'];
    $chat_id = (int)$_POST['chat_id'];
    $last_message_id = (int)$_POST['last_message_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                egm.groupchat_message_id,
                egm.sender_id,
                egm.participation_position,
                egm.message_content,
                egm.message_date,
                egm.message_time,
                ubi.user_full_name,
                ubi.user_profile_picture
            FROM event_groupchat_messages egm
            JOIN user_basic_info ubi ON egm.sender_id = ubi.user_id
            WHERE egm.chat_id = ? AND egm.event_id = ? 
                AND egm.message_visibility = 'Visible' 
                AND egm.sender_permission = 'Allowed'
                AND egm.groupchat_message_id > ?
            ORDER BY egm.groupchat_message_id ASC
        ");
        $stmt->execute([$chat_id, $event_id, $last_message_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Resolve profile picture URLs
        foreach ($messages as &$msg) {
            $msg['user_profile_picture'] = getProfilePictureUrl($msg['user_profile_picture']);
        }
        unset($msg);
        
        // Count unread
        $unread_stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM event_groupchat_messages egm
            LEFT JOIN event_groupchat_participants egp ON egp.chat_id = egm.chat_id AND egp.reader_id = ?
            WHERE egm.chat_id = ? AND egm.sender_id != ? 
                AND egm.message_visibility = 'Visible' 
                AND egm.sender_permission = 'Allowed'
                AND (egp.last_read_message_id IS NULL OR egm.groupchat_message_id > egp.last_read_message_id)
        ");
        $unread_stmt->execute([$user_id, $chat_id, $user_id]);
        $unread_result = $unread_stmt->fetch();
        $unread_count = $unread_result['unread_count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'unread_count' => $unread_count
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Polling error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
