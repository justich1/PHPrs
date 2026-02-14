<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$upload_dir_path = __DIR__ . '/../uploads/';
$upload_dir_url = '/uploads/';

$files = array_diff(scandir($upload_dir_path), ['.', '..']);
$images = [];

foreach ($files as $file) {
    // Zobrazujeme jen miniatury, abychom poznali, které obrázky patří k sobě
    if (strpos($file, '_thumb.') !== false) {
        $original_file = str_replace('_thumb.', '.', $file);
        if (in_array($original_file, $files)) {
            $images[] = [
                'thumb_url' => $upload_dir_url . $file,
                'full_url' => $upload_dir_url . $original_file,
                'filename' => $original_file // Pro mazání potřebujeme název originálu
            ];
        }
    }
}

// Seřadíme od nejnovějších po nejstarší
rsort($images);

echo json_encode($images);
