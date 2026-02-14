<?php
/**
 * Hlavní soubor pluginu pro Blog
 */
require_once __DIR__ . '/functions-blog.php';

function blog_plugin_activate() {
    blog_ensure_db_tables();
    // Cesta ke sdílené složce /plugins/uploads/
    $upload_dir = dirname(__DIR__) . '/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
}

function blog_plugin_uninstall() {
    blog_drop_db_tables();
}

register_activation_hook(__FILE__, 'blog_plugin_activate');
register_uninstall_hook(__FILE__, 'blog_plugin_uninstall');
add_shortcode('blog', 'blog_render_shortcode');
