<?php
// upload.php - Zpracování nahrávání obrázků pro plugin Galerie

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$gallery_id = $_POST['gallery_id'] ?? 0;
if (!$gallery_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Chybí ID galerie.']);
    exit;
}

$gallery_dir_name = 'gallery_' . $gallery_id;
$upload_dir_path = __DIR__ . '/../../uploads/' . $gallery_dir_name . '/';
$upload_dir_url = '/uploads/' . $gallery_dir_name . '/';

if (!is_dir($upload_dir_path)) {
    if (!mkdir($upload_dir_path, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Nepodařilo se vytvořit adresář pro nahrávání.']);
        exit;
    }
}

/**
 * Změní velikost obrázku na maximální šířku se zachováním poměru stran.
 * @param string $source Cesta k originálnímu obrázku.
 * @param string $destination Cesta pro uložení upraveného obrázku.
 * @param int $max_width Maximální šířka.
 * @return bool True v případě úspěchu.
 */
function resize_image($source, $destination, $max_width = 1920) {
    $source_image_type = exif_imagetype($source);
    if (!$source_image_type) return false;
    list($width, $height) = getimagesize($source);
    if ($width <= $max_width) { return copy($source, $destination); }
    $ratio = $height / $width;
    $new_width = $max_width;
    $new_height = $max_width * $ratio;
    $new_image = imagecreatetruecolor($new_width, $new_height);
    switch ($source_image_type) {
        case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG: $source_image = imagecreatefrompng($source); imagealphablending($new_image, false); imagesavealpha($new_image, true); break;
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
    imagedestroy($source_image); imagedestroy($new_image);
    return $success;
}

/**
 * Vytvoří čtvercovou miniaturu ořezem na střed.
 * @param string $source Cesta k originálnímu obrázku.
 * @param string $destination Cesta pro uložení miniatury.
 * @param int $thumb_size Velikost strany čtvercové miniatury.
 * @return bool True v případě úspěchu.
 */
function create_thumbnail($source, $destination, $thumb_size = 400) {
    $source_image_type = exif_imagetype($source);
    if (!$source_image_type) return false;
    list($width, $height) = getimagesize($source);
    $new_image = imagecreatetruecolor($thumb_size, $thumb_size);
    switch ($source_image_type) {
        case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG: $source_image = imagecreatefrompng($source); imagealphablending($new_image, false); imagesavealpha($new_image, true); break;
        case IMAGETYPE_GIF: $source_image = imagecreatefromgif($source); break;
        default: return false;
    }
    
    $original_aspect = $width / $height;
    $thumb_aspect = 1; // Čtverec

    if ($original_aspect >= $thumb_aspect) {
       // Širší než cíl
       $new_height = $thumb_size;
       $new_width = $width / ($height / $thumb_size);
       imagecopyresampled($new_image, $source_image, 0 - ($new_width - $thumb_size) / 2, 0, 0, 0, $new_width, $new_height, $width, $height);
    } else {
       // Vyšší než cíl
       $new_width = $thumb_size;
       $new_height = $height / ($width / $thumb_size);
       imagecopyresampled($new_image, $source_image, 0, 0 - ($new_height - $thumb_size) / 2, 0, 0, $new_width, $new_height, $width, $height);
    }

    $success = false;
    switch ($source_image_type) {
        case IMAGETYPE_JPEG: $success = imagejpeg($new_image, $destination, 85); break;
        case IMAGETYPE_PNG: $success = imagepng($new_image, $destination, 9); break;
        case IMAGETYPE_GIF: $success = imagegif($new_image, $destination); break;
    }
    imagedestroy($source_image); imagedestroy($new_image);
    return $success;
}


$uploaded_files = [];
$errors = [];

if (isset($_FILES['gallery_images'])) {
    $files = $_FILES['gallery_images'];
    $file_count = count($files['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file_tmp_name = $files['tmp_name'][$i];
            $file_name = $files['name'][$i];
            $file_size = $files['size'][$i];
            $file_type = $files['type'][$i];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($file_type, $allowed_types) && $file_size < 10000000) { // Limit 10MB
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $base_name = uniqid('img_') . '-' . preg_replace('/[^a-z0-9-]/', '-', strtolower(pathinfo(basename($file_name), PATHINFO_FILENAME)));
                $original_filename = $base_name . '.' . $ext;
                $thumb_filename = $base_name . '_thumb.' . $ext;
                $original_path = $upload_dir_path . $original_filename;
                $thumb_path = $upload_dir_path . $thumb_filename;

                if (move_uploaded_file($file_tmp_name, $original_path)) {
                    resize_image($original_path, $original_path, 1920);
                    if (create_thumbnail($original_path, $thumb_path, 400)) {
                        $uploaded_files[] = [
                            'thumb' => $upload_dir_url . $thumb_filename,
                            'full' => $upload_dir_url . $original_filename,
                            'filename' => $original_filename // Přidáno pro mazání
                        ];
                    } else { $errors[] = "Nepodařilo se vytvořit miniaturu pro soubor: " . htmlspecialchars($file_name); }
                } else { $errors[] = "Chyba při přesouvání souboru: " . htmlspecialchars($file_name); }
            } else { $errors[] = "Neplatný typ nebo velikost souboru: " . htmlspecialchars($file_name); }
        }
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode("\n", $errors), 'uploaded' => $uploaded_files]);
} else {
    echo json_encode(['success' => true, 'uploaded' => $uploaded_files]);
}
