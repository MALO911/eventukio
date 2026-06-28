<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$region = $_GET['region'] ?? '';

if (empty($region)) {
    echo json_encode(['success' => false, 'error' => 'Region parameter required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT asset_district FROM user_event_asset WHERE asset_region = ? AND asset_district IS NOT NULL AND asset_district != '' ORDER BY asset_district ASC");
    $stmt->execute([$region]);
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'districts' => $districts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
