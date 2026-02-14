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
    mkdir($upload_dir_path, 0755, true);
}

/**
 * Vytvoří zmenšenou verzi obrázku.
 */
function create_thumbnail($source, $destination, $max_width = 800) {
    $source_image_type = @exif_imagetype($source);
    if (!$source_image_type) return false;

    list($width, $height) = getimagesize($source);
    if ($width <= $max_width) {
        return copy($source, $destination);
    }

    $ratio = $height / $width;
    $new_width = $max_width;
    $new_height = $max_width * $ratio;

    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    switch ($source_image_type) {
        case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source);
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            break;
        case IMAGETYPE_GIF: $source_image = imagecreatefromgif($source); break;
        default: return false;
    }

    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    $success = false;
    switch ($source_image_type) {
        case IMAGETYPE_JPEG: $success = imagejpeg($new_image, $destination, 85); break;
        case IMAGETYPE_PNG: $success = imagepng($new_image, $destination, 9); break;
        case IMAGETYPE_GIF: $success = imagegif($new_image, $destination); break;
    }

    imagedestroy($source_image);
    imagedestroy($new_image);
    return $success;
}

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (in_array($file['type'], $allowed_types) && $file['size'] < 5000000) { // Limit 5MB
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $base_name = uniqid('img_') . '-' . preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo(basename($file['name']), PATHINFO_FILENAME));
        
        $original_filename = $base_name . '.' . $ext;
        $thumb_filename = $base_name . '_thumb.' . $ext;

        $original_path = $upload_dir_path . $original_filename;
        $thumb_path = $upload_dir_path . $thumb_filename;
        
        if (move_uploaded_file($file['tmp_name'], $original_path)) {
            if (create_thumbnail($original_path, $thumb_path)) {
                echo json_encode([
                    'location' => $upload_dir_url . $thumb_filename,
                    'thumb_url' => $upload_dir_url . $thumb_filename,
                    'full_size_url' => $upload_dir_url . $original_filename
                ]);
            } else {
                echo json_encode([
                    'location' => $upload_dir_url . $original_filename,
                    'thumb_url' => $upload_dir_url . $original_filename,
                    'full_size_url' => $upload_dir_url . $original_filename
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Chyba při přesouvání souboru.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Neplatný typ souboru nebo je soubor příliš velký.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Žádný soubor nebyl nahrán nebo nastala chyba. Kód chyby: ' . ($_FILES['image']['error'] ?? 'N/A')]);
}
