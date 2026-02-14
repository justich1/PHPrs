<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/pages.php';

// --- Funkce (zachovány přesně podle vašeho kódu) ---

function get_menu_details($menu_id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
    $stmt->execute([$menu_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$details) return null;

    $stmt_items = $db->prepare("
        SELECT mi.id, mi.page_id, mi.custom_url, mi.item_order, pt.slug as page_slug
        FROM menu_items mi
        LEFT JOIN pages_translations pt ON mi.page_id = pt.page_id AND pt.language_code = :lang
        WHERE mi.menu_id = :menu_id
        ORDER BY mi.item_order ASC
    ");
    $stmt_items->execute(['menu_id' => $menu_id, 'lang' => DEFAULT_LANG]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $stmt_trans = $db->prepare("SELECT * FROM menu_items_translations WHERE item_id = ?");
    foreach ($items as &$item) {
        $stmt_trans->execute([$item['id']]);
        $translations = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);
        $item['translations'] = [];
        foreach ($translations as $t) {
            $item['translations'][$t['language_code']] = $t;
        }
    }
    unset($item);

    $details['items'] = $items;
    return $details;
}

function add_menu_item($menu_id, $data) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        $page_id = ($data['link_type'] ?? '') === 'page' ? (int)($data['page_id'] ?? 0) : null;
        $custom_url = ($data['link_type'] ?? '') === 'custom' ? ($data['custom_url'] ?? null) : null;

        $stmt = $db->prepare("INSERT INTO menu_items (menu_id, page_id, custom_url) VALUES (?, ?, ?)");
        $stmt->execute([$menu_id, $page_id, $custom_url]);
        $item_id = $db->lastInsertId();

        $stmt_trans = $db->prepare("INSERT INTO menu_items_translations (item_id, language_code, title) VALUES (?, ?, ?)");
        foreach (($data['translations'] ?? []) as $lang => $trans) {
            if (!empty($trans['title'])) {
                $stmt_trans->execute([$item_id, $lang, $trans['title']]);
            }
        }
        $db->commit();
        return true;
    } catch (\PDOException $e) {
        $db->rollBack();
        return false;
    }
}

function update_menu_item($item_id, $translations) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        $stmt_del = $db->prepare("DELETE FROM menu_items_translations WHERE item_id = ?");
        $stmt_del->execute([$item_id]);

        $stmt_ins = $db->prepare("INSERT INTO menu_items_translations (item_id, language_code, title) VALUES (?, ?, ?)");
        foreach ($translations as $lang => $trans) {
            if (!empty($trans['title'])) {
                $stmt_ins->execute([$item_id, $lang, $trans['title']]);
            }
        }
        $db->commit();
        return true;
    } catch (\PDOException $e) {
        $db->rollBack();
        return false;
    }
}

function delete_menu_item($item_id) {
    $db = db_connect();
    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$item_id]);
}

function update_menu_order($order_data) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE menu_items SET item_order = ? WHERE id = ?");
        foreach ($order_data as $order => $id) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        $db->commit();
        return true;
    } catch (\PDOException $e) {
        $db->rollBack();
        return "Chyba databáze: " . $e->getMessage();
    }
}

// --- Zpracování AJAX pro uložení pořadí ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_ajax') {
    header('Content-Type: application/json');
    if (!empty($_POST['item_order']) && is_array($_POST['item_order'])) {
        $result = update_menu_order($_POST['item_order']);
        if ($result === true) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Chybějící data o pořadí.']);
    }
    exit;
}

// --- Základní logika ---

$menu_id = $_GET['id'] ?? null;
if (!$menu_id) {
    header('Location: menus.php');
    exit;
}

$message = '';
$error = '';

// Smazání položky
if (isset($_GET['action'], $_GET['item_id']) && $_GET['action'] === 'delete_item') {
    delete_menu_item((int)$_GET['item_id']);
    header('Location: menu_edit.php?id=' . $menu_id . '&status=deleted');
    exit;
}

// Zpracování formulářů přidání / aktualizace
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_item') {
        if (add_menu_item($menu_id, $_POST)) {
            $message = "Položka byla přidána.";
        } else {
            $error = "Chyba při přidávání položky.";
        }
    } elseif ($action === 'update_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id && update_menu_item($item_id, $_POST['translations'] ?? [])) {
            $message = "Položka byla aktualizována.";
        } else {
            $error = "Chyba při aktualizaci položky.";
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $message = "Položka byla smazána.";
}

$menu_details = get_menu_details($menu_id);
if (!$menu_details) {
    die("Menu nenalezeno.");
}

$all_pages = get_all_pages();
$page_title = "Úprava menu: " . htmlspecialchars($menu_details['name']);

// Pomocné JSON pro JS
$supportedLangs = json_encode(SUPPORTED_LANGS);
$itemsJson = json_encode(array_column($menu_details['items'], null, 'id'));

include 'includes/header.php';
?>
<style>
    .drag-handle { cursor: move; padding: 0 10px; color: #aaa; font-size: 1.2em; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000; display: none; justify-content: center; align-items: center; }
    .modal-content { background: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 500px; }
    .modal-content h3 { margin-top: 0; }
    .modal-content label { display: block; margin-bottom: 5px; text-align: left; }
    .modal-content input { width: 100%; padding: 8px; margin-bottom: 15px; box-sizing: border-box; }
    .modal-overlay.active { display: flex; }
</style>

<div id="ajax-message" style="font-weight: bold; margin-bottom: 15px;"></div>

<?php if ($message): ?>
    <p style="color: green; font-weight: bold;"><?= $message ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p style="color: red; font-weight: bold;"><?= $error ?></p>
<?php endif; ?>

<h3>Položky menu</h3>
<p>Změňte pořadí přetažením řádků. Pro úpravu názvů klikněte na "Upravit".</p>

<div>
    <table>
        <thead>
            <tr>
                <th style="width: 50px;"></th>
                <th>Název (výchozí jazyk)</th>
                <th>Odkaz</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody id="sortable-menu">
            <?php foreach($menu_details['items'] as $item): ?>
            <tr class="item-row" data-id="<?= $item['id'] ?>">
                <td class="drag-handle">&#9776;</td>
                <td><?= htmlspecialchars($item['translations'][DEFAULT_LANG]['title'] ?? 'Chybí překlad') ?></td>
                <td><?= $item['page_slug'] ? ('Stránka: ' . $item['page_slug']) : ('Vlastní URL: ' . htmlspecialchars($item['custom_url'])) ?></td>
                <td>
                    <button type="button" class="edit-item-btn" data-item-id="<?= $item['id'] ?>">Upravit</button>
                    <a href="menu_edit.php?id=<?= $menu_id ?>&action=delete_item&item_id=<?= $item['id'] ?>" onclick="return confirm('Opravdu si přejete smazat tuto položku?');" style="margin-left: 10px;">Smazat</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <button type="button" id="save-order-btn" class="button green">Uložit pořadí</button>
</div>

<hr style="margin: 40px 0;">

<h3>Přidat novou položku</h3>
<form action="menu_edit.php?id=<?= $menu_id ?>" method="post">
    <input type="hidden" name="action" value="add_item">
    <h4>1. Vyberte typ odkazu</h4>
    <label><input type="radio" name="link_type" value="page" checked onchange="toggleLinkType(this.value)"> Odkaz na existující stránku</label>
    <label><input type="radio" name="link_type" value="custom" onchange="toggleLinkType(this.value)"> Vlastní URL</label>
    <div id="link_type_page" style="margin-top: 10px;">
        <label for="page_id">Vyberte stránku:</label>
        <select name="page_id" id="page_id">
            <?php foreach($all_pages as $page): ?>
                <option value="<?= $page['id'] ?>"><?= htmlspecialchars($page['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="link_type_custom" style="display:none; margin-top: 10px;">
        <label for="custom_url">Vlastní URL:</label>
        <input type="text" name="custom_url" id="custom_url" placeholder="https://example.com" style="width: 100%;">
    </div>
    <h4 style="margin-top: 20px;">2. Vyplňte názvy položky</h4>
    <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
        <label for="add_title_<?= $code ?>"><?= $name ?>:</label>
        <input type="text" id="add_title_<?= $code ?>" name="translations[<?= $code ?>][title]" style="width: 100%; margin-bottom: 10px;" required>
    <?php endforeach; ?>
    <br>
    <button type="submit">Přidat položku do menu</button>
</form>

<!-- Modální okno pro úpravu názvů -->
<div class="modal-overlay" id="edit-item-modal">
    <div class="modal-content">
        <h3>Úprava překladů</h3>
        <form action="menu_edit.php?id=<?= $menu_id ?>" method="post">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" id="edit-item-id">
            <div id="edit-item-translations"></div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" id="edit-item-cancel">Zrušit</button>
                <button type="submit">Uložit názvy</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const allItemsData = <?= $itemsJson ?>;
    const supportedLangs = <?= $supportedLangs ?>;

    const editModal = document.getElementById('edit-item-modal');
    const editTranslationsContainer = document.getElementById('edit-item-translations');
    const editItemIdInput = document.getElementById('edit-item-id');

    document.querySelectorAll('.edit-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const itemData = allItemsData[itemId];
            
            editItemIdInput.value = itemId;
            editTranslationsContainer.innerHTML = '';

            for (const [code, name] of Object.entries(supportedLangs)) {
                const value = itemData.translations[code] ? itemData.translations[code].title : '';

                const label = document.createElement('label');
                label.htmlFor = `edit_title_${itemId}_${code}`;
                label.textContent = name + ':';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.id = `edit_title_${itemId}_${code}`;
                input.name = `translations[${code}][title]`;
                input.value = value;
                
                editTranslationsContainer.appendChild(label);
                editTranslationsContainer.appendChild(input);
            }

            editModal.classList.add('active');
        });
    });

    document.getElementById('edit-item-cancel').addEventListener('click', () => {
        editModal.classList.remove('active');
    });

    function toggleLinkType(type) {
        document.getElementById('link_type_page').style.display = type === 'page' ? 'block' : 'none';
        document.getElementById('link_type_custom').style.display = type === 'custom' ? 'block' : 'none';
    }

    const sortableMenu = document.getElementById('sortable-menu');
    if (sortableMenu) {
        Sortable.create(sortableMenu, {
            animation: 150,
            handle: '.drag-handle',
        });
    }

    document.getElementById('save-order-btn').addEventListener('click', function() {
        const itemRows = sortableMenu.querySelectorAll('tr.item-row');
        const order = Array.from(itemRows).map(row => row.dataset.id);
        
        const formData = new FormData();
        formData.append('action', 'update_order_ajax');
        order.forEach((id, index) => {
            formData.append(`item_order[${index}]`, id);
        });

        const messageDiv = document.getElementById('ajax-message');
        messageDiv.style.color = 'orange';
        messageDiv.textContent = 'Ukládám...';

        fetch('menu_edit.php?id=<?= $menu_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error(text || 'Chyba serveru') });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                messageDiv.style.color = 'green';
                messageDiv.textContent = 'Pořadí bylo úspěšně uloženo.';
            } else {
                messageDiv.style.color = 'red';
                messageDiv.textContent = 'Chyba při ukládání: ' + (data.error || 'Neznámá chyba.');
            }
        })
        .catch(error => {
            messageDiv.style.color = 'red';
            messageDiv.textContent = 'Chyba serveru: ' + error.message;
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
