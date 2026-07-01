<?php
/**
 * Homepage Translations
 * This file contains translations for the homepage (home.php) in English, Swahili, and Sukuma.
 * The language is determined by $_SESSION['homepage_language'] (default: 'en').
 * 
 * NOTE: This is isolated to home.php only. Register and Login pages ignore this session variable.
 * 
 * @author Eventukio
 * @version 1.0
 */

// Set default language if not set
if (!isset($_SESSION['homepage_language'])) {
    $_SESSION['homepage_language'] = 'en';
}

$lang = $_SESSION['homepage_language'];

// Translation array
$translations = [
    'en' => [
        // Header
        'search_placeholder' => 'Search events...',
        'search_button' => 'Search',
        'change_language' => 'Badili Lugha',
        'login' => 'Login',
        'register' => 'Register',
        
        // Main content
        'upcoming_events' => 'Upcoming Events',
        'all_events' => 'All Events',
        'your_area' => 'Your Area',
        
        // Event card
        'event_host' => 'Event Host',
        'date' => 'Date',
        'time' => 'Time',
        'location' => 'Location',
        'tickets' => 'Tickets',
        'attendees' => 'Attendees',
        'available' => 'Available',
        'booked' => 'Booked',
        'book_now' => 'Book Now',
        'no_media' => 'No media available',
        'no_description' => 'No description provided',
        
        // Empty states
        'no_events' => 'No announced events at the moment.',
        'check_back_soon' => 'Check back soon for upcoming events!',
        'no_area_events' => 'No events in your area at the moment.',
        'enable_location' => 'Enable location access to see events near you.',
        
        // Location errors
        'geolocation_not_supported' => 'Geolocation not supported on this device.',
        'enable_location_access' => 'Please enable location access to see events in your area.',
        'could_not_determine_location' => 'Could not determine your location. Please try again.',
        'no_events_found_location' => 'No events found in your area.',
        'error_loading_events' => 'Error loading events. Please try again.',
        'try_again' => 'Try Again',
        'media_preview_unavailable' => 'Media preview unavailable',
    ],
    
    'sw' => [
        // Header - Swahili translations (TO BE PROVIDED BY USER)
        'search_placeholder' => 'Tafuta matukio...',
        'search_button' => 'Tafuta',
        'change_language' => 'Badili Lugha',
        'login' => 'Ingia',
        'register' => 'Jiunge',
        
        // Main content
        'upcoming_events' => 'Matukio Yajayo',
        'all_events' => 'Matukio Yote',
        'your_area' => 'Eneo Lako',
        
        // Event card
        'event_host' => 'Mwenye Tukio',
        'date' => 'Tarehe',
        'time' => 'Saa',
        'location' => 'Eneo',
        'tickets' => 'Tiketi',
        'attendees' => 'Washiriki',
        'available' => 'Inapatikana',
        'booked' => 'Imehifadhiwa',
        'book_now' => 'Weka Nafasi Sasa',
        'no_media' => 'Hakuna midia inayopatikana',
        'no_description' => 'Hakuna maelezo yamepatikana',
        
        // Empty states
        'no_events' => 'Hakuna matukio yametangazwa kwa sasa.',
        'check_back_soon' => 'Rudi baadaye kwa matukio yajayo!',
        'no_area_events' => 'Hakuna matukio katika eneo lako kwa sasa.',
        'enable_location' => 'Washa ruhusa ya eneo kuona matukio karibu na wewe.',
        
        // Location errors
        'geolocation_not_supported' => 'Ufuatiliaji wa eneo haujaungwa mkono kwenye kifaa hiki.',
        'enable_location_access' => 'Tafadhali washa ruhusa ya eneo kuona matukio katika eneo lako.',
        'could_not_determine_location' => 'Haikuweza kuamua eneo lako. Tafadhali jaribu tena.',
        'no_events_found_location' => 'Hakuna matukio yamepatikana katika eneo lako.',
        'error_loading_events' => 'Kosa la kupakia matukio. Tafadhali jaribu tena.',
        'try_again' => 'Jaribu Tena',
        'media_preview_unavailable' => 'Kihakiki cha midia hakipatikani',
    ],
    
    'suk' => [
        // Header - Sukuma translations (TO BE PROVIDED LATER)
        'search_placeholder' => 'Tolaga shetwa...', 
        'search_button' => 'Tolaga', 
        'change_language' => 'Gwishinja Lugha',
        'login' => "Kwingela", 
        'register' => 'Kwiunga', 
        
        // Main content
        'upcoming_events' => 'Shetwa ehiza', 
        'all_events' => 'Shetwa pya', 
        'your_area' => 'Ieneo Lyako', 
        
        // Event card
        'event_host' => "N'gwenekele mhayo", 
        'date' => 'Ikanza', 
        'time' => 'Ikanza', 
        'location' => 'Ipande', 
        'tickets' => 'Tiketi', 
        'attendees' => 'Abaaleho', 
        'available' => 'Ghaleho', 
        'booked' => 'Zimekwa', 
        'book_now' => 'Gweka sasa', 
        'no_media' => 'Nduhu media', 
        'no_description' => 'Ndoho maelezo', 
        
        // Empty states
        'no_events' => 'Ndoho shetwa', 
        'check_back_soon' => 'Ukoshoka',
        'no_area_events' => 'Nduhu shetwa ja ipande', 
        'enable_location' => 'Ghwezesha ipande',
        
        // Location errors
        'geolocation_not_supported' => 'Ghutambuzi ja hali haufi', 
        'enable_location_access' => 'Tafadhali washa ruhusa ya eneo kuona matukio katika eneo lako.', 
        'could_not_determine_location' => 'Ndolo dhimaga ipande.', 
        'no_events_found_location' => 'Ndolo shetwa ja ipande lyako.', 
        'error_loading_events' => 'Ndolo shetwa. Ghhemaga hange.',
        'try_again' => 'Ghemaga hange', 
        'media_preview_unavailable' => 'Ghikihakiki ja midia hakipatikani', 
    ],
];

// Helper function to get homepage translation
function ht($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}
