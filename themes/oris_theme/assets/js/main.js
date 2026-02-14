/**
 * ORIS CORE - Template JS Logic
 * Mobilní menu a Lightbox
 */

document.addEventListener('DOMContentLoaded', () => {
    // Mobilní navigace
    const navToggle = document.getElementById('nav-btn');
    const primaryNav = document.getElementById('primary-navigation');

    if (navToggle && primaryNav) {
        navToggle.addEventListener('click', () => {
            const isVisible = primaryNav.getAttribute('data-visible') === "true";

            if (!isVisible) {
                primaryNav.setAttribute('data-visible', "true");
                navToggle.setAttribute('aria-expanded', "true");
            } else {
                primaryNav.setAttribute('data-visible', "false");
                navToggle.setAttribute('aria-expanded', "false");
            }
        });
    }

    // Lightbox logika (převzato z vašeho vzoru)
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        const lightboxImage = lightbox.querySelector('.lightbox-image');
        const container = document.querySelector('.container');

        if (container) {
            container.addEventListener('click', e => {
                const link = e.target.closest('a.lightbox-link');
                if (link && link.querySelector('img')) {
                    e.preventDefault();
                    lightboxImage.src = link.href;
                    lightbox.classList.add('active');
                }
            });
        }

        lightbox.addEventListener('click', e => {
            if (e.target === lightbox || e.target.classList.contains('lightbox-close')) {
                lightbox.classList.remove('active');
            }
        });
    }
});