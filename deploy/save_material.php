<?php require_once 'auth.php';
 
// save_material.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $category = trim($_POST['category']);
    $name = trim($_POST['name']);

    if (!empty($category) && !empty($name)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE materials SET category = ?, name = ? WHERE id = ?");
                $stmt->execute([$category, $name, $id]);
                $_SESSION['msg'] = "Odstín byl úspěšně upraven na $category $name.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO materials (category, name) VALUES (?, ?)");
                $stmt->execute([$category, $name]);
                $_SESSION['msg'] = "Nový odstín $category $name byl přidán do číselníku.";
            }
        } catch(Exception $e) {
            $_SESSION['msg'] = "Chyba při ukládání: " . $e->getMessage();
        }
    }
}

$tab = isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'settings_materials' ? '&tab=materials' : '';
header("Location: index.php?view=settings$tab");
exit;
