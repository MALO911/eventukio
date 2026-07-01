<?php
/**
 * EVENTUKIO - GLOBAL TRANSLATIONS
 * System-wide translations for all pages
 */

// Initialize user language from session or default to English
$lang = $_SESSION['user_language'] ?? 'en';

// Translation arrays
$translations = [
    'en' => [
        // Common
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'back' => 'Back',
        'next' => 'Next',
        'previous' => 'Previous',
        'search' => 'Search',
        'filter' => 'Filter',
        'sort' => 'Sort',
        'change_language' => 'Change Language',
        'loading' => 'Loading...',
        'error' => 'Error',
        'success' => 'Success',
        
        // Login page
        'sign_in_to_continue' => 'Sign in to continue',
        'email_or_phone' => 'Email or Phone Number',
        'email_or_phone_placeholder' => 'example@email.com or 255712345678',
        'enter_password' => 'Enter your password',
        'forgot_password' => 'Forgot Password?',
        'continue_with_google' => 'Continue with Google',
        'dont_have_account' => "Don't have an account?",
        'create_account' => 'Create an account',
        
        // Register page
        'create_account_title' => 'Create Account',
        'first_name' => 'First Name',
        'surname' => 'Surname',
        'national_id' => 'National ID (NIDA)',
        'birth_date' => 'Birth Date',
        'account_type' => 'Account Type',
        'personal' => 'Personal',
        'business' => 'Business',
        'business_name' => 'Business Name',
        'already_have_account' => 'Already have an account?',
        'sign_in' => 'Sign in',
        
        // Events page
        'events' => 'Events',
        'my_events' => 'My Events',
        'create_event' => 'Create Event',
        'manage_events' => 'Manage Events',
        'attend_events' => 'Attend Events',
        'event_title' => 'Event Title',
        'event_category' => 'Event Category',
        'event_date' => 'Event Date',
        'event_time' => 'Event Time',
        'event_location' => 'Event Location',
        'event_description' => 'Event Description',
        'event_tickets' => 'Event Tickets',
        'ticket_price' => 'Ticket Price',
        'free' => 'Free',
        
        // Profile page
        'profile' => 'Profile',
        'my_profile' => 'My Profile',
        'edit_profile' => 'Edit Profile',
        'update_profile' => 'Update Profile',
        'change_password' => 'Change Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        
        // Notifications
        'notifications' => 'Notifications',
        'mark_all_read' => 'Mark all as read',
        'no_notifications' => 'No notifications',
        
        // Messages
        'required_field' => 'This field is required',
        'invalid_email' => 'Invalid email address',
        'password_mismatch' => 'Passwords do not match',
        'login_success' => 'Login successful',
        'login_failed' => 'Login failed',
        'register_success' => 'Registration successful',
        'register_failed' => 'Registration failed',
        'update_success' => 'Update successful',
        'update_failed' => 'Update failed',
    ],
    
    'sw' => [
        // Common
        'login' => 'Ingia',
        'register' => 'Jiunge',
        'logout' => 'Ondoka',
        'email' => 'Barua pepe',
        'phone' => 'Namba ya simu',
        'password' => 'Nenosiri',
        'confirm_password' => 'Thibitisha nenosiri',
        'submit' => 'Wasilisha',
        'cancel' => 'Ghairi',
        'save' => 'Hifadhi',
        'delete' => 'Futa',
        'edit' => 'Hariri',
        'back' => 'Rudi',
        'next' => 'Ifuatayo',
        'previous' => 'Iliyopita',
        'search' => 'Tafuta',
        'filter' => 'Chuja',
        'sort' => 'Panga',
        'change_language' => 'Badili Lugha',
        'loading' => 'Inapakia...',
        'error' => 'Kosa',
        'success' => 'Mafanikio',
        
        // Login page
        'sign_in_to_continue' => 'Ingia kuendelea',
        'email_or_phone' => 'Barua pepe au Namba ya simu',
        'email_or_phone_placeholder' => 'mfano@email.com au 255712345678',
        'enter_password' => 'Weka nenosiri lako',
        'forgot_password' => 'Umesahau nenosiri?',
        'continue_with_google' => 'Endelea na Google',
        'dont_have_account' => 'Huna akaunti?',
        'create_account' => 'Tengeneza akaunti',
        
        // Register page
        'create_account_title' => 'Tengeneza Akaunti',
        'first_name' => 'Jina la kwanza',
        'surname' => 'Jina la ukoo',
        'national_id' => 'Kitambulisho cha kitaifa (NIDA)',
        'birth_date' => 'Tarehe ya kuzaliwa',
        'account_type' => 'Aina ya akaunti',
        'personal' => 'Binafsi',
        'business' => 'Biashara',
        'business_name' => 'Jina la biashara',
        'already_have_account' => 'Tayari una akaunti?',
        'sign_in' => 'Ingia',
        
        // Events page
        'events' => 'Matukio',
        'my_events' => 'Matukio Yangu',
        'create_event' => 'Tengeneza Tukio',
        'manage_events' => 'Dhibiti Matukio',
        'attend_events' => 'Shiriki Matukio',
        'event_title' => 'Kichwa cha tukio',
        'event_category' => 'Kundi la tukio',
        'event_date' => 'Tarehe ya tukio',
        'event_time' => 'Saa ya tukio',
        'event_location' => 'Eneo la tukio',
        'event_description' => 'Maelezo ya tukio',
        'event_tickets' => 'Tiketi za tukio',
        'ticket_price' => 'Bei ya tiketi',
        'free' => 'Bure',
        
        // Profile page
        'profile' => 'Wasifu',
        'my_profile' => 'Wasifu Wangu',
        'edit_profile' => 'Hariri wasifu',
        'update_profile' => 'Sasisha wasifu',
        'change_password' => 'Badilisha nenosiri',
        'current_password' => 'Nenosiri la sasa',
        'new_password' => 'Nenosiri jipya',
        
        // Notifications
        'notifications' => 'Taarifa',
        'mark_all_read' => 'Wote wamesomwa',
        'no_notifications' => 'Hakuna taarifa',
        
        // Messages
        'required_field' => 'Uga huu unahitajika',
        'invalid_email' => 'Barua pepe batili',
        'password_mismatch' => 'Nenosiri hazilingani',
        'login_success' => 'Kuingia kumefanikiwa',
        'login_failed' => 'Kuingia kumeshindikana',
        'register_success' => 'Usajili umefanikiwa',
        'register_failed' => 'Usajili umeshindikana',
        'update_success' => 'Kusasisha kumefanikiwa',
        'update_failed' => 'Kusasisha kumeshindikana',
    ],
    
    'suk' => [
        // Common
        'login' => "Ng'wingila",
        'register' => 'Gwisajili',
        'logout' => 'Ghitoka',
        'email' => 'Barua pepe',
        'phone' => 'Namba lya simu',
        'home' => 'Kaya',
        'password' => 'Nenosiri',
        'confirm_password' => 'Ghithibitisha nenosiri',
        'submit' => 'Ghiwasilisha',
        'cancel' => 'Ghairi',
        'save' => 'Ghihifadhi',
        'delete' => 'Ghitosa',
        'edit' => 'Hariri',
        'back' => 'Rudi',
        'next' => 'Ifuatayo',
        'previous' => 'Iliyopita',
        'search' => 'Lolaga',
        'filter' => 'Ghitenga',
        'sort' => 'Ghipanga',
        'change_language' => 'Gwishinja Lugha',
        'loading' => 'Inapakia...',
        'error' => 'Kosa',
        'success' => 'Ilendelea vizuri',
        
        // Login page
        'sign_in_to_continue' => "Ng'wingila kuendelea",
        'email_or_phone' => 'Barua pepe au Namba lya simu',
        'email_or_phone_placeholder' => 'mfano@email.com au 255712345678',
        'enter_password' => 'Weka nenosiri lako',
        'forgot_password' => 'Umesahau nenosiri?',
        'continue_with_google' => 'Ghitendelea na Google',
        'dont_have_account' => 'Nadina akaunti?',
        'create_account' => 'Ghitengeneza akaunti',
        
        // Register page
        'create_account_title' => 'Gwisajili Akaunti',
        'first_name' => 'Lina lya kwandya',
        'surname' => 'Lina lya ukoo',
        'national_id' => 'Chitambulisho ja kitaifa (NIDA)',
        'birth_date' => 'Lushiku lya kuzaliwa',
        'account_type' => 'Aina ja akaunti',
        'personal' => 'Binafsi',
        'business' => 'Biashara',
        'business_name' => 'Lina lya biashara',
        'already_have_account' => 'Tayari una akaunti?',
        'sign_in' => "Ng'wingila",
        
        // Events page
        'events' => 'Shilewa',
        'my_events' => 'Shilewa Zane',
        'create_event' => 'Ghitengeneza Shilewa',
        'manage_events' => 'Ghisimaia Shilewa',
        'attend_events' => 'Ghihudhuria Shilewa',
        'event_title' => 'Kichwa cha shilewa',
        'event_category' => 'Kundi lya shilewa',
        'event_date' => 'Lushiku lya shilewa',
        'event_time' => 'Makanza ja shilewa',
        'event_location' => 'Hali ja shilewa',
        'event_description' => 'Maelezo ja shilewa',
        'event_tickets' => 'Kadi za shilewa',
        'ticket_price' => 'Bei ja kadi',
        'free' => 'Bure',
        
        // Profile page
        'profile' => 'Wasifu',
        'my_profile' => 'Wasifu wane',
        'edit_profile' => 'Ghihariri wasifu',
        'update_profile' => 'Ghisasisha wasifu',
        'change_password' => 'Ghibadilisha nenosiri',
        'current_password' => 'Nenosiri ja sasa',
        'new_password' => 'Nenosiri lipya',
        
        // Notifications
        'notifications' => 'Notisi',
        'mark_all_read' => 'Jote wamesomwa',
        'no_notifications' => 'Nduhu notisi',
        
        // Messages
        'required_field' => 'Uga huu unahitajika',
        'invalid_email' => 'Barua pepe batili',
        'password_mismatch' => 'Nenosiri hazilingani',
        'login_success' => "Kung'wingila ilendelea ghawiza",
        'login_failed' => "Kung'wingila kumeshindikana",
        'register_success' => 'Ghusajili ilendelea ghawiza',
        'register_failed' => 'Ghusajili kumeshindikana',
        'update_success' => 'Ghusasisha ilendelea ghawiza',
        'update_failed' => 'Ghusasisha kumeshindikana',
    ],
];
