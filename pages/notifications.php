<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();

// ---- Fetch notifications ----

// 1. Verification notification (Redirecting)
$verification = null;
$stmt = $pdo->prepare("SELECT user_validity FROM user_basic_info WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if ($user && $user['user_validity'] == 'Registered') {
    $verification = [
        'type' => 'redirect',
        'message' => 'Congratulations on registering your account! Please click to proceed with the verification!',
        'redirect_url' => 'update-profile.php',
        'icon' => 'fa-check-circle',
        'color' => 'text-green-600'
    ];
}

// 2. Asset rental notifications (Redirecting)
$assetNotifications = [];
$stmt = $pdo->prepare("
    SELECT er.*, uea.owner_id
    FROM event_asset_rentals er
    JOIN user_event_asset uea ON er.asset_id = uea.asset_id
    WHERE uea.owner_id = ?
      AND er.renting_status IN ('Requested', 'Pleaded')
      AND er.lending_status = 'Pending'
");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $assetNotifications[] = [
        'type' => 'redirect',
        'message' => 'There is a user who wants to rent your asset for their event! Click for more details',
        'redirect_url' => 'history.php?tab=assets', // History page Assets tab
        'icon' => 'fa-handshake',
        'color' => 'text-blue-600'
    ];
}

// 3. Service hiring notifications (Redirecting)
$serviceNotifications = [];
$stmt = $pdo->prepare("
    SELECT * FROM event_service_hiring
    WHERE user_id = ?
      AND hire_status = 'Requested'
      AND service_status = 'Pending'
");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $serviceNotifications[] = [
        'type' => 'redirect',
        'message' => 'There is a user who would like to hire you in their event! Click for more details.',
        'redirect_url' => 'history.php?tab=services', // History page Services tab
        'icon' => 'fa-briefcase',
        'color' => 'text-purple-600'
    ];
}

// 4. Incoming funds notifications (Action)
$fundsNotifications = [];
$stmt = $pdo->prepare("
    SELECT fut.*, e.event_title, efi.fundraise_title
    FROM fundraise_user_transactions fut
    JOIN event_basic_info e ON fut.event_id = e.event_id
    JOIN event_fundraise_info efi ON fut.fundraise_id = efi.fundraise_id
    WHERE fut.user_id = ?
      AND fut.transaction_permission IN ('Allowed', 'Waiting')
      AND fut.acceptance_status = 'Waiting'
");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $fundsNotifications[] = [
        'type' => 'action',
        'action' => 'funds',
        'message' => 'There is incoming funds transferred to you. Click to receive!',
        'icon' => 'fa-coins',
        'color' => 'text-yellow-600',
        'data' => $row
    ];
}

// 5. Asset return notifications (Action)
$returnNotifications = [];
$stmt = $pdo->prepare("
    SELECT eret.*, uea.owner_id, uea.asset_name, uea.asset_quantity,
           er.rented_quantity, e.event_title
    FROM event_asset_returns eret
    JOIN event_asset_rentals er ON eret.rental_id = er.rental_id
    JOIN user_event_asset uea ON eret.asset_id = uea.asset_id
    JOIN event_basic_info e ON eret.event_id = e.event_id
    WHERE uea.owner_id = ?
      AND eret.payment_status = 'Paid'
      AND eret.reception_status = 'Waiting'
      AND er.renting_status = 'Returned'
");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $returnNotifications[] = [
        'type' => 'action',
        'action' => 'asset_return',
        'message' => 'The asset rented from you is now being returned back to you. Click to receive!',
        'icon' => 'fa-arrow-left',
        'color' => 'text-green-600',
        'data' => $row
    ];
}

// Combine all notifications into one array (order: redirect first? we can show all)
$notifications = array_merge(
    $verification ? [$verification] : [],
    $assetNotifications,
    $serviceNotifications,
    $fundsNotifications,
    $returnNotifications
);

// If no notifications, show empty state
$hasNotifications = !empty($notifications);

// ---- Handle POST actions ----

// Accept incoming funds
if (isset($_POST['accept_funds']) && isset($_POST['fundraiseuser_id'])) {
    $fundraiseuser_id = $_POST['fundraiseuser_id'];
    // Fetch the record
    $stmt = $pdo->prepare("SELECT * FROM fundraise_user_transactions WHERE fundraiseuser_id = ? AND user_id = ? AND transaction_permission = 'Allowed' AND acceptance_status = 'Waiting'");
    $stmt->execute([$fundraiseuser_id, $user_id]);
    $fund = $stmt->fetch();
    if ($fund) {
        // Update acceptance_status
        $stmt2 = $pdo->prepare("UPDATE fundraise_user_transactions SET acceptance_status = 'Accepted' WHERE fundraiseuser_id = ?");
        $stmt2->execute([$fundraiseuser_id]);

        // Insert into user_wallet_transactions
        $stmt3 = $pdo->prepare("INSERT INTO user_wallet_transactions (user_id, account_id, transaction_details, transaction_amount, transaction_date, transaction_time, transaction_type) VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'Incoming')");
        $stmt3->execute([$fund['user_id'], $fund['account_id'], $fund['transaction_details'], $fund['transaction_amount']]);

        // Add to wallet balance
        $stmt4 = $pdo->prepare("UPDATE user_wallet_info SET account_balance = account_balance + ? WHERE user_id = ? AND account_id = ?");
        $stmt4->execute([$fund['transaction_amount'], $fund['user_id'], $fund['account_id']]);

        // Redirect to refresh
        redirect('pages/notifications.php');
    }
}

// Confirm asset return
if (isset($_POST['confirm_return']) && isset($_POST['return_id'])) {
    $return_id = $_POST['return_id'];
    // Fetch the return record with owner check
    $stmt = $pdo->prepare("
        SELECT eret.*, uea.owner_id, uea.asset_id, uea.asset_quantity
        FROM event_asset_returns eret
        JOIN user_event_asset uea ON eret.asset_id = uea.asset_id
        WHERE eret.return_id = ? AND uea.owner_id = ? AND eret.reception_status = 'Waiting'
    ");
    $stmt->execute([$return_id, $user_id]);
    $ret = $stmt->fetch();
    if ($ret) {
        // Update reception_status
        $stmt2 = $pdo->prepare("UPDATE event_asset_returns SET reception_status = 'Received' WHERE return_id = ?");
        $stmt2->execute([$return_id]);

        // Add returned quantity back to asset_quantity, set asset_status to Available
        $stmt3 = $pdo->prepare("UPDATE user_event_asset SET asset_quantity = asset_quantity + ?, asset_status = 'Available' WHERE asset_id = ?");
        $stmt3->execute([$ret['returned_quantity'], $ret['asset_id']]);

        // Redirect to refresh
        redirect('pages/notifications.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .notification-card { transition: all 0.2s; cursor: pointer; }
        .notification-card:hover { transform: scale(1.01); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .modal-overlay {
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .modal-box { max-width: 500px; width: 90%; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<!-- HEADER -->
<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Notifications</h1>
        <a href="#" onclick="history.back(); return false;" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-arrow-left"></i> Return
        </a>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="max-w-4xl mx-auto px-4 py-8">
    <h2 class="text-3xl font-bold mb-8">Your Notifications</h2>

    <?php if (!$hasNotifications): ?>
        <div class="glass rounded-3xl p-12 text-center">
            <i class="fa fa-bell-slash text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">No new notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($notifications as $notif): ?>
                <?php if ($notif['type'] == 'redirect'): ?>
                    <!-- Redirecting Notification Card -->
                    <a href="<?= htmlspecialchars($notif['redirect_url']) ?>" class="block notification-card glass rounded-3xl p-6 flex items-center gap-4">
                        <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $notif['color'] ?? 'text-indigo-600' ?>">
                            <i class="fa <?= $notif['icon'] ?? 'fa-bell' ?> text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium"><?= htmlspecialchars($notif['message']) ?></p>
                            <p class="text-xs text-gray-500 mt-1">Click to proceed</p>
                        </div>
                        <i class="fa fa-chevron-right text-gray-400"></i>
                    </a>
                <?php else: ?>
                    <!-- Action Notification Card -->
                    <div class="notification-card glass rounded-3xl p-6 flex items-center gap-4" onclick="openModal(this)" data-action="<?= $notif['action'] ?>" data-json='<?= json_encode($notif['data']) ?>'>
                        <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $notif['color'] ?? 'text-indigo-600' ?>">
                            <i class="fa <?= $notif['icon'] ?? 'fa-bell' ?> text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium"><?= htmlspecialchars($notif['message']) ?></p>
                            <p class="text-xs text-gray-500 mt-1">Click to view details</p>
                        </div>
                        <i class="fa fa-chevron-right text-gray-400"></i>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL CONTAINER (hidden by default) -->
<div id="notificationModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden">
    <div class="modal-box glass rounded-3xl p-8 shadow-2xl relative">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <i class="fa fa-times text-xl"></i>
        </button>
        <div id="modalContent">
            <!-- Dynamically filled -->
        </div>
    </div>
</div>

<script>
    let modalData = null;
    const modal = document.getElementById('notificationModal');
    const modalContent = document.getElementById('modalContent');

    function openModal(element) {
        const action = element.dataset.action;
        const data = JSON.parse(element.dataset.json);
        modalData = { action, data };

        let html = '';
        if (action === 'funds') {
            // Incoming funds modal
            html = `
                <h3 class="text-2xl font-bold mb-4">Incoming Funds</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>Event:</strong> ${data.event_title || 'N/A'}</p>
                    <p><strong>Fundraise:</strong> ${data.fundraise_title || 'N/A'}</p>
                    <p><strong>Transaction Details:</strong> ${data.transaction_details || 'N/A'}</p>
                    <p><strong>Amount:</strong> TZS ${parseFloat(data.transaction_amount).toFixed(2)}</p>
                    <p><strong>Date:</strong> ${data.transaction_date || 'N/A'}</p>
                    <p><strong>Time:</strong> ${data.transaction_time || 'N/A'}</p>
                </div>
                <div class="flex gap-4 mt-6">
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-2xl">Close</button>
                    ${data.transaction_permission === 'Allowed' ? `
                        <form method="POST" action="" class="flex-1">
                            <input type="hidden" name="fundraiseuser_id" value="${data.fundraiseuser_id}">
                            <button type="submit" name="accept_funds" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl">Accept</button>
                        </form>
                    ` : `
                        <button disabled class="flex-1 bg-gray-400 text-white px-4 py-3 rounded-2xl cursor-not-allowed">Accept (pending)</button>
                    `}
                </div>
            `;
        } else if (action === 'asset_return') {
            // Asset return modal
            html = `
                <h3 class="text-2xl font-bold mb-4">Asset Return Confirmation</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>Event:</strong> ${data.event_title || 'N/A'}</p>
                    <p><strong>Asset:</strong> ${data.asset_name || 'N/A'}</p>
                    <p><strong>Rented Quantity:</strong> ${data.rented_quantity || 0}</p>
                    <p><strong>Returned Quantity:</strong> ${data.returned_quantity || 0}</p>
                    <p><strong>Return Date:</strong> ${data.returned_date || 'N/A'}</p>
                    <p><strong>Return Time:</strong> ${data.returned_time || 'N/A'}</p>
                </div>
                <div class="flex gap-4 mt-6">
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-2xl">Close</button>
                    <form method="POST" action="" class="flex-1">
                        <input type="hidden" name="return_id" value="${data.return_id}">
                        <button type="submit" name="confirm_return" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-2xl">Confirm</button>
                    </form>
                </div>
            `;
        } else {
            html = `<p class="text-center text-gray-500">Unknown notification type.</p><button onclick="closeModal()" class="mt-4 bg-gray-200 px-4 py-2 rounded-2xl">Close</button>`;
        }
        modalContent.innerHTML = html;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        modalData = null;
    }

    // Close modal on background click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
</script>

</body>
</html>