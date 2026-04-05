<?php require_once 'auth.php';
 
// update_client_profile.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $raw_tags = (string)($_POST['client_tags'] ?? '');

    $normalize_tags = static function ($value) {
        $parts = preg_split('/[\r\n,;]+/u', (string)$value);
        $tags = [];
        $seen = [];
        foreach ($parts as $part) {
            $tag = trim(preg_replace('/\s+/u', ' ', (string)$part));
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = mb_substr($tag, 0, 40, 'UTF-8');
            if (count($tags) >= 8) {
                break;
            }
        }
        return implode(', ', $tags);
    };

    $client_tags = $normalize_tags($raw_tags);

    if (!empty($first_name) && !empty($last_name) && $client_id > 0) {
        try {
            $tagCol = $pdo->query("SHOW COLUMNS FROM clients LIKE 'client_tags'")->fetch();
            if (!$tagCol) {
                $pdo->exec("ALTER TABLE clients ADD COLUMN client_tags VARCHAR(255) DEFAULT NULL");
            }

            $stmt = $pdo->prepare("UPDATE clients SET first_name = ?, last_name = ?, phone = ?, preferred_interval = ?, client_tags = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $phone, !empty($_POST['preferred_interval']) ? (int)$_POST['preferred_interval'] : null, $client_tags ?: null, $client_id]);
            $_SESSION['msg'] = "Profil klientky byl úspěšně upraven.";
        } catch(Exception $e) {
            $_SESSION['msg'] = "Chyba při aktualizaci klienta: " . $e->getMessage();
        }
    }
    
    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
