<?php
// delete_image.php - Bezpečné smazání obrázku a jeho miniatury

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $filename = $data['filename'] ?? '';
    $gallery_id = $data['gallery_id'] ?? 0;

    // Bezpečnostní kontroly
    if (empty($filename) || basename($filename) !== $filename || !$gallery_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Neplatná data.']);
        exit;
    }

    $gallery_dir_name = 'gallery_' . $gallery_id;
    $upload_dir_path = __DIR__ . '/../../uploads/' . $gallery_dir_name . '/';
    
    $original_path = $upload_dir_path . $filename;
    $thumb_path = $upload_dir_path . str_replace('.', '_thumb.', $filename);

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
