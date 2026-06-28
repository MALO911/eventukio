<?php
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$assetName = $_GET['asset_name'] ?? '';
$region = $_GET['region'] ?? '';
$district = $_GET['district'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $sql = "
        SELECT 
            uea.asset_id,
            uea.owner_id,
            uea.asset_category,
            uea.asset_name,
            uea.asset_quality,
            uea.asset_quantity,
            uea.asset_price,
            uea.asset_street,
            uea.asset_district,
            uea.asset_region,
            ubi.user_full_name
        FROM user_event_asset uea
        JOIN user_basic_info ubi ON uea.owner_id = ubi.user_id
        WHERE uea.asset_status = 'Available'
        AND ubi.user_validity = 'Verified'
    ";
    
    $params = [];
    
    if (!empty($assetName)) {
        $sql .= " AND uea.asset_name = ?";
        $params[] = $assetName;
    }
    
    if (!empty($region)) {
        $sql .= " AND uea.asset_region = ?";
        $params[] = $region;
    }
    
    if (!empty($district)) {
        $sql .= " AND uea.asset_district = ?";
        $params[] = $district;
    }
    
    if (!empty($search)) {
        $sql .= " AND ubi.user_full_name LIKE ?";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY uea.asset_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'assets' => $assets]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
