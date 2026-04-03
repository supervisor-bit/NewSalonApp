<?php require_once 'auth.php';
 
// save_client.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);

    if (!empty($first_name) && !empty($last_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, phone, preferred_interval) VALUES (?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $phone, !empty($_POST['preferred_interval']) ? (int)$_POST['preferred_interval'] : null]);
            $client_id = $pdo->lastInsertId();
            $_SESSION['msg'] = "Klientka byla úspěšně vytvořena. Nezapomeňte u ní vyplnit vlasovou diagnostiku!";
            header("Location: index.php?client_id=" . $client_id);
            exit;
        } catch(Exception $e) {
            $_SESSION['msg'] = "Chyba při vytváření: " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "Jméno a příjmení jsou povinné údaje.";
    }
}
header("Location: index.php");
exit;
