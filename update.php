<?php require_once 'auth.php';
 
// update.php
// Skript pro přidání nových sloupců (cena, alergie) do již existující databáze.

if (!file_exists('db.php')) {
    die("Aplikace ještě není nainstalovaná, spusťte setup.php");
}
require 'db.php';

try {
    // Přidání sloupce price do visits
    try {
        $pdo->exec("ALTER TABLE visits ADD COLUMN price INT DEFAULT 0");
    } catch (PDOException $e) {
        // Ignorujeme chybu, pokud sloupec už existuje
    }

    // Přidání sloupce allergy_note do clients
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN allergy_note VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignorujeme chybu, pokud sloupec už existuje
    }

    // Pro nováčky také upravíme schema.sql pro budoucí instalace.
    
    echo "<h3>Aktualizace databáze proběhla úspěšně!</h3>";
    echo "<p>Byly přidány sloupce pro cenu návštěvy a poznámky k alergiím.</p>";
    echo "<a href='index.php'>Zpět do aplikace</a>";

} catch (Exception $e) {
    die("Chyba aktualizace: " . $e->getMessage());
}
?>
