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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $filename = $data['filename'] ?? '';

    if (empty($filename) || basename($filename) !== $filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Neplatný název souboru.']);
        exit;
    }

    // KROK 2: Správné cesty ke sdílené složce
    $upload_dir_path = dirname(__DIR__) . '/uploads/'; // Cesta k /plugins/uploads/
    $original_path = $upload_dir_path . $filename;
    
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base_name = pathinfo($filename, PATHINFO_FILENAME);
    $thumb_path = $upload_dir_path . $base_name . '_thumb.' . $ext;

    $success = true;
    $error_message = '';

    if (file_exists($original_path)) {
        if (!unlink($original_path)) {
            $success = false;
            $error_message .= 'Nepodařilo se smazat originální obrázek. ';
        }
    }

    if (file_exists($thumb_path)) {
        if (!unlink($thumb_path)) {
            $success = false;
            $error_message .= 'Nepodařilo se smazat miniaturu.';
        }
    }

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $error_message]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
