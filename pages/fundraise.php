<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();
$event_id = (int)($_GET['event_id'] ?? 0);
$fundraise_id = clean($_GET['fundraise_id'] ?? '');

if ($event_id <= 0 || empty($fundraise_id)) {
    errorMsg("Invalid Fundraise");
    redirect('pages/event-page.php?id=' . $event_id);
}

// Get fundraise details
$stmt = $pdo->prepare("
    SELECT efi.*, ebi.event_title, ebi.event_activeness 
    FROM event_fundraise_info efi
    JOIN event_basic_info ebi ON efi.event_id = ebi.event_id
    WHERE efi.fundraise_id = ? AND efi.event_id = ? AND efi.fundraise_status = 'Active'
");
$stmt->execute([$fundraise_id, $event_id]);
$fundraise = $stmt->fetch();

if (!$fundraise) {
    errorMsg("Fundraise not found or inactive");
    redirect('pages/event-page.php?id=' . $event_id);
}

// Check authorization (attendee or host)
$stmt = $pdo->prepare("
    SELECT 1 FROM event_attendees
    WHERE event_id = ? AND participant_id = ? AND participation_status = 'Active'
");
$stmt->execute([$event_id, $user_id]);
$isAttendee = $stmt->rowCount() > 0;

$stmt = $pdo->prepare("SELECT 1 FROM event_basic_info WHERE event_id = ? AND host_id = ?");
$stmt->execute([$event_id, $user_id]);
$isHost = $stmt->rowCount() > 0;

if (!$isAttendee && !$isHost) {
    errorMsg("You are not authorized");
    redirect('pages/event-page.php?id=' . $event_id);
}

// Get tags
$stmt = $pdo->prepare("
    SELECT * FROM event_fundraise_tags 
    WHERE fundraise_id = ? AND tag_validity = 'Valid'
    ORDER BY required_amount ASC
");
$stmt->execute([$fundraise_id]);
$fundraise_tags = $stmt->fetchAll();

// Wallet balance
$stmt = $pdo->prepare("
    SELECT account_balance FROM user_wallet_info 
    WHERE user_id = ? AND account_activity = 'Active'
");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();
$balance = $wallet ? $wallet['account_balance'] : 0;

// Goal reached?
$is_goal_reached = false;
if ($fundraise['fundraise_category'] === 'Limited' && !empty($fundraise['required_amount'])) {
    $is_goal_reached = $fundraise['collected_amount'] > $fundraise['required_amount'];
}

// User's total contribution
$stmt = $pdo->prepare("
    SELECT SUM(funded_amount) as total_contributed 
    FROM event_funding_records 
    WHERE fundraise_id = ? AND payer_id = ? 
");
$stmt->execute([$fundraise_id, $user_id]);
$contrib = $stmt->fetch();
$total_contributed = $contrib ? $contrib['total_contributed'] : 0;

// ====================== HANDLE POST REQUESTS ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    
    if ($action === 'contribute' && isset($_POST['tag_id'])) {
        $tag_id = clean($_POST['tag_id']);
        $amount = (float)($_POST['amount'] ?? 0);
        
        // Get tag details
        $tag_stmt = $pdo->prepare("SELECT * FROM event_fundraise_tags WHERE fundraise_tag_id = ? AND fundraise_id = ? AND tag_validity = 'Valid'");
        $tag_stmt->execute([$tag_id, $fundraise_id]);
        $tag = $tag_stmt->fetch();
        
        if (!$tag) {
            echo json_encode(['success' => false, 'message' => 'Invalid tag']);
            exit;
        }
        
        $required_amount = (float)$tag['required_amount'];
        
        // Check wallet balance
        $wallet_stmt = $pdo->prepare("SELECT account_balance FROM user_wallet_info WHERE user_id = ? AND account_activity = 'Active'");
        $wallet_stmt->execute([$user_id]);
        $wallet = $wallet_stmt->fetch();
        $balance = $wallet ? (float)$wallet['account_balance'] : 0;
        
        if ($balance < $required_amount) {
            echo json_encode(['success' => false, 'insufficient_funds' => true]);
            exit;
        }
        
        // For Limited category, check if goal reached
        if ($fundraise['fundraise_category'] === 'Limited' && !empty($fundraise['required_amount'])) {
            $remaining = (float)$fundraise['required_amount'] - (float)$fundraise['collected_amount'];
            if ($remaining < $required_amount) {
                echo json_encode(['success' => false, 'goal_reached' => true]);
                exit;
            }
        }
        
        // Process contribution
        try {
            $pdo->beginTransaction();
            
            // Deduct from wallet
            $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance - ? WHERE user_id = ? AND account_activity = 'Active'")->execute([$required_amount, $user_id]);
            
            // Add to funding records
            $funding_record_id = generateFundingId();
            $pdo->prepare("
                INSERT INTO event_funding_records 
                (funding_record_id, fundraise_id, fundraise_tag_id, payer_id, funded_amount, funding_date, funding_time)
                VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())
            ")->execute([$funding_record_id, $fundraise_id, $tag_id, $user_id, $required_amount]);
            
            // Add wallet transaction
            $transaction_id = generateTransactionId();
            $transaction_details = "Contribution for {$fundraise['fundraise_title']} in the event {$fundraise['event_title']}";
            $pdo->prepare("
                INSERT INTO user_wallet_transactions 
                (transaction_id, user_id, transaction_details, transaction_type, transaction_amount, transaction_date, transaction_time)
                VALUES (?, ?, ?, 'Outgoing', ?, CURDATE(), CURTIME())
            ")->execute([$transaction_id, $user_id, $transaction_details, $required_amount]);
            
            // Update collected amount
            $pdo->prepare("UPDATE event_fundraise_info SET collected_amount = collected_amount + ? WHERE fundraise_id = ?")->execute([$required_amount, $fundraise_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Contribution successful!']);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'donate' && isset($_POST['amount'])) {
        $amount = (float)$_POST['amount'];
        $tag_id = clean($_POST['tag_id'] ?? '');
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid amount']);
            exit;
        }
        
        // For donation cases, tag_id may be empty (no tags required)
        // transaction_amount will use the donation amount
        $transaction_amount = $amount;
        
        // Check wallet balance
        $wallet_stmt = $pdo->prepare("SELECT account_balance FROM user_wallet_info WHERE user_id = ? AND account_activity = 'Active'");
        $wallet_stmt->execute([$user_id]);
        $wallet = $wallet_stmt->fetch();
        $balance = $wallet ? (float)$wallet['account_balance'] : 0;
        
        if ($balance < $amount) {
            echo json_encode(['success' => false, 'insufficient_funds' => true]);
            exit;
        }
        
        // For Limited category, check if goal reached
        if ($fundraise['fundraise_category'] === 'Limited' && !empty($fundraise['required_amount'])) {
            $remaining = (float)$fundraise['required_amount'] - (float)$fundraise['collected_amount'];
            if ($remaining < $amount) {
                echo json_encode(['success' => false, 'goal_reached' => true]);
                exit;
            }
        }
        
        // Process donation
        try {
            $pdo->beginTransaction();
            
            // Deduct from wallet
            $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance - ? WHERE user_id = ? AND account_activity = 'Active'")->execute([$amount, $user_id]);
            
            // Add to funding records - fundraise_tag_id can be null for donations
            $funding_record_id = generateFundingId();
            $pdo->prepare("
                INSERT INTO event_funding_records 
                (funding_record_id, fundraise_id, fundraise_tag_id, payer_id, funded_amount, funding_date, funding_time)
                VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())
            ")->execute([$funding_record_id, $fundraise_id, $tag_id ?: null, $user_id, $amount]);
            
            // Add wallet transaction
            $transaction_id = generateTransactionId();
            $transaction_details = "Contribution for {$fundraise['fundraise_title']} in the event {$fundraise['event_title']}";
            $pdo->prepare("
                INSERT INTO user_wallet_transactions 
                (transaction_id, user_id, transaction_details, transaction_type, transaction_amount, transaction_date, transaction_time)
                VALUES (?, ?, ?, 'Outgoing', ?, CURDATE(), CURTIME())
            ")->execute([$transaction_id, $user_id, $transaction_details, $transaction_amount]);
            
            // Update collected amount
            $pdo->prepare("UPDATE event_fundraise_info SET collected_amount = collected_amount + ? WHERE fundraise_id = ?")->execute([$amount, $fundraise_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Donation successful!']);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fundraise['fundraise_title']) ?> - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <span class="text-2xl font-bold text-indigo-700">EVENTUKIO</span>
        <button onclick="history.back()" class="text-gray-700">Back</button>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-6">
    <?php
    $fund_type = $fundraise['fundraise_type'];
    $fund_category = $fundraise['fundraise_category'];
    $is_contribution = ($fund_type === 'Contribution');
    $is_donation = ($fund_type === 'Donation');
    $is_unlimited = ($fund_category === 'Unlimited');
    $is_limited = ($fund_category === 'Limited');
    ?>

    <?php if ($is_contribution && $is_unlimited): ?>
        <!-- CASE 1: Contribution + Unlimited -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Contribution Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Title</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_title']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Type</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_type']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount collected (TZS)</span>
                    <span class="font-bold text-indigo-600">TZS <?= number_format($fundraise['collected_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-6">
            <h3 class="text-lg font-semibold mb-4">Contribution Tags</h3>
            <?php if (empty($fundraise_tags)): ?>
                <p class="text-gray-500">No tags available.</p>
            <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left py-3">Tag name</th>
                            <th class="text-left py-3">Tag details</th>
                            <th class="text-right py-3">Amount</th>
                            <th class="text-center py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fundraise_tags as $tag): ?>
                        <tr class="border-t">
                            <td class="py-4 font-medium"><?= htmlspecialchars($tag['tag_name']) ?></td>
                            <td class="py-4 text-gray-600"><?= htmlspecialchars($tag['tag_details'] ?? '-') ?></td>
                            <td class="py-4 text-right font-medium">
                                TZS <?= number_format((float)($tag['required_amount'] ?? 0), 2) ?>
                            </td>
                            <td class="py-4 text-center">
                                <button onclick="contributeTag('<?= htmlspecialchars($tag['fundraise_tag_id'] ?? '') ?>', <?= (float)($tag['required_amount'] ?? 0) ?>)" 
                                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-sm transition">
                                    Contribute
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($is_contribution && $is_limited): ?>
        <!-- CASE 2: Contribution + Limited -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Contribution Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Title</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_title']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Type</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_type']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount required (TZS)</span>
                    <span class="font-medium">TZS <?= number_format($fundraise['required_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount collected (TZS)</span>
                    <span class="font-bold text-indigo-600">TZS <?= number_format($fundraise['collected_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-6">
            <h3 class="text-lg font-semibold mb-4">Contribution Tags</h3>
            <?php if (empty($fundraise_tags)): ?>
                <p class="text-gray-500">No tags available.</p>
            <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left py-3">Tag name</th>
                            <th class="text-left py-3">Tag details</th>
                            <th class="text-right py-3">Amount</th>
                            <th class="text-center py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fundraise_tags as $tag): ?>
                        <tr class="border-t">
                            <td class="py-4 font-medium"><?= htmlspecialchars($tag['tag_name']) ?></td>
                            <td class="py-4 text-gray-600"><?= htmlspecialchars($tag['tag_details'] ?? '-') ?></td>
                            <td class="py-4 text-right font-medium">
                                TZS <?= number_format((float)($tag['required_amount'] ?? 0), 2) ?>
                            </td>
                            <td class="py-4 text-center">
                                <button onclick="contributeTag('<?= htmlspecialchars($tag['fundraise_tag_id'] ?? '') ?>', <?= (float)($tag['required_amount'] ?? 0) ?>)" 
                                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-sm transition">
                                    Contribute
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($is_donation && $is_unlimited): ?>
        <!-- CASE 3: Donation + Unlimited -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Donation Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Title</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_title']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Type</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_type']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount collected (TZS)</span>
                    <span class="font-bold text-indigo-600">TZS <?= number_format($fundraise['collected_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-6 text-center">
            <h3 class="text-lg font-semibold mb-4">Make a Donation</h3>
            <p class="text-gray-600 mb-6">Donate any amount to support this fundraise.</p>
            <button onclick="openDonateModal('', 'General Donation')" 
                    class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-semibold transition">
                Make donation
            </button>
        </div>

    <?php elseif ($is_donation && $is_limited): ?>
        <!-- CASE 4: Donation + Limited -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Donation Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Title</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_title']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Type</span>
                    <span class="font-medium"><?= htmlspecialchars($fundraise['fundraise_type']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount required (TZS)</span>
                    <span class="font-medium">TZS <?= number_format($fundraise['required_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total amount collected (TZS)</span>
                    <span class="font-bold text-indigo-600">TZS <?= number_format($fundraise['collected_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-6 text-center">
            <h3 class="text-lg font-semibold mb-4">Make a Donation</h3>
            <p class="text-gray-600 mb-6">Donate any amount to support this fundraise.</p>
            <button onclick="openDonateModal('', 'General Donation')" 
                    class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-semibold transition">
                Make donation
            </button>
        </div>

    <?php endif; ?>
</div>

<!-- Insufficient Funds Modal -->
<div id="insufficientFundsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-wallet text-3xl text-red-500"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Insufficient Funds</h3>
        <p class="text-gray-600 mb-6">Sorry! You have insufficient funds in your wallet!</p>
        <div class="flex gap-3">
            <button onclick="window.location.href='manage-wallet.php'" class="flex-1 py-3 bg-indigo-600 text-white rounded-2xl font-medium hover:bg-indigo-700 transition">
                Visit my wallet
            </button>
            <button onclick="closeInsufficientFundsModal()" class="flex-1 py-3 border border-gray-300 text-gray-700 rounded-2xl font-medium hover:bg-gray-50 transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Goal Reached Modal -->
<div id="goalReachedModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check-circle text-3xl text-green-500"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Goal Reached</h3>
        <p class="text-gray-600 mb-6">Sorry, the funding for this fundraise has reached its maximum amount. Thank you!</p>
        <button onclick="closeGoalReachedModal()" class="w-full py-3 bg-indigo-600 text-white rounded-2xl font-medium hover:bg-indigo-700 transition">
            Close
        </button>
    </div>
</div>

<!-- Donation Amount Modal -->
<div id="donationModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Make Donation</h3>
            <button onclick="closeDonationModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form id="donationForm" onsubmit="submitDonation(event)">
            <input type="hidden" name="tag_id" id="donationTagId">
            <input type="hidden" name="action" value="donate">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount (TZS)</label>
                <input type="number" name="amount" id="donationAmount" required min="1" step="0.01"
                       class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
                       placeholder="Enter amount">
            </div>
            <button type="submit" class="w-full py-3 bg-indigo-600 text-white rounded-2xl font-medium hover:bg-indigo-700 transition">
                Donate
            </button>
        </form>
    </div>
</div>

<script>
    // Modal functions
    function closeInsufficientFundsModal() {
        const modal = document.getElementById('insufficientFundsModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function closeGoalReachedModal() {
        const modal = document.getElementById('goalReachedModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        // Also close donation modal if it's open
        closeDonationModal();
    }

    function closeDonationModal() {
        const modal = document.getElementById('donationModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('donationForm').reset();
    }

    function openDonateModal(tagId, tagName) {
        document.getElementById('donationTagId').value = tagId;
        const modal = document.getElementById('donationModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Contribute to a tag (fixed amount)
    function contributeTag(tagId, amount) {
        const formData = new FormData();
        formData.append('action', 'contribute');
        formData.append('tag_id', tagId);
        formData.append('amount', amount);

        fetch('fundraise.php?event_id=<?= $event_id ?>&fundraise_id=<?= $fundraise_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else if (data.insufficient_funds) {
                const modal = document.getElementById('insufficientFundsModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else if (data.goal_reached) {
                const modal = document.getElementById('goalReachedModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    // Submit donation (custom amount)
    function submitDonation(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('donationForm'));
        
        fetch('fundraise.php?event_id=<?= $event_id ?>&fundraise_id=<?= $fundraise_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeDonationModal();
                location.reload();
            } else if (data.insufficient_funds) {
                // Keep donation modal open, show insufficient funds modal on top
                const modal = document.getElementById('insufficientFundsModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else if (data.goal_reached) {
                // Close donation modal and show goal reached modal
                closeDonationModal();
                const modal = document.getElementById('goalReachedModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    // Close modals on overlay click
    document.getElementById('insufficientFundsModal').addEventListener('click', function(e) {
        if (e.target === this) closeInsufficientFundsModal();
    });

    document.getElementById('goalReachedModal').addEventListener('click', function(e) {
        if (e.target === this) closeGoalReachedModal();
    });

    document.getElementById('donationModal').addEventListener('click', function(e) {
        if (e.target === this) closeDonationModal();
    });
</script>

</body>
</html>