<?php
// functions/config_manager.php - Funkce pro bezpečnou úpravu config.php

/**
 * Načte obsah souboru config.php.
 * @return string Obsah souboru.
 */
function get_config_content() {
    return file_get_contents(__DIR__ . '/../config/config.php');
}

/**
 * Zapíše nový obsah do souboru config.php.
 * @param string $content Nový obsah.
 * @return bool True v případě úspěchu.
 */
function write_config_content($content) {
    return file_put_contents(__DIR__ . '/../config/config.php', $content);
}

/**
 * Získá seznam dostupných šablon proskenováním adresáře /themes.
 * @return array Pole názvů šablon.
 */
function get_available_themes() {
    $themes_dir = __DIR__ . '/../themes';
    $items = array_diff(scandir($themes_dir), ['.', '..']);
    $themes = [];
    foreach ($items as $item) {
        if (is_dir($themes_dir . '/' . $item)) {
            $themes[] = $item;
        }
    }
    return $themes;
}

/**
 * Převede pole jazyků na PHP kód pro zápis do souboru.
 * @param array $languages Pole jazyků z formuláře.
 * @return string Řetězec reprezentující PHP pole.
 */
function format_languages_for_config($languages) {
    $pairs = [];
    foreach ($languages as $lang) {
        if (!empty($lang['code']) && !empty($lang['name'])) {
            $pairs[] = "'" . addslashes($lang['code']) . "' => '" . addslashes($lang['name']) . "'";
        }
    }
    return '[' . implode(', ', $pairs) . ']';
}
