<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = getCurrentUserId();
$event_id = (int)($_GET['event_id'] ?? 0);

if ($event_id <= 0) {
    redirect('pages/attend-events.php');
}

// Check if user is a Normal Attendee of this event
$stmt = $pdo->prepare("
    SELECT 1 FROM event_attendees 
    WHERE participant_id = ? AND event_id = ? AND participation_status = 'Active'
");
$stmt->execute([$user_id, $event_id]);
if ($stmt->rowCount() === 0) {
    redirect('pages/attend-events.php');
}

// Get event details
$stmt = $pdo->prepare("SELECT event_title FROM event_basic_info WHERE event_id = ?");
$stmt->execute([$event_id]);
$event_data = $stmt->fetch();

// Get service providers
$stmt = $pdo->prepare("
    SELECT 
        esh.hire_id,
        esh.user_id,
        esh.profile_id,
        esh.hire_amount,
        esh.payment_status,
        ubi.user_full_name,
        ubi.user_profile_picture,
        uej.profession_category,
        uej.profession_title,
        uej.job_average_rating,
        uej.task_count
    FROM event_service_hiring esh
    JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
    JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
    WHERE esh.event_id = ? 
      AND esh.service_status = 'Accepted'
      AND esh.presence_status = 'Active'
    ORDER BY uej.profession_category ASC
");
$stmt->execute([$event_id]);
$service_providers = $stmt->fetchAll();

// Get existing ratings by this user
$stmt = $pdo->prepare("
    SELECT profile_id, user_rating, user_review
    FROM event_service_ratings
    WHERE reviewer_id = ?
");
$stmt->execute([$user_id]);
$ratings_map = [];
while ($row = $stmt->fetch()) {
    $ratings_map[$row['profile_id']] = $row;
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $profile_id = (int)$_POST['profile_id'];
    $user_rating = (int)$_POST['user_rating'];
    $user_review = trim($_POST['user_review'] ?? '');

    if ($user_rating >= 1 && $user_rating <= 5) {
        // Check if rating already exists
        $check_stmt = $pdo->prepare("
            SELECT rating_id FROM event_service_ratings
            WHERE reviewer_id = ? AND profile_id = ?
        ");
        $check_stmt->execute([$user_id, $profile_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            // Update existing rating
            $update_stmt = $pdo->prepare("
                UPDATE event_service_ratings
                SET user_rating = ?, user_review = ?
                WHERE reviewer_id = ? AND profile_id = ?
            ");
            $update_stmt->execute([$user_rating, $user_review, $user_id, $profile_id]);
        } else {
            // Insert new rating
            $insert_stmt = $pdo->prepare("
                INSERT INTO event_service_ratings
                (reviewer_id, profile_id, user_rating, user_review)
                VALUES (?, ?, ?, ?)
            ");
            $insert_stmt->execute([$user_id, $profile_id, $user_rating, $user_review]);
        }

        // Update the service provider's average rating and task count
        $avg_stmt = $pdo->prepare("
            UPDATE user_event_jobs uej
            SET job_average_rating = (
                SELECT AVG(user_rating) FROM event_service_ratings WHERE profile_id = uej.profile_id
            ),
            task_count = task_count + 1
            WHERE profile_id = ?
        ");
        $avg_stmt->execute([$profile_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Service - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .star { font-size: 2.5rem; cursor: pointer; transition: 0.2s; color: #d1d5db; }
        .star:hover, .star.active { color: #fbbf24; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-indigo-700">Rate Service</h1>
        <button onclick="history.back()" class="text-gray-700">Back</button>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="glass rounded-3xl p-8">
        <h2 class="text-2xl font-semibold text-center mb-2">Rate the Service Providers</h2>
        <p class="text-center text-gray-600 mb-8">Rate and review the service providers who served at this event</p>

        <?php if (empty($service_providers)): ?>
            <div class="text-center py-12">
                <p class="text-gray-500">No active service providers to rate at this moment.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($service_providers as $provider): 
                    $has_rated = isset($ratings_map[$provider['profile_id']]);
                    $existing_rating = $has_rated ? $ratings_map[$provider['profile_id']]['user_rating'] : 0;
                    $existing_review = $has_rated ? $ratings_map[$provider['profile_id']]['user_review'] : '';
                ?>
                    <div class="glass rounded-3xl p-6">
                        <div class="flex items-center gap-4">
                            <img src="<?= htmlspecialchars(getProfilePictureUrl($provider['user_profile_picture'] ?? '')) ?>" 
                                 class="w-16 h-16 rounded-full object-cover" alt="">
                            <div class="flex-1">
                                <h3 class="font-semibold"><?= htmlspecialchars($provider['user_full_name']) ?></h3>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($provider['profession_title']) ?></p>
                            </div>
                            <button onclick="openRatingModal('<?= htmlspecialchars($provider['profile_id']) ?>', '<?= htmlspecialchars($provider['user_full_name']) ?>', <?= $existing_rating ?>, '<?= addslashes($existing_review) ?>')" 
                                    class="px-6 py-3 <?= $has_rated ? 'bg-green-600' : 'bg-indigo-600' ?> text-white rounded-2xl">
                                <?= $has_rated ? 'Already Rated' : 'Rate Now' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rating Modal -->
<div id="ratingModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/60">
    <div class="glass rounded-3xl p-8 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Rate <span id="providerName" class="text-indigo-600"></span></h3>
        
        <div class="flex justify-center gap-1 mb-6" id="modalStars">
            <?php for($i=1; $i<=5; $i++): ?>
                <span class="star text-4xl" data-value="<?= $i ?>">★</span>
            <?php endfor; ?>
        </div>

        <textarea id="reviewText" rows="4" class="glass w-full rounded-2xl px-5 py-4 mb-6" placeholder="Your review (optional)"></textarea>

        <div class="flex gap-3">
            <button onclick="closeRatingModal()" class="flex-1 py-3 bg-gray-300 rounded-2xl">Close</button>
            <button onclick="submitRating()" class="flex-1 py-3 bg-indigo-600 text-white rounded-2xl">Submit</button>
        </div>
    </div>
</div>

<script>
    let currentProfileId = null;
    let selectedRating = 0;

    function openRatingModal(profileId, name, existingRating, existingReview) {
        currentProfileId = profileId;
        document.getElementById('providerName').textContent = name;
        document.getElementById('reviewText').value = existingReview || '';
        
        // Reset stars
        document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
        selectedRating = existingRating || 0;
        if (selectedRating > 0) {
            for (let i = 0; i < selectedRating; i++) {
                document.querySelectorAll('.star')[i].classList.add('active');
            }
        }
        
        document.getElementById('ratingModal').classList.remove('hidden');
    }

    function closeRatingModal() {
        document.getElementById('ratingModal').classList.add('hidden');
    }

    // Star click handler
    document.querySelectorAll('.star').forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.value);
            document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
            for (let i = 0; i < selectedRating; i++) {
                document.querySelectorAll('.star')[i].classList.add('active');
            }
        });
    });

    function submitRating() {
        if (selectedRating === 0) {
            alert("Please select a rating (1-5 stars)");
            return;
        }

        const reviewText = document.getElementById('reviewText').value;

        const formData = new FormData();
        formData.append('submit_rating', 'true');
        formData.append('profile_id', currentProfileId);
        formData.append('user_rating', selectedRating);
        formData.append('user_review', reviewText);

        fetch('rate-service.php?event_id=<?= $event_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Thank you! Your rating has been submitted.');
                closeRatingModal();
                location.reload();
            } else {
                alert(data.message || 'Failed to submit rating');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting your rating');
        });
    }
</script>

</body>
</html>