<?php
// KROK 1: Diagnostika - zapnutí zobrazení chyb
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// KROK 2: Načtení potřebné konfigurace
if (file_exists(__DIR__ . '/../../config/config.php')) {
    require_once __DIR__ . '/../../config/config.php';
}

header('Content-Type: application/json');

// KROK 3: Robustní definice cest
$upload_dir_path = dirname(__DIR__) . '/uploads/'; // Cesta k /plugins/uploads/
$base_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$upload_dir_url = $base_url . '/plugins/uploads/';

if (!is_dir($upload_dir_path)) {
    echo json_encode([]);
    exit;
}

$files = array_diff(scandir($upload_dir_path), ['.', '..']);
$images = [];

foreach ($files as $file) {
    if (strpos($file, '_thumb.') !== false) {
        $original_file = str_replace('_thumb.', '.', $file);
        if (in_array($original_file, $files)) {
            $images[] = [
                'thumb_url' => $upload_dir_url . $file,
                'full_url' => $upload_dir_url . $original_file,
                'filename' => $original_file
            ];
        }
    }
}

// Seřadíme od nejnovějších po nejstarší
usort($images, function($a, $b) use ($upload_dir_path) {
    $file_a_time = @filemtime($upload_dir_path . $a['filename']);
    $file_b_time = @filemtime($upload_dir_path . $b['filename']);
    if ($file_a_time === $file_b_time) return 0;
    return ($file_a_time < $file_b_time) ? 1 : -1;
});

echo json_encode($images);
