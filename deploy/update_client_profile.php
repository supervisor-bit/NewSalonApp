<?php require_once 'auth.php';
 
// update_client_profile.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);

    if (!empty($first_name) && !empty($last_name) && $client_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET first_name = ?, last_name = ?, phone = ?, preferred_interval = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $phone, !empty($_POST['preferred_interval']) ? (int)$_POST['preferred_interval'] : null, $client_id]);
            $_SESSION['msg'] = "Základní profil klienta (příjmení/telefon) byl úspěšně upraven.";
        } catch(Exception $e) {
            $_SESSION['msg'] = "Chyba při aktualizaci klienta: " . $e->getMessage();
        }
    }
    
    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
