<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Fetch public announced events sorted by date and time (latest to earliest) with location data
$stmt = $pdo->prepare("SELECT e.*, u.user_full_name, u.user_profile_picture, 
                               a.asset_region, a.asset_district, a.asset_id
                      FROM event_basic_info e 
                      JOIN user_basic_info u ON e.host_id = u.user_id 
                      LEFT JOIN user_event_asset a ON e.venue_id = a.asset_id
                      WHERE e.event_type = 'Public' 
                        AND e.event_activeness = 'Announced' 
                      ORDER BY e.event_date DESC, e.event_time DESC");
$stmt->execute();
$allEvents = $stmt->fetchAll();

// Fetch Your Area events (will be populated via AJAX with geolocation)
$yourAreaEvents = [];

// Handle AJAX request for location-based events
if (isset($_GET['action']) && $_GET['action'] === 'getAreaEvents' && isset($_GET['region']) && isset($_GET['district'])) {
    $region = clean($_GET['region']);
    $district = clean($_GET['district']);
    
    $stmt = $pdo->prepare("SELECT e.*, u.user_full_name, u.user_profile_picture, 
                                   a.asset_region, a.asset_district, a.asset_id
                          FROM event_basic_info e 
                          JOIN user_basic_info u ON e.host_id = u.user_id 
                          LEFT JOIN user_event_asset a ON e.venue_id = a.asset_id
                          WHERE e.event_type = 'Public' 
                            AND e.event_activeness = 'Announced'
                            AND a.asset_region = ?
                            AND a.asset_district = ?
                          ORDER BY e.event_date DESC, e.event_time DESC");
    $stmt->execute([$region, $district]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as &$row) {
        $row['user_profile_picture'] = getProfilePictureUrl(
            $row['user_profile_picture'] ?? '',
            BASE_URL . 'assets/images/default.png',
            'absolute'
        );
    }
    unset($row);
    header('Content-Type: application/json');
    echo json_encode($events);
    exit;
}

// Helper function to get latest event media
function getEventMedia($pdo, $event_id, $media_type) {
    if ($media_type === 'Image') {
        $stmt = $pdo->prepare("SELECT image_a, image_b, image_c, image_d 
                              FROM event_ad_images 
                              WHERE event_id = ? 
                              ORDER BY images_upload_date DESC, images_upload_time DESC 
                              LIMIT 1");
        $stmt->execute([$event_id]);
        return $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT video_uploaded 
                              FROM event_ad_video 
                              WHERE event_id = ? 
                              ORDER BY video_upload_date DESC, video_upload_time DESC 
                              LIMIT 1");
        $stmt->execute([$event_id]);
        return $stmt->fetch();
    }
}

function normalizeUrlPath($path) {
    return str_replace('\\', '/', $path);
}

function assetUrl($path, $fallback = '') {
    if (empty($path)) {
        return $fallback;
    }
    return BASE_URL . ltrim(normalizeUrlPath($path), '/');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventukio - Discover Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .event-card {
            transition: all 0.3s ease;
        }
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .hidden { display: none; }
        main { padding-bottom: 120px; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

    <!-- HEADER -->
    <header class="glass sticky top-0 z-50 border-b border-white/30">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <!-- Logo -->
                <div class="flex items-center">
                    <h1 class="text-3xl font-bold text-indigo-700 tracking-tight">
                        <span class="text-4xl">E</span>VENTUKIO
                    </h1>
                </div>

                <!-- Search -->
                <div class="hidden md:flex flex-1 max-w-xl mx-8">
                    <div class="relative w-full">
                        <input type="text" id="searchInput" 
                               class="w-full glass border border-white/30 rounded-2xl px-5 py-3 text-sm focus:outline-none focus:border-indigo-400"
                               placeholder="Search events...">
                        <button onclick="searchEvents()" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-indigo-600 text-white px-6 py-2 rounded-xl text-sm font-medium">
                            Search
                        </button>
                    </div>
                </div>

                <!-- Right side -->
                <div class="flex items-center gap-3">
                    <button onclick="toggleLanguage()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-white/50 rounded-2xl transition">
                        <i class="fa fa-globe"></i> Badili Lugha
                    </button>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="profile.php" class="flex items-center gap-2">
                            <img src="<?= htmlspecialchars(getProfilePictureUrl(getCurrentUser()['user_profile_picture'] ?? '', BASE_URL . 'assets/images/default.png', 'absolute')) ?>" 
                                 class="w-9 h-9 rounded-full object-cover border-2 border-white" alt="">
                        </a>
                    <?php else: ?>
                        <a href="login.php" 
                           class="px-6 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-white/70 rounded-2xl transition">
                            Login
                        </a>
                        <a href="register.php" 
                           class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-2xl transition">
                            Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-3xl mx-auto px-4 py-8">
        <h2 class="text-4xl font-bold text-center text-gray-800 mb-10">Upcoming Events</h2>

        <!-- TAB 1: All Events -->
        <div id="allEventsTab" class="space-y-6">
            <?php foreach ($allEvents as $event): 
                $ticketsAvailable = $event['event_tickets'] - $event['event_tickets_sold'];
                $media = getEventMedia($pdo, $event['event_id'], $event['event_ad_media']);
            ?>
                <div class="event-card glass rounded-3xl overflow-hidden border border-white/30 p-6">
                    <!-- Top Section: Profile & Basic Info -->
                    <div class="mb-6">
                        <div class="flex items-center gap-4 mb-4">
                            <!-- User Profile Picture -->
                            <img src="<?= htmlspecialchars(getProfilePictureUrl($event['user_profile_picture'] ?? '', BASE_URL . 'assets/images/default.png', 'absolute')) ?>" 
                                 class="w-14 h-14 rounded-full object-cover border-2 border-indigo-400 flex-shrink-0" alt="Host">
                            <!-- User Name -->
                            <div>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($event['user_full_name']) ?></p>
                                <p class="text-xs text-gray-500">Event Host</p>
                            </div>
                        </div>

                        <!-- Event Title -->
                        <h3 class="font-bold text-2xl text-gray-800 mb-2"><?= htmlspecialchars($event['event_title']) ?></h3>
                        
                        <!-- Event Category -->
                        <p class="text-indigo-600 font-semibold text-sm mb-4"><?= htmlspecialchars($event['event_category']) ?></p>

                        <!-- Event Details Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-calendar"></i> Date</p>
                                <p class="font-semibold text-sm"><?= date('d M Y', strtotime($event['event_date'])) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-clock"></i> Time</p>
                                <p class="font-semibold text-sm"><?= date('H:i', strtotime($event['event_time'])) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-map-marker"></i> Location</p>
                                <p class="font-semibold text-sm"><?= htmlspecialchars(($event['asset_district'] ?? 'N/A') . ', ' . ($event['asset_region'] ?? 'N/A')) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-ticket"></i> Tickets</p>
                                <p class="font-semibold text-sm text-indigo-700"><?= max(0, $ticketsAvailable) ?> Available</p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-users"></i> Attendees</p>
                                <p class="font-semibold text-sm"><?= $event['event_tickets_sold'] ?> Booked</p>
                            </div>
                        </div>
                    </div>

                    <!-- Media Section -->
                    <div class="mb-6 rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-600 h-64 flex items-center justify-center">
                        <?php if ($event['event_ad_media'] === 'Image' && $media): ?>
                            <!-- Image Slideshow -->
                            <div class="relative w-full h-full">
                                <img id="slide-<?= $event['event_id'] ?>-0" src="<?= htmlspecialchars(assetUrl($media['image_a'] ?? '')) ?>" 
                                     class="w-full h-full object-cover" alt="Event Image">
                                <?php if ($media['image_b'] || $media['image_c'] || $media['image_d']): ?>
                                    <button onclick="prevSlide(<?= $event['event_id'] ?>)" class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full">
                                        <i class="fa fa-chevron-left"></i>
                                    </button>
                                    <button onclick="nextSlide(<?= $event['event_id'] ?>)" class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full">
                                        <i class="fa fa-chevron-right"></i>
                                    </button>
                                    <div id="slides-data-<?= $event['event_id'] ?>" class="hidden">
                                        <?php 
                                            $slides = array_filter([$media['image_a'], $media['image_b'], $media['image_c'], $media['image_d']]);
                                            echo json_encode(array_values(array_map(function($img) {
                                                return assetUrl($img, '');
                                            }, $slides)));
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($event['event_ad_media'] === 'Video' && $media): ?>
                            <!-- Video -->
                            <video width="100%" height="100%" controls class="w-full h-full object-cover">
                                <source src="<?= htmlspecialchars(assetUrl($media['video_uploaded'] ?? '')) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fa fa-image text-white text-4xl mb-4"></i>
                                <p class="text-white text-sm">No media available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Event Description (if available) -->
                    <p class="text-gray-600 text-sm mb-6 line-clamp-3"><?= htmlspecialchars($event['event_description'] ?? 'No description provided') ?></p>

                    <!-- Book Now Button (redirects to login) -->
                    <a href="login.php" 
                       class="w-full block bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-semibold text-center transition">
                        <i class="fa fa-ticket-alt"></i> Book Now
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (empty($allEvents)): ?>
                <div class="text-center py-16">
                    <i class="fa fa-calendar text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No announced events at the moment.</p>
                    <p class="text-gray-400 text-sm mt-2">Check back soon for upcoming events!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Your Area -->
        <div id="yourAreaTab" class="space-y-6 hidden">
            <?php foreach ($yourAreaEvents as $event): 
                $ticketsAvailable = $event['event_tickets'] - $event['event_tickets_sold'];
                $media = getEventMedia($pdo, $event['event_id'], $event['event_ad_media']);
            ?>
                <div class="event-card glass rounded-3xl overflow-hidden border border-white/30 p-6">
                    <!-- Same card structure -->
                    <div class="mb-6">
                        <div class="flex items-center gap-4 mb-4">
                            <img src="<?= htmlspecialchars(getProfilePictureUrl($event['user_profile_picture'] ?? '', BASE_URL . 'assets/images/default.png', 'absolute')) ?>" 
                                 class="w-14 h-14 rounded-full object-cover border-2 border-indigo-400 flex-shrink-0" alt="Host">
                            <div>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($event['user_full_name']) ?></p>
                                <p class="text-xs text-gray-500">Event Host</p>
                            </div>
                        </div>

                        <h3 class="font-bold text-2xl text-gray-800 mb-2"><?= htmlspecialchars($event['event_title']) ?></h3>
                        <p class="text-indigo-600 font-semibold text-sm mb-4"><?= htmlspecialchars($event['event_category']) ?></p>

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-calendar"></i> Date</p>
                                <p class="font-semibold text-sm"><?= date('d M Y', strtotime($event['event_date'])) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-clock"></i> Time</p>
                                <p class="font-semibold text-sm"><?= date('H:i', strtotime($event['event_time'])) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-map-marker"></i> Location</p>
                                <p class="font-semibold text-sm"><?= htmlspecialchars(($event['asset_district'] ?? 'N/A') . ', ' . ($event['asset_region'] ?? 'N/A')) ?></p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-ticket"></i> Tickets</p>
                                <p class="font-semibold text-sm text-indigo-700"><?= max(0, $ticketsAvailable) ?> Available</p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-users"></i> Attendees</p>
                                <p class="font-semibold text-sm"><?= $event['event_tickets_sold'] ?> Booked</p>
                            </div>
                        </div>

                    <p class="text-gray-600 text-sm mb-6 line-clamp-3"><?= htmlspecialchars($event['event_description'] ?? 'No description provided') ?></p>

                    <a href="login.php" 
                       class="w-full block bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-semibold text-center transition">
                        <i class="fa fa-ticket-alt"></i> Book Now
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (empty($yourAreaEvents)): ?>
                <div class="text-center py-16">
                    <i class="fa fa-map-marker text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No events in your area at the moment.</p>
                    <p class="text-gray-400 text-sm mt-2">Enable location access to see events near you.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- STICKY FOOTER - Two Tab Navigation -->
    <footer class="glass fixed bottom-0 left-0 right-0 z-40 border-t border-white/30">
        <div class="max-w-7xl mx-auto px-4 flex justify-center">
            <button onclick="switchTab('all')" id="allTabBtn" 
                    class="flex-1 max-w-xs px-6 py-4 text-center font-semibold text-indigo-700 border-b-4 border-indigo-700 bg-indigo-50/30 transition hover:bg-indigo-100/20">
                <i class="fa fa-calendar mr-2"></i> All Events
            </button>
            <button onclick="switchTab('area')" id="areaTabBtn" 
                    class="flex-1 max-w-xs px-6 py-4 text-center font-semibold text-gray-600 border-b-4 border-transparent transition hover:text-indigo-700 hover:bg-white/10">
                <i class="fa fa-map-marker mr-2"></i> Your Area
            </button>
        </div>
    </footer>

    <script>
        let currentSlides = {};
        let currentTab = 'all';
        let userLocation = { region: null, district: null };

        function switchTab(tab) {
            currentTab = tab;
            
            // Hide both tabs
            document.getElementById('allEventsTab').classList.add('hidden');
            document.getElementById('yourAreaTab').classList.add('hidden');

            // Remove active styling from both buttons
            document.getElementById('allTabBtn').classList.remove('border-indigo-700', 'text-indigo-700', 'bg-indigo-50/30');
            document.getElementById('allTabBtn').classList.add('border-transparent', 'text-gray-600');

            document.getElementById('areaTabBtn').classList.remove('border-indigo-700', 'text-indigo-700', 'bg-indigo-50/30');
            document.getElementById('areaTabBtn').classList.add('border-transparent', 'text-gray-600');

            // Show selected tab and style its button
            if (tab === 'all') {
                document.getElementById('allEventsTab').classList.remove('hidden');
                document.getElementById('allTabBtn').classList.add('border-indigo-700', 'text-indigo-700', 'bg-indigo-50/30');
                document.getElementById('allTabBtn').classList.remove('border-transparent', 'text-gray-600');
            } else if (tab === 'area') {
                document.getElementById('yourAreaTab').classList.remove('hidden');
                document.getElementById('areaTabBtn').classList.add('border-indigo-700', 'text-indigo-700', 'bg-indigo-50/30');
                document.getElementById('areaTabBtn').classList.remove('border-transparent', 'text-gray-600');
                
                // Load geolocation for area tab if not already loaded
                if (userLocation.region === null) {
                    getDeviceLocation();
                }
            }
        }

        function nextSlide(eventId) {
            const dataElement = document.getElementById(`slides-data-${eventId}`);
            if (!dataElement) return;

            const slides = JSON.parse(dataElement.textContent);
            if (!currentSlides[eventId]) currentSlides[eventId] = 0;

            currentSlides[eventId] = (currentSlides[eventId] + 1) % slides.length;
            const img = document.getElementById(`slide-${eventId}-0`);
            if (img) img.src = slides[currentSlides[eventId]];
        }

        function prevSlide(eventId) {
            const dataElement = document.getElementById(`slides-data-${eventId}`);
            if (!dataElement) return;

            const slides = JSON.parse(dataElement.textContent);
            if (!currentSlides[eventId]) currentSlides[eventId] = 0;

            currentSlides[eventId] = (currentSlides[eventId] - 1 + slides.length) % slides.length;
            const img = document.getElementById(`slide-${eventId}-0`);
            if (img) img.src = slides[currentSlides[eventId]];
        }

        function searchEvents() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const tabs = document.querySelectorAll('[id$="Tab"]');
            tabs.forEach(tab => {
                const cards = tab.querySelectorAll('.event-card');
                cards.forEach(card => {
                    const title = card.textContent.toLowerCase();
                    card.style.display = title.includes(query) ? 'block' : 'none';
                });
            });
        }

        function toggleLanguage() {
            alert("Language switching will be implemented soon!");
        }

        function bookEvent(eventId) {
            window.location.href = "login.php";
        }

        // ===== GEOLOCATION API FOR YOUR AREA TAB =====

        // Get user's device location
        function getDeviceLocation() {
            if (!navigator.geolocation) {
                console.warn('Geolocation not supported');
                showAreaEventsError('Geolocation not supported on this device.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    reverseGeocodeCoordinates(latitude, longitude);
                },
                (error) => {
                    console.warn('Geolocation error:', error.message);
                    showAreaEventsError('Please enable location access to see events in your area.');
                }
            );
        }

        // Reverse geocode coordinates to region and district using Nominatim (OpenStreetMap)
        function reverseGeocodeCoordinates(latitude, longitude) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // Extract region and district from address components
                    const address = data.address || {};
                    userLocation.district = address.city || address.town || address.village || 'Unknown';
                    userLocation.region = address.state || address.county || address.region || 'Unknown';
                    
                    // Fetch events for this region/district
                    fetchAreaEvents(userLocation.region, userLocation.district);
                })
                .catch(error => {
                    console.error('Reverse geocoding error:', error);
                    showAreaEventsError('Could not determine your location. Please try again.');
                });
        }

        // Fetch events matching user's region and district
        function fetchAreaEvents(region, district) {
            fetch(`home.php?action=getAreaEvents&region=${encodeURIComponent(region)}&district=${encodeURIComponent(district)}`)
                .then(response => response.json())
                .then(events => {
                    if (events.length === 0) {
                        showAreaEventsError(`No events found in ${userLocation.district}, ${userLocation.region}.`);
                    } else {
                        displayAreaEvents(events);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showAreaEventsError('Error loading events. Please try again.');
                });
        }

        const baseUrl = '<?= BASE_URL ?>';
        function normalizeAssetUrl(path, fallback = baseUrl + 'assets/images/default.png') {
            if (!path) return fallback;
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            return baseUrl + path.replace(/\\/g, '/').replace(/^\/+/, '');
        }

        // Display fetched area events dynamically
        function displayAreaEvents(events) {
            const container = document.getElementById('yourAreaTab');
            container.innerHTML = '';
            
            events.forEach(event => {
                const ticketsAvailable = event.event_tickets - event.event_tickets_sold;
                const eventCard = document.createElement('div');
                eventCard.className = 'event-card glass rounded-3xl overflow-hidden border border-white/30 p-6';
                eventCard.innerHTML = `
                    <div class="mb-6">
                        <div class="flex items-center gap-4 mb-4">
                            <img src="${escapeHtml(normalizeAssetUrl(event.user_profile_picture, baseUrl + 'assets/images/default.png'))}" 
                                 class="w-14 h-14 rounded-full object-cover border-2 border-indigo-400 flex-shrink-0" alt="Host">
                            <div>
                                <p class="font-semibold text-gray-800">${escapeHtml(event.user_full_name)}</p>
                                <p class="text-xs text-gray-500">Event Host</p>
                            </div>
                        </div>
                        <h3 class="font-bold text-2xl text-gray-800 mb-2">${escapeHtml(event.event_title)}</h3>
                        <p class="text-indigo-600 font-semibold text-sm mb-4">${escapeHtml(event.event_category)}</p>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                            <div class="bg-indigo-50 rounded-2xl p-4">
                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-calendar"></i> Date</p>
                                <p class="font-semibold text-sm">${new Date(event.event_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                            </div>
                            <div class="bg-indigo-50 rounded-2xl p-4">\n                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-clock"></i> Time</p>\n                                <p class="font-semibold text-sm">${new Date('1970-01-01 ' + event.event_time).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })}</p>\n                            </div>\n                            <div class="bg-indigo-50 rounded-2xl p-4">\n                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-map-marker"></i> Location</p>\n                                <p class="font-semibold text-sm">${escapeHtml((event.asset_district || 'N/A') + ', ' + (event.asset_region || 'N/A'))}</p>\n                            </div>\n                            <div class="bg-indigo-50 rounded-2xl p-4">\n                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-ticket"></i> Tickets</p>\n                                <p class="font-semibold text-sm text-indigo-700">${Math.max(0, ticketsAvailable)} Available</p>\n                            </div>\n                            <div class="bg-indigo-50 rounded-2xl p-4">\n                                <p class="text-xs text-gray-600 mb-1"><i class="fa fa-users"></i> Attendees</p>\n                                <p class="font-semibold text-sm">${event.event_tickets_sold} Booked</p>\n                            </div>\n                        </div>\n                    </div>\n                    <div class="mb-6 rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-600 h-64 flex items-center justify-center">\n                        <div class="text-center">\n                            <i class="fa fa-image text-white text-4xl mb-4"></i>\n                            <p class="text-white text-sm\">Media preview unavailable</p>\n                        </div>\n                    </div>\n                    <p class="text-gray-600 text-sm mb-6 line-clamp-3">${escapeHtml(event.event_description || 'No description provided')}</p>\n                    <a href=\"login.php\" class=\"w-full block bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-semibold text-center transition\">\n                        <i class=\"fa fa-ticket-alt\"></i> Book Now\n                    </a>\n                `;\n                container.appendChild(eventCard);\n            });\n        }

        // Show error message for area events
        function showAreaEventsError(message) {
            const container = document.getElementById('yourAreaTab');
            container.innerHTML = `\n                <div class="text-center py-16">\n                    <i class="fa fa-map-marker text-6xl text-gray-300 mb-4"></i>\n                    <p class="text-gray-500 text-lg">${escapeHtml(message)}</p>\n                    <button onclick="getDeviceLocation()" class="mt-4 px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-semibold transition">\n                        <i class="fa fa-location-arrow"></i> Try Again\n                    </button>\n                </div>\n            `;\n        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>