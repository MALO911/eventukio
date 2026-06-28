<?php
/**
 * EVENTUKIO - i18next Internationalization Setup
 * This file initializes i18next for client-side translations
 */

// Get current user language
$userLanguage = getUserLanguage();

// Valid language codes
$validLanguages = ['en', 'sw', 'suk', 'chag'];
if (!in_array($userLanguage, $validLanguages)) {
    $userLanguage = 'en';
}

// Load translation files
$translationsDir = __DIR__ . '/../translations/';
$translations = [];

foreach ($validLanguages as $lang) {
    $file = $translationsDir . $lang . '.json';
    if (file_exists($file)) {
        $translations[$lang] = json_decode(file_get_contents($file), true);
    } else {
        $translations[$lang] = [];
    }
}
?>

<!-- i18next Library -->
<script src="https://cdn.jsdelivr.net/npm/i18next@23.7.6/i18next.min.js"></script>

<script>
// Initialize i18next with loaded translations
const userLanguage = '<?= $userLanguage ?>';

const translations = {
    en: <?= json_encode($translations['en'] ?? []) ?>,
    sw: <?= json_encode($translations['sw'] ?? []) ?>,
    suk: <?= json_encode($translations['suk'] ?? []) ?>,
    chag: <?= json_encode($translations['chag'] ?? []) ?>
};

i18next.init({
    lng: userLanguage,
    fallbackLng: 'en',
    debug: false,
    resources: {
        en: { translation: translations.en },
        sw: { translation: translations.sw },
        suk: { translation: translations.suk },
        chag: { translation: translations.chag }
    }
}, function(err, t) {
    if (err) {
        console.error('i18next initialization error:', err);
    }
});

// Translation helper function
function t(key, options = {}) {
    return i18next.t(key, options);
}

// Change language function
function changeLanguage(lang) {
    // Send AJAX request to update language
    const formData = new FormData();
    formData.append('action', 'update_lang');
    formData.append('language', lang);

    fetch('account.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to apply new language
            location.reload();
        } else {
            console.error('Language change failed:', data.message);
        }
    })
    .catch(error => {
        console.error('Language change error:', error);
    });
}
</script>
