<?php
// plugins/shop/api/qr.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "FATAL ERROR\n";
        echo "Type: {$e['type']}\n";
        echo "Message: {$e['message']}\n";
        echo "File: {$e['file']}\n";
        echo "Line: {$e['line']}\n";
        exit;
    }
});

function b64url_decode(string $s): string|false {
    $s = trim($s);
    if ($s === '') return false;
    // URL-safe -> standard base64
    $s = strtr($s, '-_', '+/');
    // pad
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s, true);
}

try {
    // 1) Prefer stateless mode: ?d=base64url(payload)
    $d = (string)($_GET['d'] ?? '');
    $payload = '';

    if ($d !== '') {
        $decoded = b64url_decode($d);
        if ($decoded === false) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Bad request: invalid base64url\n";
            exit;
        }
        $payload = $decoded;
    } else {
        // 2) Backward-compatible session mode: ?order_id=...&token=...
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $order_id = (int)($_GET['order_id'] ?? 0);
        $token    = (string)($_GET['token'] ?? '');

        if ($order_id <= 0 || $token === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Bad request: missing d OR (order_id + token)\n";
            exit;
        }

        $entry = $_SESSION['shop_qr'][$order_id] ?? null;
        if (!$entry || empty($entry['token']) || !hash_equals((string)$entry['token'], $token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Forbidden: invalid token or missing session entry\n";
            exit;
        }

        $payload = (string)($entry['payload'] ?? '');
    }

    $payload = (string)$payload;
    if ($payload === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found: empty payload\n";
        exit;
    }

    // Load phpqrcode (same as your working test)
    $lib = __DIR__ . '/../assets/phpqrcode/qrlib.php';
    if (!file_exists($lib)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Library missing: {$lib}\n";
        exit;
    }
    require_once $lib;

    if (!class_exists('QRcode')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "phpqrcode loaded but class QRcode not found\n";
        exit;
    }

    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Output PNG directly
    QRcode::png($payload, false, QR_ECLEVEL_M, 6, 2);
    exit;

} catch (Throwable $t) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "QR.PHP ERROR\n";
    echo "Message: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . "\n";
    echo "Line: " . $t->getLine() . "\n";
    exit;
}
