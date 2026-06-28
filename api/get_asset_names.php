<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT DISTINCT asset_name FROM user_event_asset WHERE asset_status = 'Available' ORDER BY asset_name ASC");
    $assetNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'asset_names' => $assetNames]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
