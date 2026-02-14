<?php
// Administrační rozhraní pluginu Fotogalerie
session_start();
if (!isset($_SESSION['user_id'])) { die('Přístup odepřen.'); }
require_once '../../config/config.php';
require_once '../../functions/database.php';

$db = db_connect();
$action = $_GET['action'] ?? 'list';
$gallery_id = $_GET['id'] ?? null;
$message = '';

// Helper funkce pro převod hodnot jako '64M' na bajty
if (!function_exists('ini_get_bytes')) {
    function ini_get_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}

// Zpracování formulářů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    if ($post_action === 'create_gallery') {
        $stmt = $db->prepare("INSERT INTO galleries (name) VALUES (?)");
        $stmt->execute([$_POST['gallery_name']]);
        header('Location: admin.php?action=edit&id=' . $db->lastInsertId());
        exit;
    }
    if ($post_action === 'save_gallery') {
        $stmt = $db->prepare("DELETE FROM gallery_images WHERE gallery_id = ?");
        $stmt->execute([$gallery_id]);
        
        if (isset($_POST['images'])) {
            $stmt_insert = $db->prepare("INSERT INTO gallery_images (gallery_id, image_thumb_url, image_full_url, caption, image_order) VALUES (?, ?, ?, ?, ?)");
            
            $order_counter = 0;
            foreach ($_POST['images'] as $image) {
                if (isset($image['thumb'], $image['full'], $image['caption'])) {
                    $stmt_insert->execute([$gallery_id, $image['thumb'], $image['full'], $image['caption'], $order_counter]);
                    $order_counter++;
                }
            }
        }
        $message = "Galerie byla uložena.";
    }
    if ($post_action === 'delete_gallery') {
        $stmt = $db->prepare("DELETE FROM galleries WHERE id = ?");
        $stmt->execute([$gallery_id]);
        $gallery_dir = __DIR__ . '/../../uploads/gallery_' . $gallery_id;
        if (is_dir($gallery_dir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gallery_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $fileinfo) { ($fileinfo->isDir() ? 'rmdir' : 'unlink')($fileinfo->getRealPath()); }
            rmdir($gallery_dir);
        }
        header('Location: admin.php?status=deleted');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Správa galerií</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
        h1, h2 { margin-top: 0; }
        a { color: #007bff; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        .button { display: inline-block; padding: 8px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .button.green { background: #28a745; }
        .button.red { background: #dc3545; }
        input[type="text"], input[type="file"] { width: 100%; padding: 8px; box-sizing: border-box; }
        .gallery-images { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; background: #e9ecef; padding: 10px; border-radius: 4px; min-height: 150px; }
        .gallery-image { position: relative; background: white; padding-bottom: 5px; }
        .gallery-image img { width: 100%; height: 120px; object-fit: cover; display: block; }
        .gallery-image .remove-btn { position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; }
        .gallery-image input[type="text"] { margin-top: 5px; padding: 4px; font-size: 12px; border: 1px solid #ccc; width: calc(100% - 10px); margin-left: 5px; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000; display: none; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 400px; text-align: center; }
        .modal-content p { margin-top: 0; margin-bottom: 20px; font-size: 1.1em; }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body>

<?php if ($action === 'list'): ?>
    <h1>Správa galerií</h1>
    <?php if(isset($_GET['status']) && $_GET['status'] === 'deleted') echo "<p style='color:green;'>Galerie byla smazána.</p>"; ?>
    <form action="admin.php" method="post" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="create_gallery">
        <input type="text" name="gallery_name" placeholder="Název nové galerie" required>
        <button type="submit" class="button green" style="margin-top: 10px;">Vytvořit novou galerii</button>
    </form>
    <table>
        <thead><tr><th>Název galerie</th><th>Shortcode</th><th>Akce</th></tr></thead>
        <tbody>
            <?php
            $galleries = $db->query("SELECT * FROM galleries ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($galleries as $gallery): ?>
            <tr>
                <td><?= htmlspecialchars($gallery['name']) ?></td>
                <td><code>[gallery id="<?= $gallery['id'] ?>"]</code></td>
                <td>
                    <a href="admin.php?action=edit&id=<?= $gallery['id'] ?>">Upravit</a> |
                    <form action="admin.php?action=edit&id=<?= $gallery['id'] ?>" method="post" style="display:inline;" onsubmit="return confirm('Opravdu smazat tuto galerii a všechny její obrázky?');">
                        <input type="hidden" name="action" value="delete_gallery">
                        <button type="submit" style="background:none; border:none; color:#dc3545; cursor:pointer; padding:0;">Smazat</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'edit' && $gallery_id):
    $gallery = $db->prepare("SELECT * FROM galleries WHERE id = ?");
    $gallery->execute([$gallery_id]);
    $gallery_data = $gallery->fetch(PDO::FETCH_ASSOC);
    $images_stmt = $db->prepare("SELECT * FROM gallery_images WHERE gallery_id = ? ORDER BY image_order ASC");
    $images_stmt->execute([$gallery_id]);
    $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Získání limitů pro nahrávání ze serveru
    $upload_max_filesize = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');
    $effective_limit_bytes = min(ini_get_bytes($upload_max_filesize), ini_get_bytes($post_max_size));
    $effective_limit_mb = round($effective_limit_bytes / 1024 / 1024);
?>
    <a href="admin.php">&larr; Zpět na seznam galerií</a>
    <h1>Úprava galerie: <?= htmlspecialchars($gallery_data['name']) ?></h1>
    <?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>

    <div style="background: white; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
        <h3>Nahrát nové obrázky</h3>
        <p><small>Maximální velikost souboru je omezena serverem na: <strong><?= $effective_limit_mb ?> MB</strong>.</small></p>
        <form id="upload-form" enctype="multipart/form-data">
            <input type="file" name="gallery_images[]" id="gallery-upload-input" multiple accept="image/*">
            <input type="hidden" name="gallery_id" value="<?= $gallery_id ?>">
            <button type="submit" class="button" style="margin-top: 10px;">Nahrát</button>
        </form>
        <div id="upload-status" style="margin-top: 10px;"></div>
    </div>
    
    <form action="admin.php?action=edit&id=<?= $gallery_id ?>" method="post">
        <input type="hidden" name="action" value="save_gallery">
        <p>Seřaďte obrázky přetažením a doplňte popisky.</p>
        <div class="gallery-images" id="gallery-images-container">
            <?php foreach ($images as $key => $image): ?>
            <div class="gallery-image" data-full-url="<?= htmlspecialchars($image['image_full_url']) ?>">
                <img src="<?= htmlspecialchars($image['image_thumb_url']) ?>">
                <input type="hidden" name="images[<?= $key ?>][thumb]" value="<?= htmlspecialchars($image['image_thumb_url']) ?>">
                <input type="hidden" name="images[<?= $key ?>][full]" value="<?= htmlspecialchars($image['image_full_url']) ?>">
                <input type="text" name="images[<?= $key ?>][caption]" value="<?= htmlspecialchars($image['caption']) ?>" placeholder="Popisek...">
                <button type="button" class="remove-btn">&times;</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="button green" style="margin-top: 20px;">Uložit galerii</button>
    </form>

    <!-- Modální okno pro potvrzení smazání -->
    <div class="modal-overlay" id="confirm-delete-modal">
        <div class="modal-content">
            <p>Opravdu si přejete smazat tento obrázek? Akce je nevratná.</p>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button type="button" id="confirm-delete-cancel" class="button">Zrušit</button>
                <button type="button" id="confirm-delete-btn" class="button red">Smazat</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script>
        const container = document.getElementById('gallery-images-container');
        Sortable.create(container, { animation: 150 });

        const confirmModal = document.getElementById('confirm-delete-modal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        const cancelBtn = document.getElementById('confirm-delete-cancel');
        let imageToDelete = null;

        container.addEventListener('click', e => {
            if (e.target.classList.contains('remove-btn')) {
                imageToDelete = e.target.parentElement;
                confirmModal.classList.add('active');
            }
        });

        cancelBtn.addEventListener('click', () => {
            confirmModal.classList.remove('active');
            imageToDelete = null;
        });

        confirmBtn.addEventListener('click', () => {
            if (imageToDelete) {
                const fullUrl = imageToDelete.dataset.fullUrl;
                const filename = fullUrl.split('/').pop();
                
                fetch('delete_image.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename: filename, gallery_id: <?= $gallery_id ?> })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        imageToDelete.remove();
                    } else {
                        alert('Chyba při mazání: ' + (data.error || 'Neznámá chyba'));
                    }
                })
                .finally(() => {
                    confirmModal.classList.remove('active');
                    imageToDelete = null;
                });
            }
        });

        const uploadForm = document.getElementById('upload-form');
        const uploadStatus = document.getElementById('upload-status');
        
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (this.querySelector('input[type="file"]').files.length === 0) {
                uploadStatus.innerHTML = '<span style="color:red;">Nevybrali jste žádné soubory.</span>';
                return;
            }

            const formData = new FormData(this);
            uploadStatus.textContent = 'Nahrávám...';

            fetch('upload.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadStatus.textContent = 'Nahrávání dokončeno.';
                        data.uploaded.forEach(image => {
                            const div = document.createElement('div');
                            div.className = 'gallery-image';
                            const newId = 'new_' + Date.now() + Math.random();
                            div.dataset.fullUrl = image.full;
                            div.innerHTML = `
                                <img src="${image.thumb}">
                                <input type="hidden" name="images[${newId}][thumb]" value="${image.thumb}">
                                <input type="hidden" name="images[${newId}][full]" value="${image.full}">
                                <input type="text" name="images[${newId}][caption]" placeholder="Popisek...">
                                <button type="button" class="remove-btn">&times;</button>
                            `;
                            container.appendChild(div);
                        });
                    } else {
                        uploadStatus.innerHTML = `<span style="color:red;">Chyba: ${data.error}</span>`;
                    }
                })
                .catch(() => {
                    uploadStatus.innerHTML = '<span style="color:red;">Chyba serveru.</span>';
                });
        });
    </script>
<?php endif; ?>

</body>
</html>
