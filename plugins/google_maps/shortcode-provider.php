<?php
// /plugins/Maps/shortcode-provider.php

/**
 * Poskytuje statickou šablonu pro vložení shortcodu mapy.
 * Tento soubor se nemusí připojovat k databázi.
 */

// Bezpečnostní pojistka
if (!defined('ABSPATH')) {
    // define('ABSPATH', dirname(__DIR__, 2));
}

// Jednoduše vrátíme pole s jedním prvkem
return [
    '[mapa adresa="Zadejte adresu"]' => 'Google Mapa (podle adresy)'
];