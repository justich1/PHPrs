<?php
// functions/users.php - Funkce pro správu uživatelů

/**
 * Získá všechny uživatele z databáze.
 * @return array Pole uživatelů.
 */
function get_all_users() {
    $db = db_connect();
    return $db->query("SELECT id, username, email FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Získá jednoho uživatele podle ID.
 * @param int $id ID uživatele.
 * @return array|false Data uživatele nebo false.
 */
function get_user_by_id($id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Vytvoří nového uživatele.
 * @param array $data Data z formuláře.
 * @return int ID nového uživatele.
 */
function create_user($data) {
    $db = db_connect();
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$data['username'], $data['email'], $hashed_password]);
    return $db->lastInsertId();
}

/**
 * Aktualizuje stávajícího uživatele.
 * @param int $id ID uživatele.
 * @param array $data Data z formuláře.
 * @return bool True v případě úspěchu.
 */
function update_user($id, $data) {
    $db = db_connect();
    // Pokud je vyplněno heslo, aktualizujeme ho. Jinak ne.
    if (!empty($data['password'])) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$data['username'], $data['email'], $hashed_password, $id]);
    } else {
        $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$data['username'], $data['email'], $id]);
    }
}

/**
 * Smaže uživatele.
 * @param int $id ID uživatele.
 */
function delete_user($id) {
    // Zabrání smazání hlavního administrátora s ID 1
    if ($id == 1) {
        return;
    }
    $db = db_connect();
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
}
