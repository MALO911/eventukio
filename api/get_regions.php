<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT DISTINCT asset_region FROM user_event_asset WHERE asset_region IS NOT NULL AND asset_region != '' ORDER BY asset_region ASC");
    $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'regions' => $regions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
