<?php require_once 'auth.php';

require_once 'db.php';

$csvData = <<<EOT
Značka;Řada;Produkt;Balení;Typ
L'Oréal;Absolut Repair;Maska;500 ml;Salon
L'Oréal;Absolut Repair;Maska;250 ml;Retail
L'Oréal;Absolut Repair;Maska zlatá;250 ml;Retail
L'Oréal;Absolut Repair;Olej;90 ml;Retail
L'Oréal;Absolut Repair;Pěna;200 ml;Retail
L'Oréal;Absolut Repair;Šampon;1500 ml;Salon
L'Oréal;Absolut Repair;Šampon;500 ml;Retail
L'Oréal;Absolut Repair;Šampon;300 ml;Retail
L'Oréal;Absolut Repair Molecular;Bezoplachová maska;100 ml;Retail
L'Oréal;Absolut Repair Molecular;Discovery Box Mini Duo Kit;1 set;Retail
L'Oréal;Absolut Repair Molecular;Discovery Box Mini Trio Kit;1 set;Retail
L'Oréal;Absolut Repair Molecular;Dvoufázový olej;90 ml;Retail
L'Oréal;Absolut Repair Molecular;Maska;500 ml;Salon
L'Oréal;Absolut Repair Molecular;Maska;250 ml;Retail
L'Oréal;Absolut Repair Molecular;Přípravná péče;190 ml;Salon
L'Oréal;Absolut Repair Molecular;Sérum;250 ml;Retail
L'Oréal;Absolut Repair Molecular;Šampon;1500 ml;Salon
L'Oréal;Absolut Repair Molecular;Šampon;500 ml;Retail
L'Oréal;Absolut Repair Molecular;Šampon;300 ml;Retail
L'Oréal;Aminexil Advanced;Aminexil kúra;10x6 ml;Retail
L'Oréal;Aminexil Advanced;Aminexil kúra;42x6 ml;Retail
L'Oréal;Aminexil Advanced;Anti Hair Loss Activator;90 ml;Retail
L'Oréal;Blondifier;Cool péče;200 ml;Retail
L'Oréal;Blondifier;Gloss šampon;1500 ml;Salon
L'Oréal;Blondifier;Gloss šampon;500 ml;Retail
L'Oréal;Blondifier;Gloss šampon;300 ml;Retail
L'Oréal;Blondifier;Maska;500 ml;Salon
L'Oréal;Blondifier;Maska;250 ml;Retail
L'Oréal;Blondifier;Pěna;200 ml;Retail
L'Oréal;Curl Expression;Curls reviver;190 ml;Retail
L'Oréal;Curl Expression;Definition activator;250 ml;Retail
L'Oréal;Curl Expression;Long lasting moisturizer;200 ml;Retail
L'Oréal;Curl Expression;Maska;500 ml;Salon
L'Oréal;Curl Expression;Maska;250 ml;Retail
L'Oréal;Curl Expression;Pěna 10 v 1;250 ml;Retail
L'Oréal;Curl Expression;Šampon;1500 ml;Salon
L'Oréal;Curl Expression;Šampon;500 ml;Retail
L'Oréal;Curl Expression;Šampon;300 ml;Retail
L'Oréal;Liss Unlimited;Maska;500 ml;Salon
L'Oréal;Liss Unlimited;Maska;250 ml;Retail
L'Oréal;Liss Unlimited;Šampon;1500 ml;Salon
L'Oréal;Liss Unlimited;Šampon;500 ml;Retail
L'Oréal;Liss Unlimited;Šampon;300 ml;Retail
L'Oréal;Liss Unlimited;Sérum;125 ml;Retail
L'Oréal;Metal Detox;Discovery Box Mini Duo Kit;1 set;Retail
L'Oréal;Metal Detox;Discovery Box Mini Trio Kit;1 set;Retail
L'Oréal;Metal Detox;Krém;100 ml;Retail
L'Oréal;Metal Detox;Maska;500 ml;Salon
L'Oréal;Metal Detox;Maska;250 ml;Retail
L'Oréal;Metal Detox;Olej;50 ml;Retail
L'Oréal;Metal Detox;Pěna;500 ml;Salon
L'Oréal;Metal Detox;Přípravná péče;500 ml;Salon
L'Oréal;Metal Detox;Šampon;1500 ml;Salon
L'Oréal;Metal Detox;Šampon;500 ml;Retail
L'Oréal;Metal Detox;Šampon;300 ml;Retail
L'Oréal;Pro Longer;Krém;150 ml;Retail
L'Oréal;Pro Longer;Maska;500 ml;Salon
L'Oréal;Pro Longer;Maska;250 ml;Retail
L'Oréal;Pro Longer;Péče;750 ml;Salon
L'Oréal;Pro Longer;Péče;500 ml;Retail
L'Oréal;Pro Longer;Péče;200 ml;Retail
L'Oréal;Pro Longer;Šampon;1500 ml;Salon
L'Oréal;Pro Longer;Šampon;750 ml;Salon
L'Oréal;Pro Longer;Šampon;500 ml;Retail
L'Oréal;Pro Longer;Šampon;300 ml;Retail
L'Oréal;Scalp Advanced;Dermo Regulator šampon;1500 ml;Salon
L'Oréal;Scalp Advanced;Dermo Regulator šampon;500 ml;Retail
L'Oréal;Scalp Advanced;Dermo Regulator šampon;300 ml;Retail
L'Oréal;Scalp Advanced;Dermo Purifier šampon;1500 ml;Salon
L'Oréal;Scalp Advanced;Dermo Purifier šampon;500 ml;Retail
L'Oréal;Scalp Advanced;Dermo Purifier šampon;300 ml;Retail
L'Oréal;Scalp Advanced;Dermo Clarifier šampon;1500 ml;Salon
L'Oréal;Scalp Advanced;Dermo Clarifier šampon;500 ml;Retail
L'Oréal;Scalp Advanced;Dermo Clarifier šampon;300 ml;Retail
L'Oréal;Scalp Advanced;Intense Soother;200 ml;Retail
L'Oréal;Scalp Advanced;2 in 1 Clay;500 ml;Salon
L'Oréal;Scalp Advanced;2 in 1 Clay;250 ml;Retail
L'Oréal;Serioxyl Advanced;Bodyfying šampon;1500 ml;Salon
L'Oréal;Serioxyl Advanced;Bodyfying šampon;500 ml;Retail
L'Oréal;Serioxyl Advanced;Bodyfying šampon;300 ml;Retail
L'Oréal;Serioxyl Advanced;Density Activator;90 ml;Retail
L'Oréal;Silver;Péče;200 ml;Retail
L'Oréal;Silver;Šampon;1500 ml;Salon
L'Oréal;Silver;Šampon;500 ml;Retail
L'Oréal;Silver;Šampon;300 ml;Retail
L'Oréal;Vitamino Color;Acidic Sealer;400 ml;Salon
L'Oréal;Vitamino Color;Maska;500 ml;Salon
L'Oréal;Vitamino Color;Maska;250 ml;Retail
L'Oréal;Vitamino Color;Péče;1000 ml;Salon
L'Oréal;Vitamino Color;Péče;750 ml;Salon
L'Oréal;Vitamino Color;Péče;500 ml;Retail
L'Oréal;Vitamino Color;Péče;200 ml;Retail
L'Oréal;Vitamino Color;Šampon;1500 ml;Salon
L'Oréal;Vitamino Color;Šampon;750 ml;Salon
L'Oréal;Vitamino Color;Šampon;500 ml;Retail
L'Oréal;Vitamino Color;Šampon;300 ml;Retail
L'Oréal;Vitamino Color;Sprej 10 v 1;190 ml;Retail
L'Oréal;Vitamino Color Spectrum;Bezoplachové sérum;50 ml;Retail
L'Oréal;Vitamino Color Spectrum;Intenzivní péče;750 ml;Salon
L'Oréal;Vitamino Color Spectrum;Intenzivní péče;200 ml;Retail
L'Oréal;Vitamino Color Spectrum;Maska;500 ml;Salon
L'Oréal;Vitamino Color Spectrum;Maska;250 ml;Retail
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon fialový;1500 ml;Salon
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon fialový;500 ml;Retail
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon modrý;1500 ml;Salon
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon modrý;500 ml;Retail
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon zelený;500 ml;Retail
L'Oréal;Vitamino Color Spectrum;Neutraliz. šampon zelený;300 ml;Retail
L'Oréal;Vitamino Color Spectrum;Šampon;1500 ml;Salon
L'Oréal;Vitamino Color Spectrum;Šampon;500 ml;Retail
L'Oréal;Vitamino Color Spectrum;Šampon;300 ml;Retail
L'Oréal;Air Light Pro;Fén Air Light Pro;1 ks;Elektro
L'Oréal;SteamPod;SteamPod 3.0;1 ks;Elektro
L'Oréal;SteamPod;SteamPod 4.0;1 ks;Elektro
L'Oréal;SteamPod;SteamPod 4 Meteora;1 ks;Elektro
L'Oréal;SteamPod;Profesionální vyhlazující péče;50 ml;Retail
L'Oréal;Hair Touch Up;Černá 1-3;75 ml;Sprej
L'Oréal;Hair Touch Up;Hnědá 3-5;75 ml;Sprej
L'Oréal;Hair Touch Up;Mahagon 5-6;75 ml;Sprej
L'Oréal;Hair Touch Up;Světle hnědá 5-6;75 ml;Sprej
L'Oréal;Hair Touch Up;Tmavá blond 6-7;75 ml;Sprej
L'Oréal;Hair Touch Up;Zlatá blond 7-9;75 ml;Sprej
L'Oréal;Homme;Clay;50 ml;Styling
L'Oréal;Infinium;Infinium Pure Extra Strong;300 ml;Lak
L'Oréal;Infinium;Infinium Pure Soft;300 ml;Lak
L'Oréal;Infinium;Infinium Pure Strong;300 ml;Lak
L'Oréal;Tecni.art;All-in-1 Performer;190 ml;Styling
L'Oréal;Tecni.art;Anti Frizz;250 ml;Lak
L'Oréal;Tecni.art;Fix Design;1000 ml;Salon
L'Oréal;Tecni.art;Fix Design;200 ml;Retail
L'Oréal;Tecni.art;Fix Max;200 ml;Gel
L'Oréal;Tecni.art;Fix Paste;75 ml;Pasta
L'Oréal;Tecni.art;Flex Blowdry;150 ml;Styling
L'Oréal;Tecni.art;Flex Curl Bounce;150 ml;Styling
L'Oréal;Tecni.art;Flex Depolish;100 ml;Pasta
L'Oréal;Tecni.art;Flex Liss Control;150 ml;Styling
L'Oréal;Tecni.art;Flex Pli;190 ml;Styling
L'Oréal;Tecni.art;Flex Waves;150 ml;Styling
L'Oréal;Tecni.art;Flex Web;190 ml;Styling
L'Oréal;Tecni.art;Volume Extra Full;250 ml;Pěna
L'Oréal;Tecni.art;Volume Dust;7 g;Pudr
L'Oréal;Tecni.art;Volume Panache;250 ml;Styling
L'Oréal;Tecni.art;Volume Rootlift;250 ml;Pěna
EOT;

// Vyčistíme tabulku products a přidružené visit_products
$pdo->query("DELETE FROM products");
$pdo->query("ALTER TABLE products AUTO_INCREMENT = 1");

$lines = explode("\n", $csvData);
$imported = 0;
$skipped = 0;

foreach ($lines as $index => $line) {
    if ($index === 0) continue; // Hlavička
    $line = trim($line);
    if (!$line) continue;
    
    $data = explode(";", $line);
    if (count($data) < 5) continue;
    
    $brand = trim($data[0]);
    $rada = trim($data[1]);
    $product = trim($data[2]);
    $volume = trim($data[3]);
    $type = trim($data[4]);
    
    // APLIKACE PRAVIDEL DLE OBRÁZKU A LOGIKY:
    
    // 1. Co je "NÁPLŇ" pro prodej zákazníkům (většinou 500ml a 1000ml šampony):
    if ($type === 'Salon') {
        $isNapln = false;
        
        if (stripos($product, 'Šampon') !== false && in_array($volume, ['500 ml', '1000 ml'])) {
            $isNapln = true;
        }
        
        if ($rada === 'Vitamino Color' && $product === 'Péče' && $volume === '500 ml') {
            $isNapln = true;
        }
        
        if ($isNapln) {
            $type = 'Retail (Náplň)';
        }
    }
    
    // 2. Pokud je to stále 'Salon' i po výjimkách (NÁPLŇ), NEBUDEME ho importovat,
    // protože 1500ml šampony a 500ml masky atd. jsou čistě interní materiál k mycímu / barvícímu boxu.
    if ($type === 'Salon') {
        $skipped++;
        continue;
    }
    
    $fullName = "$rada $product $volume";
    if ($type && $type !== 'Retail') {
        $fullName .= " ($type)";
    }
    
    $stmt = $pdo->prepare("INSERT INTO products (brand, name, price, is_active) VALUES (?, ?, 0, 1)");
    $stmt->execute([$brand, $fullName]);
    $imported++;
}

echo "Uspesne naimportovano $imported Retail/Napln produktu!\n";
echo "Preskoceno $skipped ciste Salon produktů.\n";
?>
