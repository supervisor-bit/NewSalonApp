<?php require_once 'auth.php';

require 'db.php';

$id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$isAjax = isset($_GET['ajax']) ? (bool)$_GET['ajax'] : false;

if ($isAjax && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if ($id > 0) {
    require_csrf_token();

    try {
        $columnExists = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_active'")->fetch();
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }

        $stmt = $pdo->prepare("UPDATE clients SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);

        $stmt2 = $pdo->prepare("SELECT is_active FROM clients WHERE id = ?");
        $stmt2->execute([$id]);
        $newState = (bool)$stmt2->fetchColumn();

        if ($isAjax) {
            echo json_encode(['success' => true, 'is_active' => $newState]);
            exit;
        }

        $_SESSION['msg'] = $newState
            ? 'Klientka byla vrácena do hlavního seznamu.'
            : 'Klientka byla přesunuta mezi neaktivní.';
    } catch (Throwable $e) {
        if ($isAjax) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo json_encode(['success' => false, 'error' => 'Server nedokázal změnit stav klientky.']);
            exit;
        }

        $_SESSION['msg'] = 'Změna stavu klientky se nepodařila.';
    }
}

if (!$isAjax) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}
