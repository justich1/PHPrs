console.log('Hlavní JavaScript soubor byl úspěšně načten.');

// Logika pro mobilní navigaci (hamburger menu)
const primaryNav = document.querySelector('.primary-navigation');
const navToggle = document.querySelector('.mobile-nav-toggle');

navToggle.addEventListener('click', () => {
    const isVisible = primaryNav.getAttribute('data-visible');

    if (isVisible === "false" || isVisible === null) {
        primaryNav.setAttribute('data-visible', true);
        navToggle.setAttribute('aria-expanded', true);
    } else {
        primaryNav.setAttribute('data-visible', false);
        navToggle.setAttribute('aria-expanded', false);
    }
});

// --- LOGIKA PRO LIGHTBOX ---
document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('lightbox');
    if (!lightbox) return;

    const lightboxImage = lightbox.querySelector('.lightbox-image');
    const contentContainer = document.querySelector('.container');

    contentContainer.addEventListener('click', event => {
        const link = event.target.closest('a.lightbox-link');
        if (link && link.querySelector('img')) {
            event.preventDefault();
            lightboxImage.src = link.href; // Načteme obrázek v plné velikosti z odkazu
            lightbox.classList.add('active');
        }
    });

    // Zavření lightboxu
    lightbox.addEventListener('click', event => {
        if (event.target === lightbox || event.target.classList.contains('lightbox-close')) {
            lightbox.classList.remove('active');
        }
    });
});