<?php
// plugin.php - Hlavní soubor pro "Example Plugin"

/**
 * Funkce, kterou chceme spustit v patičce webu.
 */
function example_plugin_add_text_to_footer() {
    echo '<p>Powered by <a href="https://pc-pohotovost.eu" target="_blank">PC-pohotovost</a></p>';
}

/**
 * "Zavěsíme" naši funkci na hák 'footer_end'.
 * Když šablona zavolá do_action('footer_end'), spustí se i naše funkce.
 */
add_action('footer_end', 'example_plugin_add_text_to_footer');
