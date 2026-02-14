<?php
// Hlavní soubor pluginu Fotogalerie - UPRAVENÁ VERZE

// Registrace hooků
register_activation_hook(__FILE__, 'gallery_plugin_activate');
register_uninstall_hook(__FILE__, 'gallery_plugin_uninstall');
add_action('theme_head', 'gallery_plugin_enqueue_styles');
add_action('footer_end', 'gallery_plugin_render_lightbox_html');
add_action('theme_footer_js', 'gallery_plugin_enqueue_scripts');

// Registrace shortcodu
add_shortcode('gallery', 'gallery_plugin_render_shortcode');

/**
 * Vloží CSS styly pluginu do hlavičky stránky.
 */
function gallery_plugin_enqueue_styles() {
    $css_file = '/plugins/photo_gallery/assets/css/gallery.css';
    if (file_exists(__DIR__ . '/assets/css/gallery.css')) {
        echo '<link rel="stylesheet" href="' . $css_file . '?v=' . filemtime(__DIR__ . '/assets/css/gallery.css') . '">';
    }
}

/**
 * Vloží JavaScript pluginu do patičky stránky.
 */
function gallery_plugin_enqueue_scripts() {
    $js_file = '/plugins/photo_gallery/assets/js/gallery.js';
    if (file_exists(__DIR__ . '/assets/js/gallery.js')) {
        echo '<script src="' . $js_file . '?v=' . filemtime(__DIR__ . '/assets/js/gallery.js') . '"></script>';
    }
}

/**
 * Vloží HTML strukturu pro lightbox do patičky.
 * ZMĚNA: Přejmenováno ID a třídy, aby se předešlo konfliktům.
 */
function gallery_plugin_render_lightbox_html() {
    echo '
    <div class="gallery-lightbox" id="gallery-lightbox">
        <div class="gallery-lightbox-content">
            <span class="gallery-lightbox-close">&times;</span>
            <span class="gallery-lightbox-prev">&#10094;</span>
            <span class="gallery-lightbox-next">&#10095;</span>
            <img class="gallery-lightbox-image" src="">
        </div>
    </div>';
}

/**
 * Funkce spuštěná při aktivaci pluginu.
 */
function gallery_plugin_activate() {
    // ... kód zůstává stejný ...
    $db = db_connect();
    $sql = "
        CREATE TABLE IF NOT EXISTS `galleries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `gallery_images` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `gallery_id` INT NOT NULL,
            `image_thumb_url` VARCHAR(255) NOT NULL,
            `image_full_url` VARCHAR(255) NOT NULL,
            `caption` VARCHAR(255) NOT NULL DEFAULT '',
            `image_order` INT NOT NULL DEFAULT 0,
            FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql);

    try {
        $db->query("SELECT caption FROM gallery_images LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("ALTER TABLE `gallery_images` ADD COLUMN `caption` VARCHAR(255) NOT NULL DEFAULT '' AFTER `image_full_url`;");
    }
}

/**
 * Funkce spuštěná při smazání pluginu.
 */
function gallery_plugin_uninstall() {
    // ... kód zůstává stejný ...
    $db = db_connect();
    try {
        $stmt = $db->query("SELECT id FROM galleries");
        $gallery_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $uploads_dir = __DIR__ . '/../../uploads/';
        foreach ($gallery_ids as $gallery_id) {
            $gallery_dir = $uploads_dir . 'gallery_' . $gallery_id;
            if (is_dir($gallery_dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($gallery_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($gallery_dir);
            }
        }
    } catch (PDOException $e) { /* Ignorovat chybu */ }
    $db->exec("DROP TABLE IF EXISTS `gallery_images`, `galleries`;");
}


/**
 * Funkce pro vykreslení shortcodu [gallery id="X"].
 * ZMĚNA: Odkazy nyní mají specifickou třídu 'gallery-lightbox-link'.
 */
function gallery_plugin_render_shortcode($atts) {
    $gallery_id = $atts['id'] ?? 0;
    if (!$gallery_id) return '<p><em>Chyba: Nebylo zadáno ID galerie.</em></p>';

    $db = db_connect();
    $stmt = $db->prepare("SELECT image_thumb_url, image_full_url, caption FROM gallery_images WHERE gallery_id = ? ORDER BY image_order ASC");
    $stmt->execute([$gallery_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($images)) return '';

    ob_start();
    ?>
    <div class="plugin-gallery">
        <?php foreach ($images as $image): ?>
            <figure>
                <a href="<?= htmlspecialchars($image['image_full_url']) ?>" class="gallery-lightbox-link" title="<?= htmlspecialchars($image['caption']) ?>">
                    <img src="<?= htmlspecialchars($image['image_thumb_url']) ?>" alt="<?= htmlspecialchars($image['caption']) ?>">
                </a>
                <?php if (!empty($image['caption'])): ?>
                    <figcaption><?= htmlspecialchars($image['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
