<?php
// Hlavní soubor pluginu Google Maps

// Registrace shortcodu při načtení pluginu
add_shortcode('mapa', 'render_map_shortcode_plugin');

/**
 * Zpracuje shortcode [mapa] a zobrazí Google Mapu.
 *
 * Příklad použití:
 * [mapa adresa="Nová ulice 123, Město"]
 * [mapa adresa="Eiffelova věž, Paříž"]
 *
 * @param array $atts Atributy shortcodu (očekává se 'adresa').
 * @return string HTML kód s vloženou mapou.
 */
function render_map_shortcode_plugin($atts) {
    // Zkontrolujeme, zda byl zadán atribut 'adresa'
    if (empty($atts['adresa'])) {
        return '<p><em>Chyba: V shortcodu pro mapu chybí atribut "adresa".</em></p>';
    }

    // Převedeme adresu na formát bezpečný pro URL
    $adresa_pro_url = urlencode($atts['adresa']);

    // Sestavíme HTML kód pro vložení mapy (iframe)
    // Použijeme třídu .map-wrapper, aby se na mapu aplikovaly responzivní styly
    $html = '
    <div class="map-wrapper">
        <iframe
            width="100%"
            height="100%"
            frameborder="0"
            style="border:0;"
            src="https://maps.google.com/maps?q=' . $adresa_pro_url . '&output=embed"
            allowfullscreen>
        </iframe>
    </div>';

    return $html;
}
