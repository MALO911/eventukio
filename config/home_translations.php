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
        // Header - Sukuma translations (TO BE PROVIDED BY MALONGO)
        'search_placeholder' => 'Tafuta matukio...', // Placeholder
        'search_button' => 'Tafuta', // Placeholder
        'change_language' => 'Bhadili Lugha',
        'login' => 'Ingila', // Placeholder
        'register' => 'Jilunge', // Placeholder
        
        // Main content
        'upcoming_events' => 'Matukio Yajayo', // Placeholder
        'all_events' => 'Matukio Yote', // Placeholder
        'your_area' => 'Eneo Lyako', // Placeholder
        
        // Event card
        'event_host' => 'Mwenye Tukio', // Placeholder
        'date' => 'Tarehe', // Placeholder
        'time' => 'Saa', // Placeholder
        'location' => 'Eneo', // Placeholder
        'tickets' => 'Tiketi', // Placeholder
        'attendees' => 'Washiriki', // Placeholder
        'available' => 'Inapatikana', // Placeholder
        'booked' => 'Imehifadhiwa', // Placeholder
        'book_now' => 'Weka Nafasi Sasa', // Placeholder
        'no_media' => 'Hakuna midia inayopatikana', // Placeholder
        'no_description' => 'Hakuna maelezo yamepatikana', // Placeholder
        
        // Empty states
        'no_events' => 'Hakuna matukio yametangazwa kwa sasa.', // Placeholder
        'check_back_soon' => 'Rudi baadaye kwa matukio yajayo!', // Placeholder
        'no_area_events' => 'Hakuna matukio katika eneo lako kwa sasa.', // Placeholder
        'enable_location' => 'Washa ruhusa ya eneo kuona matukio karibu na wewe.', // Placeholder
        
        // Location errors
        'geolocation_not_supported' => 'Ufuatiliaji wa eneo haujaungwa mkono kwenye kifaa hiki.', // Placeholder
        'enable_location_access' => 'Tafadhali washa ruhusa ya eneo kuona matukio katika eneo lako.', // Placeholder
        'could_not_determine_location' => 'Haikuweza kuamua eneo lako. Tafadhali jaribu tena.', // Placeholder
        'no_events_found_location' => 'Hakuna matukio yamepatikana katika eneo lako.', // Placeholder
        'error_loading_events' => 'Kosa la kupakia matukio. Tafadhali jaribu tena.', // Placeholder
        'try_again' => 'Jaribu Tena', // Placeholder
        'media_preview_unavailable' => 'Kihakiki cha midia hakipatikani', // Placeholder
    ],
];

// Helper function to get homepage translation
function ht($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}
