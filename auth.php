<?php
// auth.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

$allowed_pages = ['login.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (!in_array($current_page, $allowed_pages)) {
        if (!headers_sent()) {
            header("Location: login.php");
            exit;
        } else {
            echo '<script>window.location.href="login.php";</script>';
            exit;
        }
    }
}
?>
