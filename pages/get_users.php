<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$exclude_id = $_GET['exclude'] ?? '';
$status_param = $_GET['status'] ?? 'Verified,Registered';

$statuses = explode(',', $status_param);
$statuses = array_filter(array_map('trim', $statuses));

if (empty($statuses)) {
    $statuses = ['Verified', 'Registered'];
}

$params = [];
$status_placeholders = implode(',', array_fill(0, count($statuses), '?'));

$query = "
    SELECT user_id, user_full_name, user_profile_picture, user_type, user_gender
    FROM user_basic_info 
    WHERE user_validity IN ($status_placeholders)
";
$params = array_values($statuses);

if (!empty($exclude_id)) {
    $query .= " AND user_id != ?";
    $params[] = $exclude_id;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['user_profile_picture'] = getProfilePictureUrl($user['user_profile_picture'] ?? '');
    }

    echo json_encode($users);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
}
?>
