<?php
require_once 'db.php';

// Zpracování smazání instalátoru
if (isset($_POST['delete_self'])) {
    unlink(__FILE__);
    header("Location: index.php");
    exit;
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Instalace | Profi Kadeřnická Karta</title>
    <style>
        body { font-family: 'Inter', sans-serif; padding: 40px; line-height: 1.6; color: #1e293b; background: #f8fafc; }
        .card { background: #white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; background: #fff; }
        h1 { color: #0f172a; font-size: 24px; margin-top: 0; }
        .warning { background: #fff1f2; border: 1px solid #fee2e2; color: #be123c; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .success { color: #10b981; font-weight: 600; margin: 5px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #0f172a; color: #fff; text-decoration: none; border: none; border-radius: 8px; font-weight: 700; margin-top: 10px; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-green { background: #10b981; } .btn-green:hover { background: #059669; }
        .btn-red { background: #ef4444; margin-top: 30px; font-size: 13px; } .btn-red:hover { background: #dc2626; }
    </style>
</head>
<body>
<div class='card'>";

echo "<h1>🛠️ Instalační skript salonu</h1>";

if (!isset($_GET['run'])) {
    echo "<div class='warning'>⚠️ VAROVÁNÍ: Spuštění tohoto skriptu SMAŽE veškerá současná data a nastaví čistou databázi!</div>";
    echo "<p>Chcete-li pokračovat s novou instalací a importem dat (včetně demo klientky), klikněte níže:</p>";
    echo "<a href='?run=1' class='btn'>SPUSTIT INSTALACI A IMPORT</a>";
    echo "</div></body></html>";
    exit;
}

try {
    // 1. Spuštění schématu
    $sqlData = file_get_contents('schema.sql');
    if (!$sqlData) throw new Exception("Soubor schema.sql nebyl nalezen!");
    
    $queries = explode(';', $sqlData);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    foreach ($queries as $q) {
        $q = trim($q);
        if ($q) $pdo->exec($q);
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "<div class='success'>✅ Databázová struktura i tabulka uživatelů obnovena.</div>";

    // 2. Import barev
    $materialCount = 0;
    if (file_exists('barvy_loreal.csv')) {
        $handle = fopen('barvy_loreal.csv', 'r');
        fgetcsv($handle, 0, ';'); 
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if (count($data) < 3) continue;
            $stmt = $pdo->prepare("INSERT INTO materials (brand, category, name) VALUES (?, ?, ?)");
            $stmt->execute([$data[0], $data[1], $data[2]]);
            $materialCount++;
        }
        fclose($handle);
        echo "<div class='success'>✅ Importováno $materialCount odstínů barev.</div>";
    }

    // 3. Import produktů
    $productCount = 0;
    if (file_exists('produkty_loreal.csv')) {
        $handle = fopen('produkty_loreal.csv', 'r');
        fgetcsv($handle, 0, ';'); 
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if (count($data) < 6) continue;
            $fullName = $data[1] . ' - ' . $data[2] . ' (' . $data[3] . ')';
            $stmt = $pdo->prepare("INSERT INTO products (brand, name, price) VALUES (?, ?, ?)");
            $stmt->execute([$data[0], $fullName, (int)$data[5]]);
            $productCount++;
        }
        fclose($handle);
        echo "<div class='success'>✅ Importováno $productCount produktů k prodeji.</div>";
    }

    // 4. Vytvoření Demo dat (Jana Ukázková)
    $stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, phone, allergy_note) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Jana', 'Ukázková', '+420 111 222 333', 'Alergie na PPD']);
    $client_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO visits (client_id, visit_date, hair_texture, hair_condition, note) VALUES (?, NOW(), ?, ?, ?)");
    $stmt->execute([$client_id, 'Silné / Porézní', 'Středně poškozené', 'Ukázková návštěva s recepturou.']);
    $visit_id = $pdo->lastInsertId();

    // Najdeme nějaký materiál pro recepturu
    $mat = $pdo->query("SELECT id FROM materials WHERE category = 'Inoa' LIMIT 1")->fetch();
    if ($mat) {
        $stmt = $pdo->prepare("INSERT INTO formulas (visit_id, material_id, amount_g, bowl_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$visit_id, $mat['id'], 30, 'Miska 1']);
    }
    echo "<div class='success'>✅ Demo klientka 'Jana Ukázková' byla vytvořena.</div>";

    echo "<h3>🎉 Vše je připraveno!</h3>";
    echo "<p>Nyní se můžete se přihlásit a začít pracovat.</p>";
    echo "<a href='login.php' class='btn btn-green'>PŘEJÍT K PŘIHLÁŠENÍ</a>";
    
    echo "<form method='POST' style='margin-top:40px; border-top:1px solid #e2e8f0; padding-top:20px;'>";
    echo "<p style='font-size:12px; color:#64748b;'>Z bezpečnostních důvodů je doporučeno tento instalátor smazat:</p>";
    echo "<button type='submit' name='delete_self' class='btn btn-red' onclick='return confirm(\"Opravdu smazat tento skript?\")'>Smazat install.php ze serveru</button>";
    echo "</form>";

} catch (Exception $e) {
    echo "<div style='color:#ef4444; font-weight:bold;'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
