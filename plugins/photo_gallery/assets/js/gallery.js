// UPRAVENÁ VERZE - gallery.js

document.addEventListener('DOMContentLoaded', () => {
    // ZMĚNA: Cílíme na nové, unikátní ID
    const lightbox = document.getElementById('gallery-lightbox');
    if (!lightbox) return;

    // ZMĚNA: Používáme nové, unikátní třídy
    const lightboxImage = lightbox.querySelector('.gallery-lightbox-image');
    const lightboxPrev = lightbox.querySelector('.gallery-lightbox-prev');
    const lightboxNext = lightbox.querySelector('.gallery-lightbox-next');
    const contentContainer = document.querySelector('.container'); // Toto může zůstat, pokud je to hlavní kontejner stránky
    
    let galleryImages = [];
    let currentIndex = 0;

    function showImage(index) {
        if (index < 0 || index >= galleryImages.length) return;
        currentIndex = index;
        lightboxImage.src = galleryImages[currentIndex];
    }

    function showNextImage() {
        showImage((currentIndex + 1) % galleryImages.length);
    }

    function showPrevImage() {
        showImage((currentIndex - 1 + galleryImages.length) % galleryImages.length);
    }

    contentContainer.addEventListener('click', event => {
        // ZMĚNA: Hledáme odkazy s novou, unikátní třídou
        const link = event.target.closest('a.gallery-lightbox-link');
        if (link && link.querySelector('img')) {
            event.preventDefault();
            const gallery = link.closest('.plugin-gallery');
            if (!gallery) return;
            
            // ZMĚNA: Načteme všechny obrázky z dané galerie podle nové třídy
            galleryImages = Array.from(gallery.querySelectorAll('a.gallery-lightbox-link')).map(a => a.href);
            currentIndex = galleryImages.indexOf(link.href);
            
            lightboxImage.src = galleryImages[currentIndex];
            lightbox.classList.add('active');
        }
    });

    // Zavření lightboxu
    lightbox.addEventListener('click', event => {
        // ZMĚNA: Používáme novou třídu pro tlačítko zavření
        if (event.target === lightbox || event.target.classList.contains('gallery-lightbox-close')) {
            lightbox.classList.remove('active');
        }
    });

    // Navigace kliknutím na šipky
    lightboxPrev.addEventListener('click', showPrevImage);
    lightboxNext.addEventListener('click', showNextImage);

    // Navigace klávesnicí
    document.addEventListener('keydown', event => {
        if (lightbox.classList.contains('active')) {
            if (event.key === 'ArrowRight') showNextImage();
            if (event.key === 'ArrowLeft') showPrevImage();
            if (event.key === 'Escape') lightbox.classList.remove('active');
        }
    });

    // Navigace přejetím (swipe) na dotykových zařízeních
    let touchstartX = 0;
    let touchendX = 0;

    lightbox.addEventListener('touchstart', e => {
        touchstartX = e.changedTouches[0].screenX;
    }, { passive: true });

    lightbox.addEventListener('touchend', e => {
        touchendX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        if (touchendX < touchstartX - 50) { // Swipe doleva
            showNextImage();
        }
        if (touchendX > touchstartX + 50) { // Swipe doprava
            showPrevImage();
        }
    }
});
