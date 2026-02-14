<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/functions-blog.php';

$post_id = $_GET['id'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = [
        'id' => $post_id, 'author_id' => $_SESSION['user_id'], 'allow_comments' => isset($_POST['allow_comments']) ? 1 : 0,
        'status' => $_POST['status'], 'categories' => $_POST['categories'] ?? [], 'translations' => $_POST['translations']
    ];
    $saved_post_id = blog_save_post($post_data);
    header('Location: admin-blog.php?status=saved');
    exit;
}

$post_data = $post_id ? blog_get_post_details($post_id) : null;
$all_categories = blog_get_all_categories();
$post_categories = $post_id ? array_column(blog_get_post_categories($post_id), 'id') : [];
$page_title = $post_id ? "Úprava článku" : "Vytvoření nového článku";
?>
<style>
    .editor-container { display: flex; flex-wrap: wrap; gap: 20px; }
    .main-column { flex: 1; min-width: 65%; }
    .side-column { flex: 1; min-width: 300px; }
    .side-column .widget { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 20px; }
    .side-column h3 { margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px; }
    .category-list { max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: white; }
    .category-list label { display: block; margin-bottom: 5px; }
    .tab-nav { border-bottom: 1px solid #ccc; padding-left: 0; list-style: none; margin-bottom: 0; }
    .tab-nav li { display: inline-block; } .tab-nav a { display: block; padding: 10px 15px; text-decoration: none; border: 1px solid transparent; border-bottom: 0; }
    .tab-nav a.active { border-color: #ccc; background: #fff; border-bottom: 1px solid #fff; position: relative; top: 1px; }
    .tab-content { border: 1px solid #ccc; padding: 20px; border-top: 0; background: #fff; }
    .tab-pane { display: none; } .tab-pane.active { display: block; }
    .wysiwyg-toolbar { background: #f8f9fa; padding: 8px; border-bottom: 1px solid #dee2e6; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
    .wysiwyg-toolbar button, .wysiwyg-toolbar select, .wysiwyg-toolbar input[type="color"] { border: 1px solid transparent; background: none; padding: 5px 10px; cursor: pointer; border-radius: 3px; height: 32px; }
    .wysiwyg-toolbar button:hover, .wysiwyg-toolbar select:hover { background: #e9ecef; border-color: #ced4da; }
    .wysiwyg-toolbar input[type="color"] { padding: 2px; width: 40px; }
    .wysiwyg-editor { min-height: 400px; padding: 15px; border: 1px solid #ced4da; border-radius: 4px; outline: none; }
    .wysiwyg-editor:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    .wysiwyg-editor img, .wysiwyg-editor .video-wrapper, .wysiwyg-editor table { cursor: pointer; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
    .modal-content { background: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-content.modal-sm { max-width: 500px; }
    .modal-content h3 { margin-top: 0; }
    .modal-content label { display: block; margin-bottom: 5px; }
    .modal-content input, .modal-content select { width: 100%; padding: 8px; margin-bottom: 15px; box-sizing: border-box; }
    .modal-overlay.active { display: flex; }
    .media-library-body { flex-grow: 1; overflow-y: auto; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 10px 0; }
    .media-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .media-item { position: relative; border: 2px solid transparent; cursor: pointer; }
    .media-item img { width: 100%; height: 100px; object-fit: cover; display: block; }
    .media-item.selected { border-color: #007bff; }
    .media-item .delete-btn { position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-weight: bold; line-height: 24px; text-align: center; }
    .media-library-footer { padding-top: 15px; text-align: right; }
    .table-toolbar { display: none; background: #e9ecef; padding: 5px; border: 1px solid #ccc; margin-top: 5px; }
</style>

<h1><?php echo $page_title; ?></h1>

<form action="edit-post.php<?= $post_id ? '?id='.$post_id : '' ?>" method="post" id="post-editor-form">
    <div class="editor-container">
        <div class="main-column">
            <ul class="tab-nav">
                <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
                    <li><a href="#tab-<?= $code ?>" class="<?= $code == DEFAULT_LANG ? 'active' : '' ?>"><?= htmlspecialchars($name) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="tab-content">
                <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
                    <div id="tab-<?= $code ?>" class="tab-pane <?= $code == DEFAULT_LANG ? 'active' : '' ?>">
                        <?php $t = $post_data['translations'][$code] ?? null; ?>
                        <div style="margin-bottom: 1rem;">
                            <label for="title_<?= $code ?>">Název:</label>
                            <input type="text" id="title_<?= $code ?>" name="translations[<?= $code ?>][title]" value="<?= htmlspecialchars($t['title'] ?? '') ?>" style="width:100%;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label for="slug_<?= $code ?>">URL (slug):</label>
                            <input type="text" id="slug_<?= $code ?>" name="translations[<?= $code ?>][slug]" value="<?= htmlspecialchars($t['slug'] ?? '') ?>" style="width:100%;">
                        </div>
                        <div>
                            <label>Obsah:</label>
                            <div class="wysiwyg-toolbar">
                                <select class="format-block"><option value="p">Odstavec</option><option value="h2">Nadpis 2</option><option value="h3">Nadpis 3</option></select>
                                <button type="button" data-command="bold"><b>B</b></button>
                                <button type="button" data-command="italic"><i>I</i></button>
                                <button type="button" data-command="underline"><u>U</u></button>
                                <button type="button" data-command="insertUnorderedList">Seznam</button>
                                <button type="button" data-command="insertOrderedList">Číslovaný seznam</button>
                                <button type="button" data-command="createLink">Odkaz</button>
                                <button type="button" data-command="unlink">Odebrat odkaz</button>
                                <button type="button" data-command="insertImage">Obrázek</button>
                                <button type="button" data-command="insertYoutube">YouTube</button>
                                <button type="button" data-command="insertTable">Tabulka</button>
                                <button type="button" data-command="insertShort">Short code</button>
                                <label for="foreColor-<?= $code ?>" style="margin-left: 10px; font-size: 0.9em;">Text:</label>
                                <input type="color" id="foreColor-<?= $code ?>" data-command="foreColor" title="Barva textu">
                            </div>
                            <div class="wysiwyg-editor" id="content_editor_<?= $code ?>" contenteditable="true"><?= $t['content'] ?? '' ?></div>
                            <textarea name="translations[<?= $code ?>][content]" id="content_hidden_<?= $code ?>" style="display:none;"><?= htmlspecialchars($t['content'] ?? '') ?></textarea>
                            <div id="table-toolbar-<?= $code ?>" class="wysiwyg-toolbar table-toolbar">
                                <span>Nástroje tabulky:</span>
                                <button type="button" data-table-action="addRow">Přidat řádek</button>
                                <button type="button" data-table-action="addCol">Přidat sloupec</button>
                                <button type="button" data-table-action="removeRow">Odebrat řádek</button>
                                <button type="button" data-table-action="removeCol">Odebrat sloupec</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="side-column">
            <div class="widget">
                <h3>Publikovat</h3>
                <label for="status">Stav:</label>
                <select name="status" id="status" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <option value="published" <?= ($post_data['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Publikováno</option>
                    <option value="draft" <?= ($post_data['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Koncept</option>
                </select>
                <button type="submit" class="button" style="width: 100%;">Uložit článek</button>
            </div>
            <div class="widget">
                <h3>Kategorie</h3>
                <div class="category-list">
                    <?php if (empty($all_categories)): ?>
                        <p>Nejprve <a href="categories-blog.php">vytvořte kategorie</a>.</p>
                    <?php else: ?>
                        <?php foreach ($all_categories as $category): ?>
                            <label><input type="checkbox" name="categories[]" value="<?= $category['id'] ?>" <?= in_array($category['id'], $post_categories) ? 'checked' : '' ?>> <?= htmlspecialchars($category['name']) ?></label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="widget">
                <h3>Nastavení</h3>
                <label><input type="checkbox" name="allow_comments" value="1" <?= ($post_data['allow_comments'] ?? 1) ? 'checked' : '' ?>> Povolit komentáře</label>
            </div>
        </div>
    </div>
</form>

<!-- Modální okna -->
<div class="modal-overlay" id="media-library-modal"><div class="modal-content"><div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px;"><h3>Knihovna médií</h3><button type="button" id="upload-new-btn" style="background: #28a745; color: white;">Nahrát nový</button></div><div class="media-library-body"><div class="media-gallery" id="media-gallery"></div></div><div class="media-library-footer"><button type="button" id="media-cancel">Zrušit</button><button type="button" id="media-insert" style="background: #007bff; color: white;" disabled>Vložit obrázek</button></div></div></div>
<div class="modal-overlay" id="link-modal"><div class="modal-content modal-sm"><h3>Vložit / Upravit odkaz</h3><label for="link-url">URL Adresa:</label><input type="text" id="link-url" placeholder="https://example.com"><label><input type="checkbox" id="link-target" style="width: auto;"> Otevřít v nové kartě</label><div style="text-align: right; margin-top: 20px;"><button type="button" id="link-cancel">Zrušit</button><button type="button" id="link-save" style="background: #007bff; color: white;">Použít</button></div></div></div>
<div class="modal-overlay" id="image-style-modal"><div class="modal-content modal-sm"><h3>Upravit styl obrázku</h3><label for="image-size">Velikost zobrazení (%): <span id="image-size-value">100</span>%</label><input type="range" id="image-size" min="20" max="100" value="100" step="5"><label for="image-align">Zarovnání:</label><select id="image-align"><option value="align-none">Žádné</option><option value="align-left">Vlevo</option><option value="align-center">Na střed</option><option value="align-right">Vpravo</option></select><label for="image-style">Styl:</label><select id="image-style"><option value="style-none">Bez stylu</option><option value="style-shadow">Se stínem</option><option value="style-bordered">S rámečkem</option></select><label for="image-caption">Popisek:</label><input type="text" id="image-caption" placeholder="Volitelný popisek obrázku"><div style="text-align: right;"><button type="button" id="image-style-cancel">Zrušit</button><button type="button" id="image-style-save" style="background: #007bff; color: white;">Použít</button></div></div></div>
<div class="modal-overlay" id="youtube-modal"><div class="modal-content modal-sm"><h3>Vložit / Upravit YouTube video</h3><label for="youtube-url">Odkaz na YouTube video:</label><input type="text" id="youtube-url" placeholder="https://www.youtube.com/watch?v=..."><label for="youtube-width">Šířka (%): <span id="youtube-width-value">100</span>%</label><input type="range" id="youtube-width" min="30" max="100" value="100" step="5"><label for="youtube-align">Zarovnání:</label><select id="youtube-align"><option value="align-none">Žádné</option><option value="align-left">Vlevo</option><option value="align-center">Na střed</option><option value="align-right">Vpravo</option></select><div style="text-align: right; margin-top: 20px;"><button type="button" id="youtube-cancel">Zrušit</button><button type="button" id="youtube-save" style="background: #007bff; color: white;">Použít</button></div></div></div>
<div class="modal-overlay" id="table-modal"><div class="modal-content modal-sm"><h3>Vložit tabulku</h3><label for="table-rows">Počet řádků:</label><input type="number" id="table-rows" value="3" min="1"><label for="table-cols">Počet sloupců:</label><input type="number" id="table-cols" value="3" min="1"><div style="text-align: right;"><button type="button" id="table-cancel">Zrušit</button><button type="button" id="table-save" style="background:#007bff;color:white;">Vložit</button></div></div></div>
<input type="file" id="image-upload-input" accept="image/*" style="display: none;">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('post-editor-form');
    let activeEditor = null;
    let selection = null;
    let selectedImage = null;
    let selectedLink = null;
    let selectedVideo = null;
    let activeTable = null;
    let activeCell = null;

    const mediaModal = document.getElementById('media-library-modal');
    const gallery = document.getElementById('media-gallery');
    const insertBtn = document.getElementById('media-insert');
    let selectedMedia = null;

    function loadMediaLibrary() {
        gallery.innerHTML = '<p>Načítání obrázků...</p>';
        fetch('get_images.php')
            .then(response => response.json())
            .then(images => {
                gallery.innerHTML = '';
                images.forEach(img => {
                    const item = document.createElement('div');
                    item.className = 'media-item';
                    item.dataset.fullUrl = img.full_url;
                    item.dataset.thumbUrl = img.thumb_url;
                    item.innerHTML = `<img src="${img.thumb_url}" alt=""><button type="button" class="delete-btn" data-filename="${img.filename}">&times;</button>`;
                    gallery.appendChild(item);
                });
            });
    }

    gallery.addEventListener('click', event => {
        const item = event.target.closest('.media-item');
        const deleteBtn = event.target.closest('.delete-btn');
        if (deleteBtn) {
            event.stopPropagation();
            if (confirm('Opravdu si přejete smazat tento obrázek? Akce je nevratná.')) {
                const filename = deleteBtn.dataset.filename;
                fetch('delete_image.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ filename: filename }) })
                .then(response => response.json())
                .then(data => {
                    if (data.success) { deleteBtn.parentElement.remove(); } 
                    else { alert('Chyba při mazání: ' + (data.error || 'Neznámá chyba')); }
                });
            }
        } else if (item) {
            document.querySelectorAll('.media-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');
            selectedMedia = { thumb: item.dataset.thumbUrl, full: item.dataset.fullUrl };
            insertBtn.disabled = false;
        }
    });

    document.getElementById('upload-new-btn').addEventListener('click', () => { document.getElementById('image-upload-input').click(); });
    document.getElementById('media-insert').addEventListener('click', () => { if (selectedMedia) { activeEditor.focus(); const html = `<figure class="align-center style-none" style="width: 100%;"><a href="${selectedMedia.full}" class="lightbox-link"><img src="${selectedMedia.thumb}" style="width: 100%;"></a></figure><p></p>`; document.execCommand('insertHTML', false, html); mediaModal.classList.remove('active'); } });
    document.getElementById('media-cancel').addEventListener('click', () => mediaModal.classList.remove('active'));

    const linkModal = document.getElementById('link-modal');
    const linkUrlInput = document.getElementById('link-url');
    const linkTargetCheckbox = document.getElementById('link-target');
    function openLinkModal() {
        selection = window.getSelection().getRangeAt(0);
        selectedLink = document.getSelection().anchorNode.parentElement.closest('a');
        if (selectedLink) { linkUrlInput.value = selectedLink.getAttribute('href'); linkTargetCheckbox.checked = selectedLink.target === '_blank'; } 
        else { linkUrlInput.value = 'https://'; linkTargetCheckbox.checked = false; }
        linkModal.classList.add('active');
        linkUrlInput.focus();
    }
    document.getElementById('link-save').addEventListener('click', () => { if (selection) { window.getSelection().removeAllRanges(); window.getSelection().addRange(selection); if (selectedLink) { selectedLink.href = linkUrlInput.value; if (linkTargetCheckbox.checked) { selectedLink.target = '_blank'; } else { selectedLink.removeAttribute('target'); } } else { const newLink = `<a href="${linkUrlInput.value}" ${linkTargetCheckbox.checked ? 'target="_blank"' : ''}>${document.getSelection().toString()}</a>`; document.execCommand('insertHTML', false, newLink); } } linkModal.classList.remove('active'); selection = null; selectedLink = null; });
    document.getElementById('link-cancel').addEventListener('click', () => linkModal.classList.remove('active'));

    const imageModal = document.getElementById('image-style-modal');
    const imageSizeSlider = document.getElementById('image-size');
    const imageSizeValue = document.getElementById('image-size-value');
    imageSizeSlider.addEventListener('input', () => { imageSizeValue.textContent = imageSizeSlider.value; });
    document.getElementById('image-style-save').addEventListener('click', () => { if (!selectedImage) return; const figure = selectedImage.closest('figure'); if (!figure) return; const align = document.getElementById('image-align').value; const style = document.getElementById('image-style').value; figure.className = `${align} ${style}`.trim(); figure.style.width = imageSizeSlider.value + '%'; selectedImage.style.width = '100%'; let caption = figure.querySelector('figcaption'); const captionText = document.getElementById('image-caption').value; if (captionText) { if (!caption) { caption = document.createElement('figcaption'); figure.appendChild(caption); } caption.textContent = captionText; } else if (caption) { caption.remove(); } imageModal.classList.remove('active'); selectedImage = null; });
    document.getElementById('image-style-cancel').addEventListener('click', () => imageModal.classList.remove('active'));

    const youtubeModal = document.getElementById('youtube-modal');
    const youtubeWidthSlider = document.getElementById('youtube-width');
    const youtubeWidthValue = document.getElementById('youtube-width-value');
    youtubeWidthSlider.addEventListener('input', () => { youtubeWidthValue.textContent = youtubeWidthSlider.value; });
    document.getElementById('youtube-save').addEventListener('click', () => { const url = document.getElementById('youtube-url').value; const videoId = url.match(/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/); if (!videoId || !videoId[1]) { alert('Neplatná URL adresa YouTube videa.'); return; } const width = youtubeWidthSlider.value; const align = document.getElementById('youtube-align').value; if (selectedVideo) { selectedVideo.className = `video-wrapper ${align}`.trim(); selectedVideo.style.width = width + '%'; selectedVideo.querySelector('iframe').src = `https://www.youtube.com/embed/${videoId[1]}`; } else { const embedHtml = `<div class="video-wrapper ${align}" style="width: ${width}%;"><iframe src="https://www.youtube.com/embed/${videoId[1]}" frameborder="0" allowfullscreen></iframe></div><p></p>`; if (selection) { window.getSelection().removeAllRanges(); window.getSelection().addRange(selection); document.execCommand('insertHTML', false, embedHtml); } } youtubeModal.classList.remove('active'); selectedVideo = null; });
    document.getElementById('youtube-cancel').addEventListener('click', () => youtubeModal.classList.remove('active'));

    const tableModal = document.getElementById('table-modal');
    document.getElementById('table-save').addEventListener('click', () => { const rows = document.getElementById('table-rows').value; const cols = document.getElementById('table-cols').value; let tableHtml = '<div class="table-wrapper"><table style="border-collapse: collapse; border: 1px solid #ccc;"><tbody>'; for (let i = 0; i < rows; i++) { tableHtml += '<tr>'; for (let j = 0; j < cols; j++) { tableHtml += '<td style="border: 1px solid #ccc; padding: 8px;"><p><br></p></td>'; } tableHtml += '</tr>'; } tableHtml += '</tbody></table></div><p><br></p>'; activeEditor.focus(); document.execCommand('insertHTML', false, tableHtml); tableModal.classList.remove('active'); });
    document.getElementById('table-cancel').addEventListener('click', () => tableModal.classList.remove('active'));

    document.querySelectorAll('.wysiwyg-editor').forEach(editor => {
        const toolbar = editor.previousElementSibling;
        const tableToolbar = editor.nextElementSibling.nextElementSibling;
        toolbar.addEventListener('input', e => { if (e.target.type === 'color') document.execCommand(e.target.dataset.command, false, e.target.value); });
        toolbar.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            event.preventDefault();
            activeEditor = editor;
            selection = window.getSelection().getRangeAt(0);
            const command = button.dataset.command;
            if (command === 'createLink') { openLinkModal(); }
            else if (command === 'insertImage') { selectedMedia = null; insertBtn.disabled = true; loadMediaLibrary(); mediaModal.classList.add('active'); }
            else if (command === 'insertYoutube') { selectedVideo = null; document.getElementById('youtube-url').value = ''; youtubeWidthSlider.value = 100; youtubeWidthValue.textContent = 100; document.getElementById('youtube-align').value = 'align-none'; youtubeModal.classList.add('active'); }
            else if (command === 'insertTable') { tableModal.classList.add('active'); }
            else if (command === 'insertShort') { const html = `[]`; document.execCommand('insertHTML', false, html); }
            else { document.execCommand(command, false, null); }
        });
        toolbar.querySelector('.format-block').addEventListener('change', e => document.execCommand('formatBlock', false, e.target.value));
        tableToolbar.addEventListener('click', event => { const button = event.target.closest('button[data-table-action]'); if (!button || !activeTable) return; const action = button.dataset.tableAction; const tbody = activeTable.querySelector('tbody'); if (!tbody) return; if (action === 'addRow') { const firstRow = tbody.querySelector('tr'); if (!firstRow) return; const colCount = firstRow.cells.length; const newRow = tbody.insertRow(); for (let i = 0; i < colCount; i++) { const newCell = newRow.insertCell(); newCell.innerHTML = '<p><br></p>'; newCell.style.cssText = firstRow.cells[i].style.cssText; } } else if (action === 'addCol') { for (const row of tbody.rows) { const newCell = row.insertCell(); newCell.innerHTML = '<p><br></p>'; if (row.cells.length > 1) { newCell.style.cssText = row.cells[row.cells.length - 2].style.cssText; } } } else if (action === 'removeRow') { if (activeCell && activeTable.rows.length > 1) { activeCell.parentElement.remove(); } else { alert('Nelze smazat poslední řádek.'); } } else if (action === 'removeCol') { if (activeCell && activeCell.parentElement.cells.length > 1) { const colIndex = activeCell.cellIndex; for (const row of activeTable.rows) { row.deleteCell(colIndex); } } else { alert('Nelze smazat poslední sloupec.'); } } });
        tableToolbar.addEventListener('input', event => { const input = event.target; if (input.dataset.tableStyle) { activeTable.style[input.dataset.tableStyle] = input.value + (input.type === 'number' ? 'px' : ''); activeTable.querySelectorAll('td, th').forEach(cell => { cell.style[input.dataset.tableStyle] = input.value + (input.type === 'number' ? 'px' : ''); }); } else if (input.dataset.command) { document.execCommand(input.dataset.command, false, input.value); } });
        editor.addEventListener('click', event => {
            const target = event.target;
            activeCell = target.closest('td');
            const table = target.closest('table');
            if (table) { activeTable = table; tableToolbar.style.display = 'flex'; } 
            else { activeTable = null; tableToolbar.style.display = 'none'; }
            if (target.tagName === 'IMG') { selectedImage = target; const figure = selectedImage.closest('figure'); const currentWidth = figure.style.width ? parseInt(figure.style.width) : 100; imageSizeSlider.value = currentWidth; imageSizeValue.textContent = currentWidth; document.getElementById('image-align').value = figure.className.match(/align-\w+/) ? figure.className.match(/align-\w+/)[0] : 'align-none'; document.getElementById('image-style').value = figure.className.match(/style-\w+/) ? figure.className.match(/style-\w+/)[0] : 'style-none'; const caption = figure.querySelector('figcaption'); document.getElementById('image-caption').value = caption ? caption.textContent : ''; imageModal.classList.add('active'); }
            else if (target.closest('.video-wrapper')) { selectedVideo = target.closest('.video-wrapper'); const iframeSrc = selectedVideo.querySelector('iframe').src; const currentWidth = selectedVideo.style.width ? parseInt(selectedVideo.style.width) : 100; document.getElementById('youtube-url').value = iframeSrc.replace('embed/', 'watch?v='); youtubeWidthSlider.value = currentWidth; youtubeWidthValue.textContent = currentWidth; document.getElementById('youtube-align').value = selectedVideo.className.match(/align-\w+/) ? selectedVideo.className.match(/align-\w+/)[0] : 'align-none'; youtubeModal.classList.add('active'); }
        });
    });

    document.getElementById('image-upload-input').addEventListener('change', function() { if (this.files.length === 0) return; const file = this.files[0]; const formData = new FormData(); formData.append('image', file); gallery.insertAdjacentHTML('afterbegin', '<div class="media-item"><p>Nahrávám...</p></div>'); fetch('upload_image.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => { if (data.location) { loadMediaLibrary(); } else { alert('Chyba: ' + (data.error || 'Neznámá chyba')); loadMediaLibrary(); } }).catch(() => { alert('Chyba serveru.'); loadMediaLibrary(); }); this.value = ''; });
    form.addEventListener('submit', () => document.querySelectorAll('.wysiwyg-editor').forEach(e => e.nextElementSibling.value = e.innerHTML));
    document.querySelectorAll('.tab-nav a').forEach(link => { link.addEventListener('click', e => { e.preventDefault(); document.querySelectorAll('.tab-nav a, .tab-pane').forEach(el => el.classList.remove('active')); link.classList.add('active'); document.querySelector(link.getAttribute('href')).classList.add('active'); }); });
});
</script>
