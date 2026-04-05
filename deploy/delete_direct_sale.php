<?php
require_once 'auth.php';
require_csrf_token();
require 'db.php';

$direct_sale_id = (int)($_GET['id'] ?? 0);
$redirect = ($_GET['redirect'] ?? 'sales') === 'accounting' ? 'accounting' : 'sales';

if ($direct_sale_id <= 0) {
    $_SESSION['msg'] = 'Prodej se nepodařilo najít.';
    header('Location: index.php?view=' . $redirect);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM direct_sales WHERE id = ?');
    $stmt->execute([$direct_sale_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['msg'] = 'Rychlý prodej byl smazán z tržeb.';
    } else {
        $_SESSION['msg'] = 'Prodej už v systému není.';
    }
} catch (Throwable $e) {
    $_SESSION['msg'] = 'Chyba při mazání prodeje: ' . $e->getMessage();
}

header('Location: index.php?view=' . $redirect);
exit;
