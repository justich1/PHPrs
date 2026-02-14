<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/pages.php';

$page_id = $_GET['id'] ?? null;
$message = '';

// Zobrazí zprávu o úspěchu po přesměrování
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = "Stránka byla úspěšně aktualizována.";
    } elseif ($_GET['status'] === 'created') {
        $message = "Stránka byla úspěšně vytvořena.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $translations = $_POST['translations'];
    if ($page_id) {
        update_page($page_id, $translations);
        header('Location: page_edit.php?id=' . $page_id . '&status=success');
        exit;
    } else {
        $new_page_id = create_page($translations);
        header('Location: page_edit.php?id=' . $new_page_id . '&status=created');
        exit;
    }
}

$page_data = $page_id ? get_page_details($page_id) : null;
$page_title = $page_id ? "Úprava stránky" : "Vytvoření nové stránky";
include 'includes/header.php';
?>

<!-- Styly pro editor a modální okna -->
<style>
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
    
    /* CSS Reset pro tabulky uvnitř editoru, aby se předešlo konfliktům s šablonou */
    .wysiwyg-editor table { display: table !important; width: 100% !important; border-collapse: collapse !important; }
    .wysiwyg-editor tr { display: table-row !important; }
    .wysiwyg-editor td, .wysiwyg-editor th { display: table-cell !important; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
    .modal-content { background: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-content.modal-sm { max-width: 500px; }
    .modal-content h3 { margin-top: 0; }
    .modal-content label { display: block; margin-bottom: 5px; }
    .modal-content input, .modal-content select { width: 100%; padding: 8px; margin-bottom: 15px; }
    .modal-overlay.active { display: flex; }

    /* Knihovna médií */
    .media-library-body { flex-grow: 1; overflow-y: auto; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 10px 0; }
    .media-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .media-item { position: relative; border: 2px solid transparent; cursor: pointer; }
    .media-item img { width: 100%; height: 100px; object-fit: cover; display: block; }
    .media-item.selected { border-color: #007bff; }
    .media-item .delete-btn { position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-weight: bold; line-height: 24px; text-align: center; }
    .media-library-footer { padding-top: 15px; text-align: right; }
    
    /* Kontextový panel pro tabulku */
    .table-toolbar { display: none; background: #e9ecef; padding: 5px; border: 1px solid #ccc; margin-top: 5px; }

    /* Styly pro HTML editor */
    .html-editor {
        width: 100%;
        min-height: 400px;
        font-family: Consolas, "Courier New", monospace;
        font-size: 14px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 15px;
        box-sizing: border-box;
        resize: vertical;
        background-color: #2d2d2d;
        color: #f8f8f2;
    }
    .wysiwyg-toolbar.source-mode > *:not([data-command="toggleHtml"]) {
        display: none;
    }
    .wysiwyg-toolbar button[data-command="toggleHtml"].active {
        background-color: #007bff;
        color: white;
    }
</style>

<?php if ($message): ?>
    <p style="color: green; font-weight: bold;"><?= $message ?></p>
<?php endif; ?>

<form action="page_edit.php<?= $page_id ? '?id='.$page_id : '' ?>" method="post" id="page-editor-form">
    <ul class="tab-nav">
        <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
            <li><a href="#tab-<?= $code ?>" class="<?= $code == DEFAULT_LANG ? 'active' : '' ?>"><?= htmlspecialchars($name) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
            <div id="tab-<?= $code ?>" class="tab-pane <?= $code == DEFAULT_LANG ? 'active' : '' ?>">
                <?php $t = $page_data['translations'][$code] ?? null; ?>
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
                        <button type="button" data-command="toggleHtml" title="Přepnout HTML zobrazení" style="margin-left: auto; font-weight: bold;">&lt;/&gt;</button>
                    </div>
                    <div class="wysiwyg-editor" id="content_editor_<?= $code ?>" contenteditable="true"><?= $t['content'] ?? '' ?></div>
                    <textarea class="html-editor" id="html_editor_<?= $code ?>" style="display:none;"></textarea>
                    <textarea name="translations[<?= $code ?>][content]" id="content_hidden_<?= $code ?>" style="display:none;"><?= htmlspecialchars($t['content'] ?? '') ?></textarea>
                    
                    <div id="table-toolbar-<?= $code ?>" class="wysiwyg-toolbar table-toolbar" style="display: none;">
                        <span>Nástroje tabulky:</span>
                        <button type="button" data-table-action="addRow">Přidat řádek</button>
                        <button type="button" data-table-action="addCol">Přidat sloupec</button>
                        <button type="button" data-table-action="removeRow">Odebrat řádek</button>
                        <button type="button" data-table-action="removeCol">Odebrat sloupec</button>
                        <label for="cell-bgcolor-<?= $code ?>">Pozadí:</label>
                        <input type="color" id="cell-bgcolor-<?= $code ?>" data-table-style="backgroundColor" title="Barva pozadí buňky">
                        <button type="button" data-table-action="removeBgColor" title="Odebrat barvu pozadí" style="font-size: 0.9em; padding: 5px 8px;">Odebrat</button>
                        <label for="table-border-color-<?= $code ?>">Rámeček:</label>
                        <input type="color" id="table-border-color-<?= $code ?>" data-table-style="borderColor" title="Barva rámečku">
                        <input type="number" id="table-border-width-<?= $code ?>" data-table-style="borderWidth" min="0" max="10" placeholder="Šířka" style="width: 80px;">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <br>
    <button type="submit" style="padding: 10px 20px;">Uložit změny</button>
</form>

<!-- Modální okna -->
<div class="modal-overlay" id="media-library-modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px;">
            <h3>Knihovna médií</h3>
            <button type="button" id="upload-new-btn" style="background: #28a745; color: white;">Nahrát nový</button>
        </div>
        <div class="media-library-body">
            <div class="media-gallery" id="media-gallery"></div>
        </div>
        <div class="media-library-footer">
            <button type="button" id="media-cancel">Zrušit</button>
            <button type="button" id="media-insert" style="background: #007bff; color: white;" disabled>Vložit obrázek</button>
        </div>
    </div>
</div>
<div class="modal-overlay" id="shortcode-modal">
    <div class="modal-content modal-sm">
        <h3>Vložit shortcode</h3>
        <label for="shortcode-select">Vyberte z dostupných shortcodů:</label>
        <select id="shortcode-select">
            <option>Načítání...</option>
        </select>
        <div style="text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="shortcode-cancel">Zrušit</button>
            <button type="button" id="shortcode-insert-empty" style="background: #6c757d; color:white;">Vložit prázdný []</button>
            <button type="button" id="shortcode-save" style="background:#007bff;color:white;" disabled>Vložit vybraný</button>
        </div>
    </div>
</div>
<div class="modal-overlay" id="link-modal">
    <div class="modal-content modal-sm">
        <h3>Vložit / Upravit odkaz</h3>
        <label for="link-url">URL Adresa:</label>
        <input type="text" id="link-url" placeholder="https://example.com">
        <label><input type="checkbox" id="link-target" style="width: auto;"> Otevřít v nové kartě</label>
        <div style="text-align: right; margin-top: 20px;"><button type="button" id="link-cancel">Zrušit</button><button type="button" id="link-save" style="background: #007bff; color: white;">Použít</button></div>
    </div>
</div>
<div class="modal-overlay" id="image-style-modal">
    <div class="modal-content modal-sm">
        <h3>Upravit styl obrázku</h3>
        <label for="image-size">Velikost zobrazení (%): <span id="image-size-value">100</span>%</label>
        <input type="range" id="image-size" min="20" max="100" value="100" step="5">
        <label for="image-align">Zarovnání:</label>
        <select id="image-align"><option value="align-none">Žádné</option><option value="align-left">Vlevo</option><option value="align-center">Na střed</option><option value="align-right">Vpravo</option></select>
        <label for="image-style">Styl:</label>
        <select id="image-style"><option value="style-none">Bez stylu</option><option value="style-shadow">Se stínem</option><option value="style-bordered">S rámečkem</option></select>
        <label for="image-caption">Popisek:</label>
        <input type="text" id="image-caption" placeholder="Volitelný popisek obrázku">
        <div style="text-align: right;"><button type="button" id="image-style-cancel">Zrušit</button><button type="button" id="image-style-save" style="background: #007bff; color: white;">Použít</button></div>
    </div>
</div>
<div class="modal-overlay" id="youtube-modal">
    <div class="modal-content modal-sm">
        <h3>Vložit / Upravit YouTube video</h3>
        <label for="youtube-url">Odkaz na YouTube video:</label>
        <input type="text" id="youtube-url" placeholder="https://www.youtube.com/watch?v=...">
        <label for="youtube-width">Šířka (%): <span id="youtube-width-value">100</span>%</label>
        <input type="range" id="youtube-width" min="30" max="100" value="100" step="5">
        <label for="youtube-align">Zarovnání:</label>
        <select id="youtube-align"><option value="align-none">Žádné</option><option value="align-left">Vlevo</option><option value="align-center">Na střed</option><option value="align-right">Vpravo</option></select>
        <div style="text-align: right; margin-top: 20px;"><button type="button" id="youtube-cancel">Zrušit</button><button type="button" id="youtube-save" style="background: #007bff; color: white;">Použít</button></div>
    </div>
</div>
<div class="modal-overlay" id="table-modal">
    <div class="modal-content modal-sm">
        <h3>Vložit tabulku</h3>
        <label for="table-rows">Počet řádků:</label><input type="number" id="table-rows" value="3" min="1">
        <label for="table-cols">Počet sloupců:</label><input type="number" id="table-cols" value="3" min="1">
        <div style="text-align: right;"><button type="button" id="table-cancel">Zrušit</button><button type="button" id="table-save" style="background:#007bff;color:white;">Vložit</button></div>
    </div>
</div>
<input type="file" id="image-upload-input" accept="image/*" style="display: none;">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ZÁKLADNÍ PROMĚNNÉ EDITORU ---
    const form = document.getElementById('page-editor-form');
    let activeEditor = null;
    let selection = null;
    let selectedImage = null;
    let selectedLink = null;
    let selectedVideo = null;
    let activeTable = null;
    let activeCell = null;

    // --- LOGIKA PRO KNIHOVNU MÉDIÍ ---
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
                    item.innerHTML = `
                        <img src="${img.thumb_url}" alt="">
                        <button type="button" class="delete-btn" data-filename="${img.filename}">&times;</button>
                    `;
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
                fetch('delete_image.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ filename: filename })})
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            deleteBtn.parentElement.remove();
                        } else {
                            alert('Chyba při mazání: ' + (data.error || 'Neznámá chyba'));
                        }
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
    document.getElementById('media-insert').addEventListener('click', () => {
        if (selectedMedia) {
            activeEditor.focus();
            if (selection) { window.getSelection().removeAllRanges(); window.getSelection().addRange(selection); }
            const html = `<figure class="align-center style-none" style="width: 100%;"><a href="${selectedMedia.full}" class="lightbox-link"><img src="${selectedMedia.thumb}" style="width: 100%;"></a></figure><p></p>`;
            document.execCommand('insertHTML', false, html);
            mediaModal.classList.remove('active');
        }
    });
    document.getElementById('media-cancel').addEventListener('click', () => mediaModal.classList.remove('active'));

    // --- LOGIKA PRO ODKAZY ---
    const linkModal = document.getElementById('link-modal');
    const linkUrlInput = document.getElementById('link-url');
    const linkTargetCheckbox = document.getElementById('link-target');
    function openLinkModal() {
        selection = window.getSelection().getRangeAt(0);
        selectedLink = document.getSelection().anchorNode.parentElement.closest('a');
        linkUrlInput.value = selectedLink ? selectedLink.getAttribute('href') : 'https://';
        linkTargetCheckbox.checked = selectedLink ? selectedLink.target === '_blank' : false;
        linkModal.classList.add('active');
        linkUrlInput.focus();
    }
    document.getElementById('link-save').addEventListener('click', () => {
        if (selection) {
            window.getSelection().removeAllRanges(); window.getSelection().addRange(selection);
            if (selectedLink) {
                selectedLink.href = linkUrlInput.value;
                if (linkTargetCheckbox.checked) { selectedLink.target = '_blank'; } else { selectedLink.removeAttribute('target'); }
            } else {
                const newLink = `<a href="${linkUrlInput.value}" ${linkTargetCheckbox.checked ? 'target="_blank"' : ''}>${document.getSelection().toString()}</a>`;
                document.execCommand('insertHTML', false, newLink);
            }
        }
        linkModal.classList.remove('active');
        selection = null; selectedLink = null;
    });
    document.getElementById('link-cancel').addEventListener('click', () => linkModal.classList.remove('active'));

    // --- LOGIKA PRO OBRÁZKY ---
    const imageModal = document.getElementById('image-style-modal');
    const imageSizeSlider = document.getElementById('image-size');
    const imageSizeValue = document.getElementById('image-size-value');
    imageSizeSlider.addEventListener('input', () => { imageSizeValue.textContent = imageSizeSlider.value; });
    document.getElementById('image-style-save').addEventListener('click', () => {
        if (!selectedImage) return;
        const figure = selectedImage.closest('figure');
        if (!figure) return;
        const align = document.getElementById('image-align').value;
        const style = document.getElementById('image-style').value;
        figure.className = `${align} ${style}`.trim();
        figure.style.width = imageSizeSlider.value + '%';
        selectedImage.style.width = '100%';
        let caption = figure.querySelector('figcaption');
        const captionText = document.getElementById('image-caption').value;
        if (captionText) {
            if (!caption) { caption = document.createElement('figcaption'); figure.appendChild(caption); }
            caption.textContent = captionText;
        } else if (caption) {
            caption.remove();
        }
        imageModal.classList.remove('active');
        selectedImage = null;
    });
    document.getElementById('image-style-cancel').addEventListener('click', () => imageModal.classList.remove('active'));

    // --- LOGIKA PRO YOUTUBE ---
    const youtubeModal = document.getElementById('youtube-modal');
    const youtubeWidthSlider = document.getElementById('youtube-width');
    const youtubeWidthValue = document.getElementById('youtube-width-value');
    youtubeWidthSlider.addEventListener('input', () => { youtubeWidthValue.textContent = youtubeWidthSlider.value; });
    document.getElementById('youtube-save').addEventListener('click', () => {
        const url = document.getElementById('youtube-url').value;
        const videoId = url.match(/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        if (!videoId || !videoId[1]) { alert('Neplatná URL adresa YouTube videa.'); return; }
        const width = youtubeWidthSlider.value;
        const align = document.getElementById('youtube-align').value;
        if (selectedVideo) {
            selectedVideo.className = `video-wrapper ${align}`.trim();
            selectedVideo.style.width = width + '%';
            selectedVideo.querySelector('iframe').src = `https://www.youtube.com/embed/${videoId[1]}`;
        } else {
            const embedHtml = `<div class="video-wrapper ${align}" style="width: ${width}%;"><iframe src="https://www.youtube.com/embed/${videoId[1]}" frameborder="0" allowfullscreen></iframe></div><p></p>`;
            if (selection) {
                window.getSelection().removeAllRanges(); window.getSelection().addRange(selection);
                document.execCommand('insertHTML', false, embedHtml);
            }
        }
        youtubeModal.classList.remove('active');
        selectedVideo = null;
    });
    document.getElementById('youtube-cancel').addEventListener('click', () => youtubeModal.classList.remove('active'));

    // --- LOGIKA PRO TABULKY ---
    const tableModal = document.getElementById('table-modal');
    document.getElementById('table-save').addEventListener('click', () => {
        const rows = document.getElementById('table-rows').value; 
        const cols = document.getElementById('table-cols').value;
        
        // Vytvoříme HTML jako string
        let tableHtmlString = '<div class="table-wrapper"><table style="border-collapse: collapse; border: 1px solid #ccc;"><tbody>';
        for (let i = 0; i < rows; i++) {
            tableHtmlString += '<tr>';
            for (let j = 0; j < cols; j++) { tableHtmlString += '<td style="border: 1px solid #ccc; padding: 8px;"><p><br></p></td>'; }
            tableHtmlString += '</tr>';
        }
        tableHtmlString += '</tbody></table></div><p>&nbsp;</p>'; // Přidáme odstavec pro další psaní

        // Obnovíme focus a pozici kurzoru
        activeEditor.focus();
        if (selection) {
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(selection);
        }

        // Spolehlivé vložení
        document.execCommand('insertHTML', false, tableHtmlString);
        tableModal.classList.remove('active');
    });
    document.getElementById('table-cancel').addEventListener('click', () => tableModal.classList.remove('active'));

    // --- INICIALIZACE VŠECH EDITORŮ NA STRÁNCE ---
    document.querySelectorAll('.wysiwyg-editor').forEach(editor => {
        // FUNKCE PRO OČIŠTĚNÍ VKLÁDANÉHO HTML
        editor.addEventListener('paste', function(event) {
            event.preventDefault();
            let pastedHtml = (event.clipboardData || window.clipboardData).getData('text/html');
            if (!pastedHtml) {
                pastedHtml = (event.clipboardData || window.clipboardData).getData('text/plain').replace(/\r?\n/g, '<br>');
            }
            const allowedTags = ['P', 'B', 'I', 'U', 'A', 'H2', 'H3', 'UL', 'OL', 'LI', 'BR', 'TABLE', 'TBODY', 'TR', 'TD', 'TH', 'THEAD', 'TFOOT', 'FIGURE', 'FIGCAPTION', 'IMG', 'DIV'];
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = pastedHtml;
            tempDiv.querySelectorAll('*').forEach(el => {
                if (!allowedTags.includes(el.tagName)) {
                    // Místo smazání "rozbalíme" obsah
                    el.outerHTML = el.innerHTML;
                } else {
                    const allowedAttrs = ['href', 'target', 'class', 'style', 'src', 'alt', 'title'];
                    for (let i = el.attributes.length - 1; i >= 0; i--) {
                        if (!allowedAttrs.includes(el.attributes[i].name)) {
                            el.removeAttribute(el.attributes[i].name);
                        }
                    }
                }
            });
            document.execCommand('insertHTML', false, tempDiv.innerHTML);
        });

        const toolbar = editor.previousElementSibling;
        const htmlEditor = editor.nextElementSibling;
        const tableToolbar = htmlEditor.nextElementSibling.nextElementSibling;

        // FUNKCE PRO FORMÁTOVÁNÍ HTML
        function formatHtml(html) {
            let indent = '';
            const tab = '    '; // 4 mezery pro odsazení
            let result = '';
            // Rozdělení podle tagů, ale zachování obsahu mezi nimi
            html.replace(/<[^>]*>|[^<]+/g, (match) => {
                if (match.startsWith('</')) { // Uzavírací tag
                    indent = indent.substring(tab.length);
                    result += '\n' + indent + match;
                } else if (match.startsWith('<')) { // Otevírací tag
                    result += (result ? '\n' : '') + indent + match;
                    // Neodsazujeme u "void" elementů
                    if (!match.match(/<(\w+)[^>]*\s*\/>/) && !['<br>', '<img>', '<hr>'].includes(match.toLowerCase())) {
                       indent += tab;
                    }
                } else { // Prostý text
                    // Odstraníme prázdné řádky a nadbytečné mezery
                    const trimmed = match.trim();
                    if (trimmed) {
                        result += '\n' + indent + trimmed;
                    }
                }
            });
            return result.trim();
        }

        // OBSLUHA HLAVNÍ NÁSTROJOVÉ LIŠTY
        toolbar.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            event.preventDefault();
            activeEditor = editor;
            if(window.getSelection().rangeCount > 0) selection = window.getSelection().getRangeAt(0);

            const command = button.dataset.command;
            
            if (command === 'toggleHtml') {
                if (toolbar.classList.contains('source-mode')) {
                    editor.innerHTML = htmlEditor.value;
                    editor.style.display = 'block';
                    htmlEditor.style.display = 'none';
                    toolbar.classList.remove('source-mode');
                    button.classList.remove('active');
                } else {
                    htmlEditor.value = formatHtml(editor.innerHTML);
                    editor.style.display = 'none';
                    htmlEditor.style.display = 'block';
                    toolbar.classList.add('source-mode');
                    button.classList.add('active');
                }
            }
            else if (command === 'createLink') { openLinkModal(); }
            else if (command === 'insertImage') {
                selectedMedia = null; insertBtn.disabled = true;
                loadMediaLibrary(); mediaModal.classList.add('active');
            }
            else if (command === 'insertYoutube') {
                selectedVideo = null; document.getElementById('youtube-url').value = '';
                document.getElementById('youtube-width').value = 100;
                document.getElementById('youtube-width-value').textContent = 100;
                document.getElementById('youtube-align').value = 'align-none';
                youtubeModal.classList.add('active');
            } else if (command === 'insertTable') {
                tableModal.classList.add('active');
            } else if (command === 'insertShort') {
                const select = document.getElementById('shortcode-select');
                select.innerHTML = '<option>Načítání...</option>';
                document.getElementById('shortcode-save').disabled = true;

                fetch('get_shortcodes.php')
                    .then(response => response.json())
                    .then(data => {
                        select.innerHTML = '';
                        if (data.error) {
                            select.innerHTML = `<option>${data.error}</option>`;
                            return;
                        }
                        if (Object.keys(data).length > 0) {
                            for (const code in data) {
                                const option = document.createElement('option');
                                option.value = code;
                                option.textContent = data[code];
                                select.appendChild(option);
                            }
                            document.getElementById('shortcode-save').disabled = false;
                        } else {
                            select.innerHTML = '<option>Žádné shortcody nebyly nalezeny.</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Chyba při komunikaci se serverem:', error);
                        select.innerHTML = '<option>Chyba při načítání.</option>';
                    });
                shortcodeModal.classList.add('active');
            } else { 
                document.execCommand(command, false, null);
            }
        });

        toolbar.addEventListener('input', e => {
            if (e.target.type === 'color') document.execCommand(e.target.dataset.command, false, e.target.value);
            if (e.target.matches('.format-block')) document.execCommand('formatBlock', false, e.target.value);
        });

        // --- OBSLUHA NÁSTROJŮ PRO TABULKU ---
        tableToolbar.addEventListener('click', event => {
            const button = event.target.closest('button[data-table-action]');
            if (!button || !activeTable) return;
            const action = button.dataset.tableAction;
            
            if (action === 'removeBgColor') {
                if (activeCell) {
                    activeCell.style.backgroundColor = '';
                }
                return;
            }

            const tbody = activeTable.querySelector('tbody');
            if (!tbody) return;

            if (action === 'addRow') {
                const firstRow = tbody.querySelector('tr');
                if (!firstRow) return;
                const colCount = firstRow.cells.length;
                const newRow = tbody.insertRow();
                for (let i = 0; i < colCount; i++) {
                    const newCell = newRow.insertCell();
                    newCell.innerHTML = '<p><br></p>';
                    newCell.style.cssText = firstRow.cells[i].style.cssText;
                }
            } else if (action === 'addCol') {
                for (const row of tbody.rows) {
                    const newCell = row.insertCell();
                    newCell.innerHTML = '<p><br></p>';
                    if (row.cells.length > 1) {
                        newCell.style.cssText = row.cells[row.cells.length - 2].style.cssText;
                    }
                }
            } else if (action === 'removeRow') {
                if (activeCell && activeTable.rows.length > 1) {
                    activeCell.parentElement.remove();
                } else {
                    alert('Nelze smazat poslední řádek.');
                }
            } else if (action === 'removeCol') {
                if (activeCell && activeCell.parentElement.cells.length > 1) {
                    const colIndex = activeCell.cellIndex;
                    for (const row of activeTable.rows) {
                        row.deleteCell(colIndex);
                    }
                } else {
                    alert('Nelze smazat poslední sloupec.');
                }
            }
        });
        tableToolbar.addEventListener('input', event => {
            const input = event.target;
            if (input.dataset.tableStyle) {
                if (input.dataset.tableStyle === 'backgroundColor' && activeCell) {
                    activeCell.style.backgroundColor = input.value;
                } 
                else {
                    activeTable.style[input.dataset.tableStyle] = input.value + (input.type === 'number' ? 'px' : '');
                    activeTable.querySelectorAll('td, th').forEach(cell => {
                        cell.style[input.dataset.tableStyle] = input.value + (input.type === 'number' ? 'px' : '');
                    });
                }
            }
        });

        // OBSLUHA KLIKNUTÍ V EDITORU
        editor.addEventListener('click', event => {
            const target = event.target;
            activeCell = target.closest('td');
            const table = target.closest('table');
            if (table) {
                activeTable = table;
                tableToolbar.style.display = 'flex';
                tableToolbar.querySelector('[data-table-style="borderColor"]').value = table.style.borderColor || '#cccccc';
                tableToolbar.querySelector('[data-table-style="borderWidth"]').value = parseInt(table.style.borderWidth) || 1;
            } else {
                activeTable = null; tableToolbar.style.display = 'none';
            }
            if (target.tagName === 'IMG') {
                selectedImage = target;
                const figure = selectedImage.closest('figure');
                if (figure) { // OPRAVA: Spustí se, jen pokud je obrázek ve <figure>
                    const currentWidth = figure.style.width ? parseInt(figure.style.width) : 100;
                    imageSizeSlider.value = currentWidth;
                    imageSizeValue.textContent = currentWidth;
                    document.getElementById('image-align').value = figure.className.match(/align-\w+/) ? figure.className.match(/align-\w+/)[0] : 'align-none';
                    document.getElementById('image-style').value = figure.className.match(/style-\w+/) ? figure.className.match(/style-\w+/)[0] : 'style-none';
                    const caption = figure.querySelector('figcaption');
                    document.getElementById('image-caption').value = caption ? caption.textContent : '';
                    imageModal.classList.add('active');
                }
            } else if (target.closest('.video-wrapper')) {
                selectedVideo = target.closest('.video-wrapper');
                if (selectedVideo) { // OPRAVA: Pojistka
                    const iframeSrc = selectedVideo.querySelector('iframe').src;
                    const currentWidth = selectedVideo.style.width ? parseInt(selectedVideo.style.width) : 100;
                    document.getElementById('youtube-url').value = iframeSrc.replace('embed/', 'watch?v=');
                    youtubeWidthSlider.value = currentWidth;
                    youtubeWidthValue.textContent = currentWidth;
                    document.getElementById('youtube-align').value = selectedVideo.className.match(/align-\w+/) ? selectedVideo.className.match(/align-\w+/)[0] : 'align-none';
                    youtubeModal.classList.add('active');
                }
            }
        });
    });

    // --- GLOBÁLNÍ OBSLUHA TLAČÍTEK V MODÁLNÍCH OKNECH ---
    const shortcodeModal = document.getElementById('shortcode-modal');
    document.getElementById('shortcode-save').addEventListener('click', () => {
        const selectedShortcode = document.getElementById('shortcode-select').value;
        if (selectedShortcode && activeEditor) {
            activeEditor.focus();
            if (selection) { window.getSelection().removeAllRanges(); window.getSelection().addRange(selection); }
            document.execCommand('insertHTML', false, selectedShortcode);
        }
        shortcodeModal.classList.remove('active');
    });
    document.getElementById('shortcode-insert-empty').addEventListener('click', () => {
        if (activeEditor) {
            activeEditor.focus();
            if (selection) { window.getSelection().removeAllRanges(); window.getSelection().addRange(selection); }
            document.execCommand('insertHTML', false, '[]');
        }
        shortcodeModal.classList.remove('active');
    });
    document.getElementById('shortcode-cancel').addEventListener('click', () => {
        shortcodeModal.classList.remove('active');
    });

    // --- ZBÝVAJÍCÍ GLOBÁLNÍ FUNKCE ---
    document.getElementById('image-upload-input').addEventListener('change', function() {
        if (this.files.length === 0) return;
        const file = this.files[0];
        const formData = new FormData();
        formData.append('image', file);
        gallery.insertAdjacentHTML('afterbegin', '<div class="media-item"><p>Nahrávám...</p></div>');
        fetch('upload_image.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.error) { alert('Chyba: ' + data.error); }
                loadMediaLibrary();
            })
            .catch(() => { alert('Chyba serveru.'); loadMediaLibrary(); });
        this.value = '';
    });

    form.addEventListener('submit', () => {
        document.querySelectorAll('.wysiwyg-editor').forEach(editor => {
            const toolbar = editor.previousElementSibling;
            const htmlEditor = editor.nextElementSibling;
            const hiddenTextarea = htmlEditor.nextElementSibling;

            if (toolbar.classList.contains('source-mode')) {
                // Pokud je v režimu HTML, vezmeme obsah z textového pole
                hiddenTextarea.value = htmlEditor.value;
            } else {
                // Jinak vezmeme obsah z vizuálního editoru
                hiddenTextarea.value = editor.innerHTML;
            }
        });
    });

    // --- PŘEPÍNÁNÍ ZÁLOŽEK ---
    document.querySelectorAll('.tab-nav a').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const activeTab = document.querySelector('.tab-pane.active .wysiwyg-editor');
            if (activeTab) {
                // Musíme synchronizovat obsah před přepnutím
                const toolbar = activeTab.previousElementSibling;
                const htmlEditor = activeTab.nextElementSibling;
                const hiddenTextarea = htmlEditor.nextElementSibling;
                if (toolbar.classList.contains('source-mode')) {
                    hiddenTextarea.value = htmlEditor.value;
                } else {
                    hiddenTextarea.value = activeTab.innerHTML;
                }
            }
            document.querySelectorAll('.tab-nav a, .tab-pane').forEach(el => el.classList.remove('active'));
            link.classList.add('active');
            document.querySelector(link.getAttribute('href')).classList.add('active');
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
