<?php require_once 'auth.php';
 
// toggle_home_product.php

require 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAjax = isset($_GET['ajax']) ? (bool)$_GET['ajax'] : false;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($isAjax) {
            $stmt2 = $pdo->prepare("SELECT is_active FROM products WHERE id = ?");
            $stmt2->execute([$id]);
            $newState = $stmt2->fetchColumn();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'is_active' => (bool)$newState]);
            exit;
        }

        $_SESSION['msg'] = "Stav produktu byl změněn.";
    } catch(Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['msg'] = "Chyba při úpravě produktu.";
    }
}

if (!$isAjax) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
