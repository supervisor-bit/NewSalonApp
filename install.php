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
    <title>Instalace | Aura</title>
    <style>
        body { font-family: 'Inter', sans-serif; padding: 40px; line-height: 1.6; color: #1e293b; background: #f8fafc; }
        .card { background: #white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; background: #fff; }
        h1 { color: #0f172a; font-size: 24px; margin-top: 0; }
        .warning { background: #fff1f2; border: 1px solid #fee2e2; color: #be123c; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .success { color: #10b981; font-weight: 600; margin: 5px 0; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #0f172a; color: #fff; text-decoration: none; border: none; border-radius: 8px; font-weight: 700; margin-top: 10px; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-green { background: #10b981; } .btn-green:hover { background: #059669; }
        .btn-secondary { background: #475569; } .btn-secondary:hover { background: #334155; }
        .btn-red { background: #ef4444; margin-top: 30px; font-size: 13px; } .btn-red:hover { background: #dc2626; }
    </style>
</head>
<body>
<div class='card'>";

echo "<h1>🛠️ Instalační skript salonu</h1>";

function resolveImportCsv(array $preferredNames, array $patterns): ?string {
    foreach ($preferredNames as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }

        $pathsToTry = [$candidate, __DIR__ . DIRECTORY_SEPARATOR . $candidate];
        foreach ($pathsToTry as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }
    }

    foreach ($patterns as $pattern) {
        $matches = glob(__DIR__ . DIRECTORY_SEPARATOR . $pattern) ?: [];
        sort($matches, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($matches as $match) {
            if (is_file($match) && is_readable($match)) {
                return $match;
            }
        }
    }

    return null;
}

function resolveProductBrandLabel(string $brand, string $line): string {
    $brand = trim($brand);
    $line = trim($line);
    $specialLines = ['Tecni.art', 'Hair Touch Up', 'Homme', 'Infinium', 'SteamPod'];

    foreach ($specialLines as $specialLine) {
        if ($line !== '' && mb_strtolower($line, 'UTF-8') === mb_strtolower($specialLine, 'UTF-8')) {
            return $specialLine;
        }
    }

    return $brand !== '' ? $brand : ($line !== '' ? $line : 'Produkt');
}

function buildImportedProductName(string $brandLabel, string $line, string $product, string $volume, string $type = ''): string {
    $line = trim($line);
    $product = trim($product);
    $volume = trim($volume);
    $type = trim($type);

    $name = $product;
    if ($line !== '' && mb_strtolower($brandLabel, 'UTF-8') !== mb_strtolower($line, 'UTF-8')) {
        $name = $line . ' - ' . $name;
    }
    if ($volume !== '') {
        $name .= ' (' . $volume . ')';
    }
    if ($type !== '' && mb_strtolower($type, 'UTF-8') !== 'retail') {
        $name .= ' · ' . $type;
    }

    return trim($name);
}

if (!isset($_GET['run'])) {
    echo "<div class='warning'>⚠️ VAROVÁNÍ: Spuštění tohoto skriptu SMAŽE veškerá současná data a nastaví čistou databázi!</div>";
    echo "<p>Výchozí instalace vytvoří čistý systém bez demo klienta. Číselníky materiálů a produktů se importují jen na vyžádání.</p>";
    echo "<div style='display:flex; gap:10px; flex-wrap:wrap;'>";
    echo "<a href='?run=1' class='btn'>JEN ČISTÁ INSTALACE</a>";
    echo "<a href='?run=1&catalog=1' class='btn btn-green'>INSTALACE + ČÍSELNÍKY</a>";
    echo "<a href='?run=1&catalog=1&demo=1' class='btn btn-secondary'>INSTALACE + ČÍSELNÍKY + DEMO</a>";
    echo "</div>";
    echo "<p style='margin-top:14px; font-size:13px; color:#64748b;'>Demo klient se vytvoří jen při parametru <code>demo=1</code>.</p>";
    echo "</div></body></html>";
    exit;
}

$shouldImportCatalogs = (($_GET['catalog'] ?? '0') === '1') || isset($_GET['materials_csv']) || isset($_GET['products_csv']);
$shouldCreateDemoClient = (($_GET['demo'] ?? '0') === '1');

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

    if ($shouldImportCatalogs) {
        // 2. Import barev
        $materialCount = 0;
        $materialsFile = resolveImportCsv(
            [$_GET['materials_csv'] ?? '', getenv('MATERIALS_CSV') ?: '', 'barvy.csv', 'materials.csv'],
            ['barvy*.csv', 'materials*.csv']
        );

        if ($materialsFile) {
            $handle = fopen($materialsFile, 'r');
            fgetcsv($handle, 0, ';');
            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                if (count($data) < 3) continue;
                $stmt = $pdo->prepare("INSERT INTO materials (brand, category, name) VALUES (?, ?, ?)");
                $stmt->execute([$data[0], $data[1], $data[2]]);
                $materialCount++;
            }
            fclose($handle);
            echo "<div class='success'>✅ Importováno $materialCount odstínů barev ze souboru <b>" . htmlspecialchars(basename($materialsFile)) . "</b>.</div>";
        } else {
            echo "<div class='warning'>⚠️ Soubor s materiály nebyl nalezen. Aplikace hledá např. <code>barvy.csv</code> nebo libovolné <code>barvy*.csv</code>.</div>";
        }

        // 3. Import produktů
        $productCount = 0;
        $productsFile = resolveImportCsv(
            [$_GET['products_csv'] ?? '', getenv('PRODUCTS_CSV') ?: '', 'produkty.csv', 'products.csv'],
            ['produkty*.csv', 'products*.csv']
        );

        if ($productsFile) {
            $handle = fopen($productsFile, 'r');
            fgetcsv($handle, 0, ';');
            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                if (count($data) < 6) continue;
                $brandLabel = resolveProductBrandLabel((string)($data[0] ?? ''), (string)($data[1] ?? ''));
                $fullName = buildImportedProductName(
                    $brandLabel,
                    (string)($data[1] ?? ''),
                    (string)($data[2] ?? ''),
                    (string)($data[3] ?? ''),
                    (string)($data[4] ?? '')
                );
                $stmt = $pdo->prepare("INSERT INTO products (brand, name, price) VALUES (?, ?, ?)");
                $stmt->execute([$brandLabel, $fullName, (int)$data[5]]);
                $productCount++;
            }
            fclose($handle);
            echo "<div class='success'>✅ Importováno $productCount produktů k prodeji ze souboru <b>" . htmlspecialchars(basename($productsFile)) . "</b>.</div>";
        } else {
            echo "<div class='warning'>⚠️ Soubor s produkty nebyl nalezen. Aplikace hledá např. <code>produkty.csv</code> nebo libovolné <code>produkty*.csv</code>.</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ Číselníky se při této instalaci neimportovaly. Pokud je chcete nahrát, spusťte <code>install.php?run=1&catalog=1</code>.</div>";
    }

    if ($shouldCreateDemoClient) {
        // 4. Vytvoření demo dat (Jana Ukázková)
        $stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, phone, allergy_note) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Jana', 'Ukázková', '+420 111 222 333', 'Alergie na PPD']);
        $client_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO visits (client_id, visit_date, hair_texture, hair_condition, note) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([$client_id, 'Silné / Porézní', 'Středně poškozené', 'Ukázková návštěva s recepturou.']);
        $visit_id = $pdo->lastInsertId();

        $mat = $pdo->query("SELECT id FROM materials WHERE category = 'Inoa' LIMIT 1")->fetch();
        if ($mat) {
            $stmt = $pdo->prepare("INSERT INTO formulas (visit_id, material_id, amount_g, bowl_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$visit_id, $mat['id'], 30, 'Miska 1']);
        }
        echo "<div class='success'>✅ Demo záznam klienta 'Jana Ukázková' byl vytvořen.</div>";
    } else {
        echo "<div class='info'>ℹ️ Demo klient se při této instalaci nevytváří.</div>";
    }

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
