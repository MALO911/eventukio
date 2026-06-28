// assets/js/i18next-init.js
let currentLang = localStorage.getItem('language') || 'en';

async function initI18next() {
    await i18next
        .use(i18nextHttpBackend)
        .init({
            lng: currentLang,
            fallbackLng: 'en',
            backend: {
                loadPath: '../translations/{{lng}}.json'
            }
        });

    // Translate all elements with data-i18n
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        el.textContent = i18next.t(key, { defaultValue: el.textContent });
    });
}

// Change language function (called from modal after save)
function changeLanguage(lang) {
    localStorage.setItem('language', lang);
    currentLang = lang;
    initI18next();
}

// Initialize when page loads
window.addEventListener('load', initI18next);