<?php
require_once '../config/config.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    redirect('pages/login.php');
}

$event_id = (int)($_GET['id'] ?? 0);
if ($event_id <= 0) {
    errorMsg("Invalid Event");
    redirect('pages/history.php');
}

// Fetch only closed events with location details
$stmt = $pdo->prepare("
    SELECT e.*, u.user_full_name, u.user_profile_picture,
           uea.asset_location_specifics, uea.asset_street, uea.asset_district, uea.asset_region
    FROM event_basic_info e
    JOIN user_basic_info u ON e.host_id = u.user_id
    LEFT JOIN user_event_asset uea ON e.venue_id = uea.asset_id
    WHERE e.event_id = ? AND e.event_activeness = 'Closed'
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    errorMsg("Event profile is only available for completed events.");
    redirect('pages/history.php');
}

// Fetch media based on event_ad_media type
$media = null;
if ($event['event_ad_media'] === 'Image') {
    // Get latest images from event_ad_images
    $stmt = $pdo->prepare("
        SELECT image_a, image_b, image_c, image_d
        FROM event_ad_images
        WHERE event_id = ?
        ORDER BY images_upload_date DESC, images_upload_time DESC
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $media = $stmt->fetch();
} elseif ($event['event_ad_media'] === 'Video') {
    // Get latest video from event_ad_video
    $stmt = $pdo->prepare("
        SELECT video_uploaded
        FROM event_ad_video
        WHERE event_id = ?
        ORDER BY video_upload_date DESC, video_upload_time DESC
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $media = $stmt->fetch();
}

// Fetch shared media for Gallery tab
$stmt = $pdo->prepare("
    SELECT esm.*, ubi.user_full_name
    FROM event_shared_media esm
    JOIN user_basic_info ubi ON esm.uploader_id = ubi.user_id
    WHERE esm.event_id = ? AND esm.media_validity = 'Valid'
    ORDER BY esm.upload_datetime DESC
");
$stmt->execute([$event_id]);
$sharedMedia = $stmt->fetchAll();

// Fetch Event Population Summary counts
// Invited People (not denied)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_invitees WHERE event_id = ? AND attendance_status != 'Denied'");
$stmt->execute([$event_id]);
$invitedCount = $stmt->fetch()['count'];

// Attended People (active)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_attendees WHERE event_id = ? AND participation_status = 'Active'");
$stmt->execute([$event_id]);
$attendedCount = $stmt->fetch()['count'];

// Service Providers (active)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_service_hiring WHERE event_id = ? AND presence_status = 'Active'");
$stmt->execute([$event_id]);
$serviceProvidersCount = $stmt->fetch()['count'];

// Fetch Attendees List
$stmt = $pdo->prepare("
    SELECT ea.*, ubi.user_full_name, ubi.user_profile_picture, ubi.user_phone_number,
           ei.invitation_badge, ei.invitation_position
    FROM event_attendees ea
    JOIN user_basic_info ubi ON ea.participant_id = ubi.user_id
    LEFT JOIN event_invitees ei ON ea.invitee_id = ei.invitee_id
    WHERE ea.event_id = ? AND ea.participation_status = 'Active'
");
$stmt->execute([$event_id]);
$attendees = $stmt->fetchAll();

// Fetch Service Providers List
$stmt = $pdo->prepare("
    SELECT esh.*, ubi.user_full_name, ubi.user_profile_picture,
           uej.profession_category, uej.profession_title, uej.job_average_rating
    FROM event_service_hiring esh
    JOIN user_basic_info ubi ON esh.user_id = ubi.user_id
    JOIN user_event_jobs uej ON esh.profile_id = uej.profile_id
    WHERE esh.event_id = ? AND esh.service_status = 'Accepted' AND esh.presence_status = 'Active'
");
$stmt->execute([$event_id]);
$serviceProviders = $stmt->fetchAll();

// Fetch Fundraises for Fundraises tab
$stmt = $pdo->prepare("
    SELECT * FROM event_fundraise_info
    WHERE event_id = ? AND fundraise_status = 'Compiled' AND fundraise_duration != 'Post-event'
    ORDER BY creation_date DESC, creation_time DESC
");
$stmt->execute([$event_id]);
$fundraises = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Profile - <?= htmlspecialchars($event['event_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.2); }
        .tab-active { border-bottom: 4px solid #6366f1; color: #6366f1; font-weight: 600; }
        body { padding-bottom: 80px; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

<header class="glass sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-indigo-700">Event Profile</h1>
        <button onclick="history.back()" class="text-gray-700 hover:text-indigo-700">
            <i class="fa fa-arrow-left"></i> Back
        </button>
    </div>
</header>

<div class="max-w-5xl mx-auto px-4 py-6">
    <!-- Event Header -->
    <div class="glass rounded-3xl overflow-hidden mb-8">
        <div class="h-64 bg-gradient-to-br from-indigo-600 to-purple-600 relative">
            <div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black/70">
                <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($event['event_title']) ?></h1>
                <p class="text-indigo-200"><?= htmlspecialchars($event['event_category']) ?> • Completed Event</p>
            </div>
        </div>
    </div>

    <!-- TAB CONTENTS -->
    <div id="tabContent">
        <!-- Summary -->
        <div class="tab-panel">
            <div class="space-y-6">
                <!-- Event Details -->
                <div class="glass rounded-3xl p-8">
                    <h3 class="font-semibold mb-6 text-xl">Event Details</h3>
                    <div class="space-y-4">
                        <p><strong>Event Title:</strong> <?= htmlspecialchars($event['event_title']) ?></p>
                        <p><strong>Event Category:</strong> <?= htmlspecialchars($event['event_category']) ?></p>
                        <p><strong>Event Date:</strong> <?= date('d F Y', strtotime($event['event_date'])) ?></p>
                        <p><strong>Event Time:</strong> <?= date('H:i', strtotime($event['event_time'])) ?></p>
                        <p><strong>Event Type:</strong> <?= htmlspecialchars($event['event_type']) ?></p>
                        <?php
                        $location = implode(', ', array_filter([
                            $event['asset_location_specifics'],
                            $event['asset_street'],
                            $event['asset_district'],
                            $event['asset_region']
                        ])) ?: 'Location not specified';
                        ?>
                        <p><strong>Event Location:</strong> <?= htmlspecialchars($location) ?></p>
                        <p><strong>Event Host:</strong> <?= htmlspecialchars($event['user_full_name']) ?></p>
                    </div>
                </div>

                <!-- Media Section -->
                <div class="glass rounded-3xl p-8">
                    <h3 class="font-semibold mb-6 text-xl">Media Section</h3>
                    <?php if ($media): ?>
                        <?php if ($event['event_ad_media'] === 'Image'): ?>
                            <!-- Image Slideshow -->
                            <div class="relative">
                                <div id="slideshow" class="relative h-96 bg-gray-200 rounded-2xl overflow-hidden">
                                    <?php if ($media['image_a']): ?>
                                        <img src="../uploads/events/<?= htmlspecialchars(basename($media['image_a'])) ?>" class="slide absolute inset-0 w-full h-full object-cover" alt="Event Image A">
                                    <?php endif; ?>
                                    <?php if ($media['image_b']): ?>
                                        <img src="../uploads/events/<?= htmlspecialchars(basename($media['image_b'])) ?>" class="slide absolute inset-0 w-full h-full object-cover hidden" alt="Event Image B">
                                    <?php endif; ?>
                                    <?php if ($media['image_c']): ?>
                                        <img src="../uploads/events/<?= htmlspecialchars(basename($media['image_c'])) ?>" class="slide absolute inset-0 w-full h-full object-cover hidden" alt="Event Image C">
                                    <?php endif; ?>
                                    <?php if ($media['image_d']): ?>
                                        <img src="../uploads/events/<?= htmlspecialchars(basename($media['image_d'])) ?>" class="slide absolute inset-0 w-full h-full object-cover hidden" alt="Event Image D">
                                    <?php endif; ?>
                                </div>
                                <button onclick="prevSlide()" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/50 text-white p-3 rounded-full hover:bg-black/70">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button onclick="nextSlide()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/50 text-white p-3 rounded-full hover:bg-black/70">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            </div>
                        <?php elseif ($event['event_ad_media'] === 'Video'): ?>
                            <!-- Video -->
                            <div class="h-96 bg-gray-200 rounded-2xl overflow-hidden">
                                <video controls class="w-full h-full">
                                    <source src="../uploads/events/<?= htmlspecialchars(basename($media['video_uploaded'])) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-500">No media available for this event.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gallery -->
        <div class="tab-panel hidden">
            <div class="glass rounded-3xl p-8">
                <h3 class="font-semibold mb-6">Event Gallery</h3>
                <?php if (empty($sharedMedia)): ?>
                    <p class="text-gray-500">No shared media available for this event.</p>
                <?php else: ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($sharedMedia as $item): ?>
                            <div class="relative aspect-square bg-gray-200 rounded-2xl overflow-hidden cursor-pointer hover:opacity-90 transition"
                                 onclick="openModal('<?= htmlspecialchars($item['media_file']) ?>', '<?= htmlspecialchars($item['media_type']) ?>', '<?= htmlspecialchars($item['user_full_name']) ?>')">
                                <?php if ($item['media_type'] === 'Photo'): ?>
                                    <img src="../uploads/event_media/<?= htmlspecialchars(basename($item['media_file'])) ?>" class="w-full h-full object-cover" alt="Shared media">
                                <?php elseif ($item['media_type'] === 'Video'): ?>
                                    <video class="w-full h-full object-cover">
                                        <source src="../uploads/event_media/<?= htmlspecialchars(basename($item['media_file'])) ?>" type="video/mp4">
                                    </video>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                        <i class="fa fa-play-circle text-white text-4xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Crowd -->
        <div class="tab-panel hidden">
            <div class="space-y-6">
                <!-- Event Population Summary -->
                <div class="glass rounded-3xl p-8">
                    <h3 class="font-semibold mb-6 text-xl">Event Population Summary</h3>
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-indigo-100 rounded-2xl p-6 text-center">
                            <p class="text-3xl font-bold text-indigo-600"><?= $invitedCount ?></p>
                            <p class="text-sm text-gray-600 mt-2">Invited People</p>
                        </div>
                        <div class="bg-green-100 rounded-2xl p-6 text-center">
                            <p class="text-3xl font-bold text-green-600"><?= $attendedCount ?></p>
                            <p class="text-sm text-gray-600 mt-2">Attended People</p>
                        </div>
                        <div class="bg-purple-100 rounded-2xl p-6 text-center">
                            <p class="text-3xl font-bold text-purple-600"><?= $serviceProvidersCount ?></p>
                            <p class="text-sm text-gray-600 mt-2">Service Providers</p>
                        </div>
                    </div>
                </div>

                <!-- Attendees List -->
                <div class="glass rounded-3xl p-8">
                    <h3 class="font-semibold mb-6 text-xl">Attendees List</h3>
                    <?php if (empty($attendees)): ?>
                        <p class="text-gray-500">No attendees found for this event.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-4">Name of Attendee</th>
                                        <th class="text-right py-3 px-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendees as $attendee): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-4"><?= htmlspecialchars($attendee['user_full_name']) ?></td>
                                            <td class="py-3 px-4 text-right">
                                                <button onclick="openAttendeeModal(
                                                    '<?= htmlspecialchars($attendee['user_profile_picture'] ?? '') ?>',
                                                    '<?= htmlspecialchars($attendee['user_full_name']) ?>',
                                                    '<?= htmlspecialchars($attendee['user_phone_number'] ?? 'N/A') ?>',
                                                    '<?= htmlspecialchars($attendee['invitation_badge'] ?? 'N/A') ?>',
                                                    '<?= htmlspecialchars($attendee['invitation_position'] ?? 'N/A') ?>'
                                                )" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm">
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
                <div class="glass rounded-3xl p-8">
                    <h3 class="font-semibold mb-6 text-xl">Service Providers List</h3>
                    <?php if (empty($serviceProviders)): ?>
                        <p class="text-gray-500">No service providers found for this event.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($serviceProviders as $sp): ?>
                                <div class="bg-white rounded-2xl p-6 hover:shadow-lg transition cursor-pointer">
                                    <div class="flex items-center gap-4">
                                        <div class="w-16 h-16 rounded-full overflow-hidden flex-shrink-0 bg-gray-200">
                                            <?php if ($sp['user_profile_picture']): ?>
                                                <img src="../uploads/profiles/<?= htmlspecialchars(basename($sp['user_profile_picture'])) ?>" class="w-full h-full object-cover" alt="Profile">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-indigo-100 text-indigo-600">
                                                    <i class="fa fa-user text-2xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold"><?= htmlspecialchars($sp['user_full_name']) ?></h4>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($sp['profession_category']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($sp['profession_title']) ?></p>
                                            <div class="flex items-center mt-2">
                                                <i class="fa fa-star text-yellow-500 mr-1"></i>
                                                <span class="text-sm"><?= htmlspecialchars($sp['job_average_rating']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fundraises -->
        <div class="tab-panel hidden">
            <div class="glass rounded-3xl p-8">
                <h3 class="font-semibold mb-6">Fundraises Summary</h3>
                <?php if (empty($fundraises)): ?>
                    <p class="text-gray-500">No compiled fundraises found for this event.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4">Fundraise Title</th>
                                    <th class="text-left py-3 px-4">Created On</th>
                                    <th class="text-right py-3 px-4">Planned Amount</th>
                                    <th class="text-right py-3 px-4">Collected Amount</th>
                                    <th class="text-right py-3 px-4">Spent Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fundraises as $fund): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4"><?= htmlspecialchars($fund['fundraise_title']) ?></td>
                                        <td class="py-3 px-4">
                                            <?= date('d M Y H:i', strtotime($fund['creation_date'] . ' ' . $fund['creation_time'])) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right"><?= number_format($fund['required_amount']) ?></td>
                                        <td class="py-3 px-4 text-right"><?= number_format($fund['collected_amount']) ?></td>
                                        <td class="py-3 px-4 text-right"><?= number_format($fund['spent_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Footer with Tabs -->
<footer class="glass fixed bottom-0 left-0 right-0 z-40">
    <div class="max-w-5xl mx-auto px-4 py-3 flex justify-around text-sm font-medium">
        <button onclick="switchTab(0)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab0">
            <i class="fa fa-info-circle"></i> Summary
        </button>
        <button onclick="switchTab(1)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab1">
            <i class="fa fa-images"></i> Gallery
        </button>
        <button onclick="switchTab(2)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab2">
            <i class="fa fa-users"></i> Crowd
        </button>
        <button onclick="switchTab(3)" class="tab-footer text-gray-700 hover:text-indigo-700" id="footerTab3">
            <i class="fa fa-hand-holding-dollar"></i> Fundraises
        </button>
    </div>
</footer>

<!-- Media Modal -->
<div id="mediaModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative max-w-5xl w-full max-h-screen">
            <!-- Uploader name (top left) -->
            <div class="absolute top-4 left-4 z-10">
                <span class="bg-black/50 text-white px-4 py-2 rounded-full text-sm">
                    <i class="fa fa-user mr-2"></i>
                    <span id="modalUploaderName"></span>
                </span>
            </div>

            <!-- Action buttons (top right) -->
            <div class="absolute top-4 right-4 z-10 flex gap-2">
                <button onclick="closeModal()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-full text-sm">
                    <i class="fa fa-times mr-2"></i>Close
                </button>
                <button onclick="downloadMedia()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-full text-sm">
                    <i class="fa fa-download mr-2"></i>Download
                </button>
            </div>

            <!-- Media content -->
            <div id="modalContent" class="bg-black rounded-2xl overflow-hidden"></div>
        </div>
    </div>
</div>

<!-- Attendee Modal -->
<div id="attendeeModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeAttendeeModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative max-w-md w-full bg-white rounded-3xl p-8">
            <!-- Profile picture (top center) -->
            <div class="flex justify-center mb-6">
                <div class="w-32 h-32 rounded-full overflow-hidden bg-gray-200 border-4 border-indigo-100">
                    <img id="attendeeProfilePic" src="" class="w-full h-full object-cover hidden" alt="Profile">
                    <div id="attendeeProfilePicPlaceholder" class="w-full h-full flex items-center justify-center bg-indigo-100 text-indigo-600">
                        <i class="fa fa-user text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- User name -->
            <div class="text-center mb-4">
                <h3 id="attendeeName" class="text-xl font-semibold"></h3>
            </div>

            <!-- Phone number -->
            <div class="text-center mb-6">
                <p id="attendeePhone" class="text-gray-600"></p>
            </div>

            <!-- Invitation badge and position -->
            <div class="flex justify-between items-center mb-6">
                <div class="text-left">
                    <p class="text-sm text-gray-500">Invitation Position</p>
                    <p id="attendeePosition" class="font-medium"></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Invitation Badge</p>
                    <p id="attendeeBadge" class="font-medium"></p>
                </div>
            </div>

            <!-- Close button -->
            <div class="text-center">
                <button onclick="closeAttendeeModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-2xl font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(n) {
        document.querySelectorAll('.tab').forEach((tab, i) => {
            tab.classList.toggle('tab-active', i === n);
        });
        document.querySelectorAll('.tab-panel').forEach((panel, i) => {
            panel.classList.toggle('hidden', i !== n);
        });
        document.querySelectorAll('.tab-footer').forEach((tab, i) => {
            tab.classList.toggle('text-indigo-700', i === n);
            tab.classList.toggle('text-gray-700', i !== n);
        });
    }

    // Slideshow functionality
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.toggle('hidden', i !== index);
        });
        currentSlide = index;
    }

    function nextSlide() {
        const nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }

    function prevSlide() {
        const prevIndex = (currentSlide - 1 + slides.length) % slides.length;
        showSlide(prevIndex);
    }

    // Modal functionality
    let currentMediaFile = '';
    let currentMediaType = '';

    function basename(path) {
        return path.split(/[\\\/]/).pop();
    }

    function openModal(mediaFile, mediaType, uploaderName) {
        currentMediaFile = mediaFile;
        currentMediaType = mediaType;
        document.getElementById('modalUploaderName').textContent = uploaderName;

        const modalContent = document.getElementById('modalContent');
        const mediaPath = '../uploads/event_media/' + basename(mediaFile);

        if (mediaType === 'Photo') {
            modalContent.innerHTML = `<img src="${mediaPath}" class="w-full h-full object-contain max-h-screen" alt="Shared media">`;
        } else if (mediaType === 'Video') {
            modalContent.innerHTML = `
                <video controls class="w-full h-full max-h-screen">
                    <source src="${mediaPath}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `;
        }

        document.getElementById('mediaModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('mediaModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
        document.getElementById('modalContent').innerHTML = '';
    }

    function downloadMedia() {
        const mediaPath = '../uploads/event_media/' + basename(currentMediaFile);
        const link = document.createElement('a');
        link.href = mediaPath;
        link.download = currentMediaFile;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Attendee Modal functionality
    function openAttendeeModal(profilePic, userName, phoneNumber, badge, position) {
        const profilePicElement = document.getElementById('attendeeProfilePic');
        const profilePicPlaceholder = document.getElementById('attendeeProfilePicPlaceholder');

        if (profilePic && profilePicElement) {
            profilePicElement.src = '../uploads/profiles/' + basename(profilePic);
            profilePicElement.classList.remove('hidden');
            profilePicPlaceholder.classList.add('hidden');
        } else {
            profilePicElement.classList.add('hidden');
            profilePicPlaceholder.classList.remove('hidden');
        }

        document.getElementById('attendeeName').textContent = userName;
        document.getElementById('attendeePhone').textContent = phoneNumber;
        document.getElementById('attendeeBadge').textContent = badge;
        document.getElementById('attendeePosition').textContent = position;

        document.getElementById('attendeeModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAttendeeModal() {
        document.getElementById('attendeeModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Default to Summary
    switchTab(0);
</script>
</body>
</html>