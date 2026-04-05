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
        $columnExists = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_favorite'")->fetch();
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN is_favorite TINYINT(1) DEFAULT 0");
        }

        $stmt = $pdo->prepare("UPDATE clients SET is_favorite = NOT COALESCE(is_favorite, 0) WHERE id = ?");
        $stmt->execute([$id]);

        $stmt2 = $pdo->prepare("SELECT is_favorite FROM clients WHERE id = ?");
        $stmt2->execute([$id]);
        $newState = (bool)$stmt2->fetchColumn();

        if ($isAjax) {
            echo json_encode(['success' => true, 'is_favorite' => $newState]);
            exit;
        }

        $_SESSION['msg'] = $newState
            ? 'Klientka byla připnuta mezi oblíbené.'
            : 'Klientka byla odepnuta z oblíbených.';
    } catch (Throwable $e) {
        if ($isAjax) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo json_encode(['success' => false, 'error' => 'Server nedokázal změnit oblíbenou klientku.']);
            exit;
        }

        $_SESSION['msg'] = 'Změna oblíbené klientky se nepodařila.';
    }
}

if (!$isAjax) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}
