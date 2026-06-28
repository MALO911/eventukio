<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$user_id = $_SESSION['user_id'];
$event_id = (int)($_GET['event_id'] ?? 0);

if ($event_id <= 0) {
    errorMsg("Invalid Event");
    redirect('pages/attend-events.php');
}

// Verify user is an Accepted Service Provider for this event
$service_query = $pdo->prepare("
    SELECT esh.hire_id, esh.user_id, esh.profile_id, esh.hire_amount, esh.payment_status,
           esh.hire_status, esh.service_status, esh.presence_status,
           uej.profession_category, uej.profession_title, uej.job_average_rating,
           ubi.user_full_name, ubi.user_profile_picture
    FROM event_service_hiring esh
    JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
    JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
    WHERE esh.event_id = ? AND esh.user_id = ? 
      AND esh.service_status = 'Accepted' AND esh.presence_status = 'Active'
");
$service_query->execute([$event_id, $user_id]);
$service_provider = $service_query->fetch();

if (!$service_provider) {
    errorMsg("You are not authorized as a service provider for this event.");
    redirect('pages/attend-events.php');
}

// Get event details
$event_query = $pdo->prepare("
    SELECT event_id, event_title, host_id, event_activeness, 
           groupchat_permission, privatechat_permission
    FROM event_basic_info 
    WHERE event_id = ?
");
$event_query->execute([$event_id]);
$event = $event_query->fetch();

if (!$event) {
    redirect('pages/attend-events.php');
}

// Check if event is In Session
if ($event['event_activeness'] !== 'In Session') {
    errorMsg("This event is no longer active");
    redirect('pages/events.php');
}

// Get event population summary
$invited_query = $pdo->prepare("
    SELECT COUNT(*) as count FROM event_invitees 
    WHERE event_id = ? AND attendance_status != 'Denied'
");
$invited_query->execute([$event_id]);
$invited_count = $invited_query->fetch()['count'];

$attendees_query = $pdo->prepare("
    SELECT COUNT(*) as count FROM event_attendees 
    WHERE event_id = ? AND participation_status = 'Active'
");
$attendees_query->execute([$event_id]);
$attendees_count = $attendees_query->fetch()['count'];

$providers_query = $pdo->prepare("
    SELECT COUNT(*) as count FROM event_service_hiring 
    WHERE event_id = ? AND presence_status = 'Active'
");
$providers_query->execute([$event_id]);
$providers_count = $providers_query->fetch()['count'];

// Get all service providers for this event (for the list)
$all_providers_query = $pdo->prepare("
    SELECT esh.user_id, esh.profile_id, esh.hire_amount,
           ubi.user_full_name, ubi.user_profile_picture,
           uej.profession_category, uej.profession_title, uej.job_average_rating,
           esh.presence_status
    FROM event_service_hiring esh
    JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
    JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
    WHERE esh.event_id = ? AND esh.service_status = 'Accepted'
    ORDER BY uej.profession_category ASC
");
$all_providers_query->execute([$event_id]);
$all_providers = $all_providers_query->fetchAll();

// Get attendees list
$attendees_list_query = $pdo->prepare("
    SELECT ea.participant_id, ea.participation_badge,
           ubi.user_full_name, ubi.user_profile_picture, ubi.user_phone_number,
           ei.invitation_badge, ei.invitation_position
    FROM event_attendees ea
    JOIN user_basic_info ubi ON ea.participant_id = ubi.user_id
    JOIN event_invitees ei ON ea.invitee_id = ei.invitee_id
    WHERE ea.event_id = ? AND ea.participation_status = 'Active'
    ORDER BY ubi.user_full_name ASC
");
$attendees_list_query->execute([$event_id]);
$attendees_list = $attendees_list_query->fetchAll();

// Get user's existing ratings for service providers
$ratings_query = $pdo->prepare("
    SELECT reviewer_id, profile_id, user_rating, user_review
    FROM event_service_ratings
    WHERE reviewer_id = ?
");
$ratings_query->execute([$user_id]);
$ratings_map = [];
while ($row = $ratings_query->fetch()) {
    $ratings_map[$row['profile_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serve - <?php echo htmlspecialchars($event['event_title']); ?> - Eventukio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.12); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.25); }
        .service-card {
            transition: all 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .star {
            font-size: 2.5rem;
            cursor: pointer;
            transition: 0.2s;
            color: #d1d5db;
        }
        .star:hover, .star.active { color: #fbbf24; }
        .star.active { color: #fbbf24; }
        .profile-pic {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(99,102,241,0.3);
        }
        .profile-pic-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .attendee-row {
            transition: all 0.2s ease;
        }
        .attendee-row:hover {
            background: rgba(255,255,255,0.1);
        }
        .badge-host {
            background: #7c3aed;
            color: white;
        }
        .badge-server {
            background: #059669;
            color: white;
        }
        .badge-normal {
            background: #4b5563;
            color: white;
        }
        .badge-co-host {
            background: #d97706;
            color: white;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

    <!-- Header - Same as Event Page for Service Providers -->
    <header class="glass sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-xl font-bold text-indigo-700">EVENTUKIO</span>
                <div class="hidden sm:block h-6 w-px bg-gray-300"></div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($event['event_title']); ?></p>
                    <p class="text-xs text-gray-500"><i class="fas fa-briefcase text-green-500 mr-1"></i>Service Mode</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="event-page.php?id=<?php echo $event_id; ?>" class="text-gray-600 hover:text-indigo-700 transition p-2 rounded-full hover:bg-white/20">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-6">

        <!-- Service Provider Welcome Card -->
        <div class="glass rounded-3xl p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-briefcase text-3xl text-green-600"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($service_provider['user_full_name']); ?></h2>
                    <p class="text-gray-600 text-sm">
                        <span class="font-medium"><?php echo htmlspecialchars($service_provider['profession_title']); ?></span>
                        <span class="text-gray-400 mx-1">•</span>
                        <?php echo htmlspecialchars($service_provider['profession_category']); ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                        Rating: <?php echo number_format($service_provider['job_average_rating'] ?? 0, 1); ?> / 5.0
                    </p>
                </div>
                <div class="ml-auto text-right">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                        <i class="fas fa-check-circle mr-1"></i>Active
                    </span>
                    <p class="text-xs text-gray-400 mt-1">Payment: <?php echo $service_provider['payment_status']; ?></p>
                </div>
            </div>
        </div>

        <!-- Event Population Summary -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="glass rounded-xl p-4 text-center stat-card">
                <p class="text-xs text-gray-500">Invited</p>
                <p class="text-2xl font-bold text-indigo-600"><?php echo $invited_count; ?></p>
            </div>
            <div class="glass rounded-xl p-4 text-center stat-card">
                <p class="text-xs text-gray-500">Attended</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $attendees_count; ?></p>
            </div>
            <div class="glass rounded-xl p-4 text-center stat-card">
                <p class="text-xs text-gray-500">Providers</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $providers_count; ?></p>
            </div>
        </div>

        <!-- Attendees List -->
        <div class="glass rounded-3xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-users text-indigo-500 mr-2"></i>
                Attendees List
                <span class="text-sm font-normal text-gray-400">(<?php echo count($attendees_list); ?> active)</span>
            </h3>

            <?php if (count($attendees_list) === 0): ?>
                <p class="text-gray-500 text-center py-6">No active attendees at the moment.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200/50">
                                <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Attendee</th>
                                <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase hidden sm:table-cell">Badge</th>
                                <th class="text-center py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees_list as $attendee): 
                                $badge_class = 'badge-normal';
                                if ($attendee['invitation_badge'] === 'Host') $badge_class = 'badge-host';
                                elseif ($attendee['invitation_badge'] === 'Co-host') $badge_class = 'badge-co-host';
                                elseif ($attendee['invitation_badge'] === 'Server') $badge_class = 'badge-server';
                                $attendee_profile_url = getProfilePictureUrl($attendee['user_profile_picture'] ?? '');
                            ?>
                            <tr class="attendee-row border-b border-gray-100/30">
                                <td class="py-3 px-2">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($attendee_profile_url); ?>" 
                                             alt="<?php echo htmlspecialchars($attendee['user_full_name']); ?>" 
                                             class="profile-pic-sm">
                                        <div>
                                            <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($attendee['user_full_name']); ?></p>
                                            <p class="text-xs text-gray-400 hidden sm:block"><?php echo htmlspecialchars($attendee['invitation_position']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-2 hidden sm:table-cell">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($attendee['invitation_badge']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <button onclick="viewAttendee('<?php echo htmlspecialchars($attendee['participant_id']); ?>', '<?php echo htmlspecialchars($attendee['user_full_name']); ?>', '<?php echo htmlspecialchars($attendee['user_phone_number']); ?>', '<?php echo htmlspecialchars($attendee['invitation_badge']); ?>', '<?php echo htmlspecialchars($attendee['invitation_position']); ?>', '<?php echo htmlspecialchars($attendee_profile_url); ?>')" 
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Service Providers List -->
        <div class="glass rounded-3xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user-tie text-indigo-500 mr-2"></i>
                Service Providers
                <span class="text-sm font-normal text-gray-400">(<?php echo count($all_providers); ?> active)</span>
            </h3>

            <?php if (count($all_providers) === 0): ?>
                <p class="text-gray-500 text-center py-6">No service providers assigned to this event.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($all_providers as $provider): 
                        $is_self = ($provider['user_id'] === $user_id);
                        $has_rated = isset($ratings_map[$provider['profile_id']]);
                        $existing_rating = $has_rated ? $ratings_map[$provider['profile_id']]['user_rating'] : 0;
                        $existing_review = $has_rated ? $ratings_map[$provider['profile_id']]['user_review'] : '';
                        $profile_img = htmlspecialchars(getProfilePictureUrl($provider['user_profile_picture'] ?? ''));
                    ?>
                        <div class="service-card glass rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4 hover:shadow-lg transition <?php echo $is_self ? 'border-2 border-green-400' : ''; ?>">
                            <!-- Profile Picture -->
                            <div class="flex-shrink-0">
                                <img src="<?php echo $profile_img; ?>" 
                                     alt="<?php echo htmlspecialchars($provider['user_full_name']); ?>" 
                                     class="profile-pic">
                            </div>
                            
                            <!-- Provider Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-semibold text-gray-800 text-sm">
                                        <?php echo htmlspecialchars($provider['user_full_name']); ?>
                                    </h4>
                                    <?php if ($is_self): ?>
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">You</span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center text-xs text-gray-500">
                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                        <?php echo number_format($provider['job_average_rating'] ?? 0, 1); ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                    <span><?php echo htmlspecialchars($provider['profession_category']); ?></span>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($provider['profession_title']); ?></span>
                                    <?php if (!$is_self): ?>
                                        <span>•</span>
                                        <span class="text-gray-400">TZS <?php echo number_format($provider['hire_amount'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <?php if (!$is_self): ?>
                                <button onclick="openRatingModal('<?php echo htmlspecialchars($provider['profile_id']); ?>', '<?php echo htmlspecialchars($provider['user_full_name']); ?>', <?php echo $existing_rating; ?>, '<?php echo addslashes($existing_review); ?>')"
                                        class="flex-shrink-0 px-5 py-2 rounded-full text-sm font-medium transition <?php echo $has_rated ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'; ?>">
                                    <?php echo $has_rated ? '<i class="fas fa-check mr-1"></i> Rated' : 'Rate'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Attendee View Modal -->
    <div id="attendeeModal" class="modal-overlay">
        <div class="modal-content glass rounded-3xl p-8 bg-white/95 backdrop-blur-lg text-center">
            <button onclick="closeAttendeeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition text-2xl leading-none">
                <i class="fas fa-times"></i>
            </button>
            <div class="flex flex-col items-center">
                <img id="attendeeModalPic" src="" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-indigo-200 mb-4">
                <h3 id="attendeeModalName" class="text-xl font-bold text-gray-800"></h3>
                <p id="attendeeModalPhone" class="text-gray-600 text-sm"></p>
                <div class="flex gap-3 mt-2">
                    <span id="attendeeModalBadge" class="inline-block px-3 py-1 rounded-full text-sm font-medium"></span>
                    <span id="attendeeModalPosition" class="inline-block px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600"></span>
                </div>
                <button onclick="closeAttendeeModal()" class="mt-6 px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="ratingModal" class="modal-overlay">
        <div class="modal-content glass rounded-3xl p-8 bg-white/95 backdrop-blur-lg">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-star text-yellow-400 mr-2"></i>
                    Rate <span id="ratingProviderName" class="text-indigo-600"></span>
                </h3>
                <button onclick="closeRatingModal()" class="text-gray-400 hover:text-gray-600 transition text-2xl leading-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="ratingForm" method="POST" action="submit-rating.php">
                <input type="hidden" name="profile_id" id="ratingProfileId">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                <input type="hidden" name="rating" id="ratingValue" value="0">

                <div class="text-center mb-6">
                    <div class="flex justify-center gap-2" id="modalStars">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2" id="ratingLabel">Select a rating</p>
                </div>

                <div class="mb-6">
                    <label for="reviewText" class="block text-sm font-medium text-gray-700 mb-2">Your Review (Optional)</label>
                    <textarea id="reviewText" name="review" rows="4" 
                              class="glass w-full rounded-2xl px-5 py-4 border-0 focus:ring-2 focus:ring-indigo-400"
                              placeholder="Share your experience with this service provider..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeRatingModal()" 
                            class="flex-1 py-3 rounded-2xl border border-gray-300 text-gray-700 hover:bg-gray-50 transition font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Rating
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Attendee Modal
        function viewAttendee(id, name, phone, badge, position, profilePic) {
            document.getElementById('attendeeModalPic').src = profilePic;
            document.getElementById('attendeeModalName').textContent = name;
            document.getElementById('attendeeModalPhone').textContent = phone;
            
            const badgeEl = document.getElementById('attendeeModalBadge');
            badgeEl.textContent = badge;
            if (badge === 'Host') badgeEl.className = 'inline-block px-3 py-1 rounded-full text-sm font-medium badge-host';
            else if (badge === 'Co-host') badgeEl.className = 'inline-block px-3 py-1 rounded-full text-sm font-medium badge-co-host';
            else if (badge === 'Server') badgeEl.className = 'inline-block px-3 py-1 rounded-full text-sm font-medium badge-server';
            else badgeEl.className = 'inline-block px-3 py-1 rounded-full text-sm font-medium badge-normal';
            
            document.getElementById('attendeeModalPosition').textContent = position;
            document.getElementById('attendeeModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAttendeeModal() {
            document.getElementById('attendeeModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('attendeeModal').addEventListener('click', function(e) {
            if (e.target === this) closeAttendeeModal();
        });

        // Rating Modal
        let selectedRating = 0;
        let currentProfileId = null;

        const modalStars = document.querySelectorAll('#modalStars .star');
        const ratingValueInput = document.getElementById('ratingValue');
        const ratingLabel = document.getElementById('ratingLabel');

        modalStars.forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.dataset.value);
                ratingValueInput.value = selectedRating;

                modalStars.forEach(s => s.classList.remove('active'));
                for (let i = 0; i < selectedRating; i++) {
                    modalStars[i].classList.add('active');
                }

                const labels = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                ratingLabel.textContent = labels[selectedRating - 1] || 'Select a rating';
                ratingLabel.className = 'text-sm mt-2 font-medium ' + 
                    (selectedRating <= 2 ? 'text-red-500' : 
                     selectedRating === 3 ? 'text-yellow-500' : 
                     'text-green-500');
            });
        });

        function openRatingModal(profileId, providerName, existingRating, existingReview) {
            currentProfileId = profileId;
            document.getElementById('ratingProfileId').value = profileId;
            document.getElementById('ratingProviderName').textContent = providerName;
            document.getElementById('reviewText').value = existingReview || '';

            modalStars.forEach(s => s.classList.remove('active'));
            selectedRating = existingRating || 0;
            ratingValueInput.value = selectedRating;

            if (selectedRating > 0) {
                for (let i = 0; i < selectedRating; i++) {
                    modalStars[i].classList.add('active');
                }
                const labels = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                ratingLabel.textContent = labels[selectedRating - 1];
                ratingLabel.className = 'text-sm mt-2 font-medium ' + 
                    (selectedRating <= 2 ? 'text-red-500' : 
                     selectedRating === 3 ? 'text-yellow-500' : 
                     'text-green-500');
            } else {
                ratingLabel.textContent = 'Select a rating';
                ratingLabel.className = 'text-sm mt-2 text-gray-500';
            }

            document.getElementById('ratingModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeRatingModal() {
            document.getElementById('ratingModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('ratingModal').addEventListener('click', function(e) {
            if (e.target === this) closeRatingModal();
        });

        document.getElementById('ratingForm').addEventListener('submit', function(e) {
            if (selectedRating === 0) {
                e.preventDefault();
                alert('Please select a rating (1-5 stars) before submitting.');
                return false;
            }
            return true;
        });
    </script>

</body>
</html>