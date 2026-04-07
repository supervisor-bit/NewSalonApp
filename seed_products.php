<?php require_once 'auth.php';

require_once 'db.php';

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

$file = resolveImportCsv(
    [$_GET['file'] ?? '', getenv('PRODUCTS_CSV') ?: '', 'produkty.csv', 'products.csv'],
    ['produkty*.csv', 'products*.csv']
);

if (!$file) {
    die("CSV soubor s produkty nebyl nalezen.");
}

$handle = fopen($file, 'r');
if ($handle !== FALSE) {
    fgetcsv($handle, 1000, ';');

    $imported = 0;
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        if (count($data) < 6) continue;

        $brand = resolveProductBrandLabel(trim((string)$data[0]), trim((string)$data[1]));
        $line = trim((string)$data[1]);
        $product = trim((string)$data[2]);
        $volume = trim((string)$data[3]);
        $type = trim((string)$data[4]);
        $price = (int)trim((string)$data[5]);

        $fullName = buildImportedProductName($brand, $line, $product, $volume, $type);

        $stmt = $pdo->prepare("INSERT INTO products (brand, name, price, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$brand, $fullName, $price]);
        $imported++;
    }
    fclose($handle);
    echo "Uspesne naimportovano $imported produktu ze souboru " . basename($file) . "!";
} else {
    echo "Nelze otevrit soubor.";
}
?>
