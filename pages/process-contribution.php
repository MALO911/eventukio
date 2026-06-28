<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = getCurrentUserId();
$fundraise_id = clean($_POST['fundraise_id'] ?? '');
$tag_id = clean($_POST['tag_id'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$is_contribution = ($_POST['is_contribution'] ?? '1') === '1';

if (empty($fundraise_id) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Check fundraise exists and is active
    $stmt = $pdo->prepare("SELECT * FROM event_fundraise_info WHERE fundraise_id = ? AND fundraise_status = 'Active'");
    $stmt->execute([$fundraise_id]);
    $fundraise = $stmt->fetch();

    if (!$fundraise) {
        throw new Exception("Fundraise not found or inactive");
    }

    // 2. Check wallet balance
    $stmt = $pdo->prepare("SELECT account_id, account_balance FROM user_wallet_info WHERE user_id = ? AND account_activity = 'Active'");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();

    if (!$wallet || $wallet['account_balance'] < $amount) {
        throw new Exception("Insufficient wallet balance");
    }

    // 3. Check goal for Limited fundraises
    if ($fundraise['fundraise_category'] === 'Limited' && $fundraise['required_amount'] > 0) {
        if ($fundraise['collected_amount'] + $amount > $fundraise['required_amount']) {
            throw new Exception("This fundraise has reached its goal");
        }
    }

    $account_id = $wallet['account_id'];

    // 4. Deduct from wallet
    $new_balance = $wallet['account_balance'] - $amount;
    $stmt = $pdo->prepare("UPDATE user_wallet_info SET account_balance = ? WHERE account_id = ?");
    $stmt->execute([$new_balance, $account_id]);

    // 5. Update fundraise collected amount
    $stmt = $pdo->prepare("UPDATE event_fundraise_info SET collected_amount = collected_amount + ? WHERE fundraise_id = ?");
    $stmt->execute([$amount, $fundraise_id]);

    // 6. Update tag participant count (if contribution)
    if ($is_contribution && !empty($tag_id)) {
        $stmt = $pdo->prepare("UPDATE event_fundraise_tags SET participant_count = participant_count + 1 WHERE fundraise_tag_id = ?");
        $stmt->execute([$tag_id]);
    }

    // 7. Record the transaction
    $funding_id = 'FUND-' . strtoupper(bin2hex(random_bytes(6)));
    $stmt = $pdo->prepare("
        INSERT INTO event_funding_records 
        (funding_id, fundraise_id, event_id, fundraise_tag_id, payer_id, funded_amount, fund_validity)
        VALUES (?, ?, ?, ?, ?, ?, 'Valid')
    ");
    $stmt->execute([$funding_id, $fundraise_id, $fundraise['event_id'], $tag_id ?: null, $user_id, $amount]);

    // 8. Record wallet transaction
    $stmt = $pdo->prepare("
        INSERT INTO user_wallet_transactions 
        (user_id, account_id, transaction_details, transaction_amount, transaction_type, transaction_validity)
        VALUES (?, ?, ?, ?, 'Outgoing', 'Valid')
    ");
    $stmt->execute([$user_id, $account_id, "Contribution to fundraise: " . $fundraise['fundraise_title'], $amount]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Contribution successful!'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>