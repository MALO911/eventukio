<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$profession = $_GET['profession'] ?? '';

if (empty($profession)) {
    echo json_encode(['success' => false, 'error' => 'Profession parameter required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            uej.profile_id,
            uej.user_id,
            uej.profession_title,
            uej.job_average_rating,
            uej.task_count,
            ubi.user_full_name,
            ubi.user_profile_picture,
            ubi.home_region,
            ubi.home_district,
            ubi.home_street,
            CONCAT(COALESCE(ubi.home_region, ''), ', ', COALESCE(ubi.home_district, ''), ', ', COALESCE(ubi.home_street, '')) as location
        FROM user_event_jobs uej
        JOIN user_basic_info ubi ON uej.user_id = ubi.user_id
        WHERE uej.profession_title = ? 
        AND uej.job_status = 'Valid'
        AND ubi.user_validity = 'Verified'
        ORDER BY uej.job_average_rating DESC
    ");
    $stmt->execute([$profession]);
    $professionals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($professionals as &$professional) {
        $professional['user_profile_picture'] = getProfilePictureUrl($professional['user_profile_picture'] ?? '');
    }
    unset($professional);
    
    echo json_encode(['success' => true, 'professionals' => $professionals]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
