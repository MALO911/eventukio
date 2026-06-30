<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Ensure user is logged in and verified/registered
if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();

// Handle deposit/withdraw POST requests
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $gateway_id = $_POST['gateway_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $pin_or_password = $_POST['pin_or_password'] ?? '';

        if ($action === 'deposit') {
            // Validate gateway belongs to user
            $gatewayStmt = $pdo->prepare("SELECT * FROM user_gateway_info WHERE gateway_id = ? AND user_id = ? AND account_status = 'Active'");
            $gatewayStmt->execute([$gateway_id, $user_id]);
            $gateway = $gatewayStmt->fetch();
            if (!$gateway) {
                $message = 'Invalid gateway selected.';
                $messageType = 'error';
            } elseif ($amount <= 0) {
                $message = 'Amount must be greater than zero.';
                $messageType = 'error';
            } elseif ($amount > 100000000) {
                $message = 'Maximum deposit limit is 100,000,000 TZS.';
                $messageType = 'error';
            } else {
                // For deposit, we only need the PIN (max 4 digits) but we just simulate; no actual PIN validation in DB.
                // We'll just require a 4-digit PIN (any number) as per spec: "Enter your ____ PIN".
                if (!preg_match('/^\d{4}$/', $pin_or_password)) {
                    $message = 'Please enter a valid 4-digit PIN.';
                    $messageType = 'error';
                } else {
                    // Process deposit
                    try {
                        $pdo->beginTransaction();

                        // Update wallet balance
                        $updateWallet = $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance + ? WHERE user_id = ?");
                        $updateWallet->execute([$amount, $user_id]);

                        // Insert transaction record
                        $details = "Deposit from {$gateway['gateway_brand']} through {$gateway['gateway_account_number']}";
                        $insertTrans = $pdo->prepare("INSERT INTO user_wallet_transactions (user_id, account_id, transaction_details, transaction_amount, transaction_date, transaction_time, transaction_type, transaction_validity) VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'Deposit', 'Valid')");
                        $insertTrans->execute([$user_id, $gateway['account_id'], $details, $amount]);

                        $pdo->commit();
                        $message = 'Deposit successful!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'Deposit failed: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            }
        } elseif ($action === 'withdraw') {
            // Validate gateway
            $gatewayStmt = $pdo->prepare("SELECT * FROM user_gateway_info WHERE gateway_id = ? AND user_id = ? AND account_status = 'Active'");
            $gatewayStmt->execute([$gateway_id, $user_id]);
            $gateway = $gatewayStmt->fetch();
            if (!$gateway) {
                $message = 'Invalid gateway selected.';
                $messageType = 'error';
            } elseif ($amount <= 0) {
                $message = 'Amount must be greater than zero.';
                $messageType = 'error';
            } else {
                // Verify password
                $userStmt = $pdo->prepare("SELECT user_password FROM user_basic_info WHERE user_id = ?");
                $userStmt->execute([$user_id]);
                $user = $userStmt->fetch();
                if (!$user || !password_verify($pin_or_password, $user['user_password'])) {
                    $message = 'Invalid password.';
                    $messageType = 'error';
                } else {
                    // Check sufficient balance
                    $walletStmt = $pdo->prepare("SELECT account_balance FROM user_wallet_info WHERE user_id = ?");
                    $walletStmt->execute([$user_id]);
                    $wallet = $walletStmt->fetch();
                    if ($wallet['account_balance'] < $amount) {
                        $message = 'Insufficient balance.';
                        $messageType = 'error';
                    } else {
                        // Process withdrawal
                        try {
                            $pdo->beginTransaction();

                            // Update wallet
                            $updateWallet = $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance - ? WHERE user_id = ?");
                            $updateWallet->execute([$amount, $user_id]);

                            // Insert transaction
                            $details = "Withdrawal into {$gateway['gateway_brand']} through {$gateway['gateway_account_number']}";
                            $insertTrans = $pdo->prepare("INSERT INTO user_wallet_transactions (user_id, account_id, transaction_details, transaction_amount, transaction_date, transaction_time, transaction_type, transaction_validity) VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'Withdrawal', 'Valid')");
                            $insertTrans->execute([$user_id, $gateway['account_id'], $details, $amount]);

                            $pdo->commit();
                            $message = 'Withdrawal successful!';
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $message = 'Withdrawal failed: ' . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
            }
        } else {
            $message = 'Invalid action.';
            $messageType = 'error';
        }
    }
}

// Handle filter by month (via GET)
$filterMonth = isset($_GET['month']) ? $_GET['month'] : '';
$filterYear = isset($_GET['year']) ? $_GET['year'] : '';

// Build transactions query
$transQuery = "SELECT * FROM user_wallet_transactions WHERE user_id = ? AND transaction_validity = 'Valid'";
$params = [$user_id];
if (!empty($filterMonth) && !empty($filterYear)) {
    $transQuery .= " AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
    $params[] = $filterMonth;
    $params[] = $filterYear;
}
$transQuery .= " ORDER BY transaction_date DESC, transaction_time DESC LIMIT 30";
$transStmt = $pdo->prepare($transQuery);
$transStmt->execute($params);
$transactions = $transStmt->fetchAll();

// Handle PDF download
if (isset($_GET['download'])) {
    // Re-fetch transactions for PDF (same filter)
    $pdfQuery = "SELECT * FROM user_wallet_transactions WHERE user_id = ? AND transaction_validity = 'Valid'";
    $pdfParams = [$user_id];
    if (!empty($filterMonth) && !empty($filterYear)) {
        $pdfQuery .= " AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
        $pdfParams[] = $filterMonth;
        $pdfParams[] = $filterYear;
    }
    $pdfQuery .= " ORDER BY transaction_date DESC, transaction_time DESC";
    $pdfStmt = $pdo->prepare($pdfQuery);
    $pdfStmt->execute($pdfParams);
    $pdfTransactions = $pdfStmt->fetchAll();

    // Generate PDF using TCPDF from the project root tcpdf folder
    require_once(__DIR__ . '/../tcpdf/tcpdf.php');
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Eventukio');
    $pdf->SetAuthor('Eventukio User');
    $pdf->SetTitle('Transaction History');
    $pdf->SetSubject('Transaction History');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $html = '<h1>Transaction History</h1>';
    if (!empty($filterMonth) && !empty($filterYear)) {
        $html .= '<p>Filtered by: ' . date('F Y', mktime(0,0,0, $filterMonth, 1, $filterYear)) . '</p>';
    }
    $html .= '<table border="1" cellpadding="4">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Transaction Type</th>
                        <th>Transaction Amount</th>
                        <th>Transaction Details</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($pdfTransactions as $t) {
        $html .= '<tr>
                    <td>' . $t['transaction_date'] . '</td>
                    <td>' . $t['transaction_time'] . '</td>
                    <td>' . $t['transaction_type'] . '</td>
                    <td>' . number_format($t['transaction_amount'], 2) . '</td>
                    <td>' . htmlspecialchars($t['transaction_details']) . '</td>
                </tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('transaction_history.pdf', 'D');
    exit;
}

// Get wallet info
$walletStmt = $pdo->prepare("SELECT * FROM user_wallet_info WHERE user_id = ?");
$walletStmt->execute([$user_id]);
$wallet = $walletStmt->fetch();
$balance = $wallet ? $wallet['account_balance'] : 0;

// Get gateways
$gatewayStmt = $pdo->prepare("SELECT * FROM user_gateway_info WHERE user_id = ? AND account_status = 'Active'");
$gatewayStmt->execute([$user_id]);
$gateways = $gatewayStmt->fetchAll();

// Determine current month/year for filter
$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = $filterMonth ?: $currentMonth;
$selectedYear = $filterYear ?: $currentYear;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Wallet - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 50; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { max-width: 400px; width: 90%; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-40">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Manage Wallet</h1>
        <a href="account.php" class="text-gray-700 hover:text-indigo-700">Account</a>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-xl <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Wallet Card -->
    <div class="glass rounded-3xl p-8 mb-8 flex flex-col md:flex-row items-center justify-between">
        <div class="text-center md:text-left mb-4 md:mb-0">
            <p class="text-sm text-gray-400">Current Balance</p>
            <p class="text-5xl font-bold text-green-400 mt-2">TZS <?= number_format($balance, 2) ?></p>
        </div>
        <div class="flex flex-wrap gap-4">
            <!-- Deposit Dropdown -->
            <div class="relative">
                <button id="depositDropdownBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-medium flex items-center">
                    <i class="fas fa-arrow-down mr-2"></i> Deposit
                </button>
                <div id="depositDropdown" class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg overflow-hidden z-20 hidden">
                    <?php if (empty($gateways)): ?>
                        <div class="p-4 text-gray-500 text-sm">No active gateways linked. Please add one in settings.</div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($gateways as $g): ?>
                                <li class="border-b last:border-0">
                                    <button class="deposit-gateway-btn w-full text-left px-4 py-3 hover:bg-gray-100 transition flex items-center justify-between" 
                                            data-gateway-id="<?= $g['gateway_id'] ?>" 
                                            data-brand="<?= htmlspecialchars($g['gateway_brand']) ?>" 
                                            data-account="<?= htmlspecialchars($g['gateway_account_number']) ?>">
                                        <span><?= htmlspecialchars($g['gateway_brand']) ?></span>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($g['gateway_account_number']) ?></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Withdraw Dropdown -->
            <div class="relative">
                <button id="withdrawDropdownBtn" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-2xl font-medium flex items-center">
                    <i class="fas fa-arrow-up mr-2"></i> Withdraw
                </button>
                <div id="withdrawDropdown" class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg overflow-hidden z-20 hidden">
                    <?php if (empty($gateways)): ?>
                        <div class="p-4 text-gray-500 text-sm">No active gateways linked.</div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($gateways as $g): ?>
                                <li class="border-b last:border-0">
                                    <button class="withdraw-gateway-btn w-full text-left px-4 py-3 hover:bg-gray-100 transition flex items-center justify-between"
                                            data-gateway-id="<?= $g['gateway_id'] ?>"
                                            data-brand="<?= htmlspecialchars($g['gateway_brand']) ?>"
                                            data-account="<?= htmlspecialchars($g['gateway_account_number']) ?>">
                                        <span><?= htmlspecialchars($g['gateway_brand']) ?></span>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($g['gateway_account_number']) ?></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="glass rounded-3xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <h3 class="font-semibold text-lg">Transaction History</h3>
            <div class="flex flex-wrap items-center gap-3 mt-2 md:mt-0">
                <!-- Month Filter -->
                <form method="GET" class="flex items-center gap-2">
                    <input type="number" name="month" min="1" max="12" value="<?= $selectedMonth ?>" class="w-16 px-2 py-1 rounded border border-gray-300 text-sm" placeholder="MM">
                    <input type="number" name="year" min="2000" max="2099" value="<?= $selectedYear ?>" class="w-20 px-2 py-1 rounded border border-gray-300 text-sm" placeholder="YYYY">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1 rounded-lg text-sm">Filter</button>
                    <?php if (!empty($filterMonth) && !empty($filterYear)): ?>
                        <a href="manage-wallet.php" class="text-gray-500 hover:text-indigo-600 text-sm">Clear</a>
                    <?php endif; ?>
                </form>
                <!-- Download PDF -->
                <a href="?download=1<?= (!empty($filterMonth) && !empty($filterYear)) ? '&month='.$filterMonth.'&year='.$filterYear : '' ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded-lg text-sm flex items-center">
                    <i class="fas fa-file-pdf mr-1"></i> Download
                </a>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
            <p class="text-center text-gray-500 py-12">No transactions found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-white/10">
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3">Time</th>
                            <th class="py-2 px-3">Type</th>
                            <th class="py-2 px-3 text-right">Amount</th>
                            <th class="py-2 px-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition">
                                <td class="py-2 px-3"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                <td class="py-2 px-3"><?= $t['transaction_time'] ?></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-1 rounded-full text-xs <?= in_array($t['transaction_type'], ['Deposit','Incoming']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $t['transaction_type'] ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-right font-medium <?= in_array($t['transaction_type'], ['Deposit','Incoming']) ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= ($t['transaction_type'] == 'Deposit' || $t['transaction_type'] == 'Incoming' ? '+' : '-') ?> TZS <?= number_format($t['transaction_amount'], 2) ?>
                                </td>
                                <td class="py-2 px-3 text-gray-600"><?= htmlspecialchars($t['transaction_details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Deposit Modal -->
<div id="depositModal" class="modal-overlay">
    <div class="modal-content glass rounded-3xl p-6">
        <h3 class="text-xl font-semibold mb-4 text-center">Deposit Funds</h3>
        <form method="POST" id="depositForm">
            <input type="hidden" name="action" value="deposit">
            <input type="hidden" name="gateway_id" id="deposit_gateway_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Gateway</label>
                <p id="deposit_gateway_label" class="mt-1 text-gray-600"></p>
            </div>
            <div class="mb-4">
                <label for="deposit_amount" class="block text-sm font-medium text-gray-700">Enter the amount (TZS) to deposit</label>
                <input type="number" name="amount" id="deposit_amount" min="0.01" step="0.01" required class="w-full mt-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label for="deposit_pin" class="block text-sm font-medium text-gray-700">Enter your <span id="deposit_brand_name">____</span> PIN</label>
                <input type="password" name="pin_or_password" id="deposit_pin" maxlength="4" pattern="\d{4}" required class="w-full mt-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="4-digit PIN">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" id="closeDepositModal" class="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white">Deposit</button>
            </div>
        </form>
    </div>
</div>

<!-- Withdraw Modal -->
<div id="withdrawModal" class="modal-overlay">
    <div class="modal-content glass rounded-3xl p-6">
        <h3 class="text-xl font-semibold mb-4 text-center">Withdraw Funds</h3>
        <form method="POST" id="withdrawForm">
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="gateway_id" id="withdraw_gateway_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Gateway</label>
                <p id="withdraw_gateway_label" class="mt-1 text-gray-600"></p>
            </div>
            <div class="mb-4">
                <label for="withdraw_amount" class="block text-sm font-medium text-gray-700">Enter the amount (TZS) to withdraw</label>
                <input type="number" name="amount" id="withdraw_amount" min="0.01" step="0.01" required class="w-full mt-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label for="withdraw_password" class="block text-sm font-medium text-gray-700">Enter your Eventukio password</label>
                <input type="password" name="pin_or_password" id="withdraw_password" required class="w-full mt-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Your account password">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" id="closeWithdrawModal" class="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white">Withdraw</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dropdown toggles
    const depositBtn = document.getElementById('depositDropdownBtn');
    const depositDropdown = document.getElementById('depositDropdown');
    const withdrawBtn = document.getElementById('withdrawDropdownBtn');
    const withdrawDropdown = document.getElementById('withdrawDropdown');

    depositBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        depositDropdown.classList.toggle('hidden');
        withdrawDropdown.classList.add('hidden');
    });
    withdrawBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        withdrawDropdown.classList.toggle('hidden');
        depositDropdown.classList.add('hidden');
    });
    document.addEventListener('click', () => {
        depositDropdown.classList.add('hidden');
        withdrawDropdown.classList.add('hidden');
    });

    // Deposit gateway selection
    document.querySelectorAll('.deposit-gateway-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const gatewayId = this.dataset.gatewayId;
            const brand = this.dataset.brand;
            const account = this.dataset.account;
            document.getElementById('deposit_gateway_id').value = gatewayId;
            document.getElementById('deposit_gateway_label').textContent = brand + ' (' + account + ')';
            document.getElementById('deposit_brand_name').textContent = brand;
            document.getElementById('depositModal').classList.add('active');
            depositDropdown.classList.add('hidden');
        });
    });

    // Withdraw gateway selection
    document.querySelectorAll('.withdraw-gateway-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const gatewayId = this.dataset.gatewayId;
            const brand = this.dataset.brand;
            const account = this.dataset.account;
            document.getElementById('withdraw_gateway_id').value = gatewayId;
            document.getElementById('withdraw_gateway_label').textContent = brand + ' (' + account + ')';
            document.getElementById('withdrawModal').classList.add('active');
            withdrawDropdown.classList.add('hidden');
        });
    });

    // Close modals
    document.getElementById('closeDepositModal').addEventListener('click', () => {
        document.getElementById('depositModal').classList.remove('active');
    });
    document.getElementById('closeWithdrawModal').addEventListener('click', () => {
        document.getElementById('withdrawModal').classList.remove('active');
    });
    // Click outside modal content to close
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });
</script>

</body>
</html>