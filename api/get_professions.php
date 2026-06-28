<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT DISTINCT profession_title FROM user_event_jobs WHERE job_status = 'Valid' ORDER BY profession_title ASC");
    $professions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'professions' => $professions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
