<?php require_once 'auth.php';
 
// save_home_product.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $brand = trim($_POST['brand'] ?? 'Ostatní');
    $name = trim($_POST['name'] ?? '');
    $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;

    if (!empty($name)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE products SET brand = ?, name = ?, price = ? WHERE id = ?");
                $stmt->execute([$brand, $name, $price, $id]);
                $_SESSION['msg'] = "Produkt byl úspěšně upraven na $brand $name.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (brand, name, price) VALUES (?, ?, ?)");
                $stmt->execute([$brand, $name, $price]);
                $_SESSION['msg'] = "Nový produkt $brand $name byl přidán do číselníku.";
            }
        } catch (Exception $e) {
            $_SESSION['msg'] = "Chyba při ukládání produktu: " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "Název produktu je povinný.";
    }
}

$tab = isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'settings_products' ? '&tab=products' : '';
header("Location: index.php?view=settings$tab");
exit;
