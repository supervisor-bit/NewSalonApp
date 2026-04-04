<?php
// auth.php
$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $is_https,
    ]);
    @session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

function require_csrf_token() {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $requestToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!$sessionToken || !$requestToken || !hash_equals($sessionToken, $requestToken)) {
        $isAjax = (
            !empty($_GET['ajax']) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );

        if (!headers_sent()) {
            http_response_code(403);
        }

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'error' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.']);
        } else {
            $_SESSION['msg'] = 'Bezpečnostní ověření vypršelo. Obnovte stránku a akci opakujte.';
            if (!headers_sent()) {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            } else {
                echo '<script>window.location.href="index.php";</script>';
            }
        }
        exit;
    }
}

$allowed_pages = ['login.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (!in_array($current_page, $allowed_pages)) {
        $requested_path = $_SERVER['REQUEST_URI'] ?? $current_page;
        $requested_path = ltrim($requested_path, '/');

        if ($requested_path === '' || strpos($requested_path, 'login.php') === 0) {
            $requested_path = 'index.php';
        }

        $login_url = 'login.php?redirect=' . rawurlencode($requested_path);

        if (!headers_sent()) {
            header('Location: ' . $login_url);
            exit;
        } else {
            echo '<script>window.location.href=' . json_encode($login_url) . ';</script>';
            exit;
        }
    }
}
?>
