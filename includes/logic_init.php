<?php require_once 'auth.php';
 
// index.php


$setup_needed = false;
if (!file_exists('db.php')) {
    $setup_needed = true;
} else {
    include 'db.php';
    if (!isset($pdo)) {
        $setup_needed = true;
    }
}

// Chytáme zprávy do UI
$zprava = "";
if (isset($_SESSION['msg'])) {
    $zprava = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$range = isset($_GET['range']) ? $_GET['range'] : null;
$view = isset($_GET['view']) ? $_GET['view'] : null;

// MUTUALLY EXCLUSIVE VIEWS
$show_settings = ($view === 'settings');
$show_accounting = ($view === 'accounting');
$show_sales = ($view === 'sales');
$show_stats = ($view === null && $range !== null && $client_id === 0);
$show_client_karta = ($view === null && !$show_stats && !$show_settings && !$show_accounting && !$show_sales);

// Logika pro aktualizaci profilu (přesunuto z settings.php)
if (!$setup_needed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $current_username = $_SESSION['username'] ?? 'admin';
    $new_user = trim($_POST['username'] ?? '');
    $old_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$current_username]);
    $user_row = $stmt->fetch();

    if ($user_row && password_verify($old_pass, $user_row['password_hash'])) {
        if (!empty($new_user) && $new_user !== $current_username) {
            $update = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $update->execute([$new_user, $user_row['id']]);
            $_SESSION['username'] = $new_user;
            $_SESSION['msg'] = "Uživatelské jméno bylo změněno.";
        }
        if (!empty($new_pass)) {
            if ($new_pass === $confirm_pass) {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$hash, $user_row['id']]);
                $_SESSION['msg'] = ($_SESSION['msg'] ?? "") . " Heslo bylo úspěšně aktualizováno!";
            } else {
                $_SESSION['msg'] = "CHYBA: Nová hesla se neshodují.";
            }
        }
    }
    $active_tab = $_POST['active_tab'] ?? 'profile';
    header("Location: index.php?view=settings&tab=$active_tab");
    exit;
}

// Chytání aktivní záložky pro nastavení
$active_settings_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

$active_client = null;
$visits = [];
$all_materials = [];
$materials = [];
$clients = [];
$total_visits = 0;
$total_spent = 0;

// Client Analytics
$vip_status = false;
$avg_interval = null;
$today_direct_sales_sum = 0;
$today_direct_sales_count = 0;
$today_direct_sales_list = [];
$stats_total_prod_clients = 0;
$stats_total_prod_direct = 0;
$m_direct_now = 0;
$top_home_products = [];
$shopping_total_qty = 0;
$opened_materials = [];
$low_materials = [];
$opened_materials_count = 0;
$low_materials_count = 0;
$recent_receipts = [];

if (!$setup_needed) {
    $clientOrderBy = 'first_name ASC';
    $has_direct_sales = false;

    try {
        $clientCol = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_active'")->fetch();
        if (!$clientCol) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }

        $tagCol = $pdo->query("SHOW COLUMNS FROM clients LIKE 'client_tags'")->fetch();
        if (!$tagCol) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN client_tags VARCHAR(255) DEFAULT NULL");
        }

        $favoriteCol = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_favorite'")->fetch();
        if (!$favoriteCol) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN is_favorite TINYINT(1) DEFAULT 0");
        }

        $materialColumns = $pdo->query("SHOW COLUMNS FROM materials")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('needs_buying', $materialColumns, true)) {
            $pdo->exec("ALTER TABLE materials ADD COLUMN needs_buying TINYINT(1) DEFAULT 0");
        }
        if (!in_array('shopping_qty', $materialColumns, true)) {
            $pdo->exec("ALTER TABLE materials ADD COLUMN shopping_qty INT NOT NULL DEFAULT 1");
        }
        if (!in_array('stock_state', $materialColumns, true)) {
            $pdo->exec("ALTER TABLE materials ADD COLUMN stock_state VARCHAR(20) NOT NULL DEFAULT 'none'");
        }
        if (!in_array('ean', $materialColumns, true)) {
            $pdo->exec("ALTER TABLE materials ADD COLUMN ean VARCHAR(64) DEFAULT NULL");
        }
        $pdo->exec("UPDATE materials SET shopping_qty = 1 WHERE shopping_qty IS NULL OR shopping_qty < 1");
        $pdo->exec("UPDATE materials SET stock_state = 'none' WHERE stock_state IS NULL OR stock_state = '' OR stock_state NOT IN ('none', 'opened', 'low')");

        $productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('ean', $productColumns, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN ean VARCHAR(64) DEFAULT NULL");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS direct_sales (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price INT NOT NULL DEFAULT 0,
            sold_at DATE NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_receipts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            batch_code VARCHAR(64) DEFAULT NULL,
            item_type VARCHAR(20) NOT NULL,
            item_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            scanned_ean VARCHAR(64) DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receipts_batch_code (batch_code),
            INDEX idx_receipts_type_item (item_type, item_id),
            INDEX idx_receipts_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $receiptColumns = $pdo->query("SHOW COLUMNS FROM stock_receipts")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('batch_code', $receiptColumns, true)) {
            $pdo->exec("ALTER TABLE stock_receipts ADD COLUMN batch_code VARCHAR(64) DEFAULT NULL AFTER id");
        }
        $has_direct_sales = true;

        $clientOrderBy = 'COALESCE(c.is_favorite, 0) DESC, first_name ASC';
    } catch (Throwable $e) {
        try {
            $has_direct_sales = (bool)$pdo->query("SHOW TABLES LIKE 'direct_sales'")->fetch();
        } catch (Throwable $inner) {
            $has_direct_sales = false;
        }
        // Když se sloupec nepodaří přidat, aplikace poběží dál v původním režimu.
    }

    // 1. Stáhnutí všech klientek pro levý panel (včetně použitých materiálů a dat návštěv pro hledání)
    $c_stmt = $pdo->query("
        SELECT c.*, 
        (SELECT MAX(visit_date) FROM visits WHERE client_id = c.id) as last_visit_date,
        (SELECT GROUP_CONCAT(DISTINCT m.name SEPARATOR ' ') 
         FROM visits v 
         JOIN formulas f ON v.id = f.visit_id 
         JOIN materials m ON f.material_id = m.id 
         WHERE v.client_id = c.id) as materials_used,
        (SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(visit_date, '%d.%m.%Y') SEPARATOR ' ') 
         FROM visits WHERE client_id = c.id) as visit_dates
        FROM clients c 
        ORDER BY $clientOrderBy
    ");
    $clients = $c_stmt->fetchAll();

    $parse_bowl_meta = static function ($stored_name) {
        $stored_name = trim((string)$stored_name);
        if ($stored_name === '') {
            return ['name' => 'Miska 1', 'ratio' => ''];
        }
        if (strpos($stored_name, '||') !== false) {
            [$name, $ratio] = array_map('trim', explode('||', $stored_name, 2));
            return [
                'name' => $name !== '' ? $name : 'Miska 1',
                'ratio' => $ratio
            ];
        }
        return ['name' => $stored_name, 'ratio' => ''];
    };

    // 2. Aktivní klient a jeho návštěvy
    if ($client_id > 0) {
        $ac_stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $ac_stmt->execute([$client_id]);
        $active_client = $ac_stmt->fetch();

        if ($active_client) {
            $v_stmt = $pdo->prepare("SELECT * FROM visits WHERE client_id = ? ORDER BY visit_date DESC, id DESC");
            $v_stmt->execute([$client_id]);
            $visits = $v_stmt->fetchAll();
            
            $total_visits = count($visits);
            $total_spent_work = 0;
            $total_spent_products = 0;
            $total_products_count = 0;

            // Average interval calculation
            if ($total_visits > 1) {
                $intervals = [];
                for ($i = 0; $i < $total_visits - 1; $i++) {
                    $d1 = strtotime($visits[$i]['visit_date']);
                    $d2 = strtotime($visits[$i+1]['visit_date']);
                    $diff = abs($d1 - $d2) / 86400; // days
                    if ($diff > 0) {
                        $intervals[] = $diff;
                    }
                }
                if (count($intervals) > 0) {
                    $avg_interval = array_sum($intervals) / count($intervals);
                }
            }

            foreach ($visits as $k => $v) {
                if(isset($v['price'])) { 
                    $total_spent_work += (int)$v['price'];
                }
                
                // Vytazeni receptur
                $f_stmt = $pdo->prepare("
                    SELECT f.material_id, f.amount_g, f.bowl_name, m.category, m.name 
                    FROM formulas f
                    LEFT JOIN materials m ON f.material_id = m.id
                    WHERE f.visit_id = ?
                ");
                $f_stmt->execute([$v['id']]);
                $formulas = $f_stmt->fetchAll();
                foreach ($formulas as &$formula_row) {
                    $bowl_meta = $parse_bowl_meta($formula_row['bowl_name'] ?? '');
                    $formula_row['bowl_name'] = $bowl_meta['name'];
                    $formula_row['mix_ratio'] = $bowl_meta['ratio'];
                }
                unset($formula_row);
                $visits[$k]['formulas'] = $formulas;
                
                $array_for_json = [];
                foreach ($formulas as $row) {
                    $bName = $row['bowl_name'] ?: 'Miska 1';
                    if (!isset($array_for_json[$bName])) {
                        $array_for_json[$bName] = [
                            'ratio' => (string)($row['mix_ratio'] ?? ''),
                            'items' => []
                        ];
                    }
                    if ($array_for_json[$bName]['ratio'] === '' && !empty($row['mix_ratio'])) {
                        $array_for_json[$bName]['ratio'] = (string)$row['mix_ratio'];
                    }
                    $array_for_json[$bName]['items'][] = [ 'mat_id' => $row['material_id'], 'g' => $row['amount_g'] ];
                }
                $visits[$k]['formulas_json'] = json_encode($array_for_json);
                
                // Services JSON for copying
                $services_json = [
                    's_metal_detox' => (int)($v['s_metal_detox'] ?? 0),
                    's_trim'        => (int)($v['s_trim'] ?? 0),
                    's_blow'        => (int)($v['s_blow'] ?? 0),
                    's_curl'        => (int)($v['s_curl'] ?? 0),
                    's_iron'        => (int)($v['s_iron'] ?? 0)
                ];
                $visits[$k]['services_json'] = json_encode($services_json);
                
                // Vytazeni produktu (Homecare)
                $p_stmt = $pdo->prepare("
                    SELECT vp.product_id, vp.price_sold, vp.amount, p.brand, p.name 
                    FROM visit_products vp
                    JOIN products p ON vp.product_id = p.id
                    WHERE vp.visit_id = ?
                ");
                $p_stmt->execute([$v['id']]);
                $visit_products = $p_stmt->fetchAll();
                $visits[$k]['products'] = $visit_products;
                $visits[$k]['products_json'] = json_encode($visit_products);
                
                foreach ($visit_products as $vp) {
                    $item_total = ($vp['price_sold'] * $vp['amount']);
                    $total_spent_products += $item_total;
                    $total_products_count += $vp['amount'];
                }
            }
            
            $total_spent = $total_spent_work + $total_spent_products;

            // VIP Status calculation (Top 10% spenders)
            $all_clients_totals_stmt = $pdo->query("
                SELECT c.id, 
                (IFNULL((SELECT SUM(price) FROM visits WHERE client_id = c.id), 0) + 
                 IFNULL((SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE v.client_id = c.id), 0)) as total 
                FROM clients c 
                ORDER BY total DESC
            ");
            $all_totals = $all_clients_totals_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($all_totals)) {
                $threshold_idx = max(0, floor(count($all_totals) * 0.1) - 1);
                $threshold = $all_totals[$threshold_idx];
                if ($total_spent >= $threshold) {
                    $vip_status = true;
                }
            }
        }
    }

    // 3. Stáhnutí číselníku pro selekty s prioritou podle používání
    $m_stmt = $pdo->query("
        SELECT m.id, m.brand, m.category, m.name, m.is_active, m.needs_buying, COALESCE(m.shopping_qty, 1) AS shopping_qty,
        COALESCE(NULLIF(m.stock_state, ''), 'none') AS stock_state,
        COALESCE(NULLIF(m.ean, ''), '') AS ean,
        (SELECT COUNT(*) FROM formulas f WHERE f.material_id = m.id) as use_count
        FROM materials m 
        ORDER BY use_count DESC, m.category, m.name
    ");
    $all_materials = $m_stmt->fetchAll();
    
    // Pro formuláře návštěvy chceme jen aktivní
    $materials = array_filter($all_materials, function($m) { return $m['is_active'] == 1; });
    
    // 4. Seznam k nákupu (Hlídač)
    $shop_stmt = $pdo->query("SELECT id, brand, category, name, COALESCE(shopping_qty, 1) AS shopping_qty, COALESCE(NULLIF(stock_state, ''), 'none') AS stock_state FROM materials WHERE needs_buying = 1 OR COALESCE(NULLIF(stock_state, ''), 'none') = 'low' ORDER BY brand, category, name");
    $shopping_list = $shop_stmt->fetchAll();
    $shopping_total_qty = array_sum(array_map(static function ($item) {
        return max(1, (int)($item['shopping_qty'] ?? 1));
    }, $shopping_list));

    $opened_stmt = $pdo->query("SELECT id, brand, category, name FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') = 'opened' ORDER BY brand, category, name LIMIT 8");
    $opened_materials = $opened_stmt->fetchAll();
    $opened_materials_count = (int)($pdo->query("SELECT COUNT(*) FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') = 'opened'")->fetchColumn() ?: 0);

    $low_stmt = $pdo->query("SELECT id, brand, category, name FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') = 'low' ORDER BY brand, category, name LIMIT 8");
    $low_materials = $low_stmt->fetchAll();
    $low_materials_count = (int)($pdo->query("SELECT COUNT(*) FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') = 'low'")->fetchColumn() ?: 0);

    try {
        $recent_receipts_stmt = $pdo->query(" 
            SELECT sr.id, sr.batch_code, sr.item_type, sr.item_id, sr.quantity, sr.scanned_ean, sr.note,
                   DATE_FORMAT(sr.received_at, '%Y-%m-%d %H:%i:%s') AS received_at,
                   CASE 
                        WHEN sr.item_type = 'material' THEN TRIM(CONCAT(COALESCE(m.brand, ''), ' ', COALESCE(m.category, ''), ' ', COALESCE(m.name, '')))
                        ELSE TRIM(CONCAT(COALESCE(p.brand, ''), ' ', COALESCE(p.name, '')))
                   END AS item_label
            FROM stock_receipts sr
            LEFT JOIN materials m ON sr.item_type = 'material' AND sr.item_id = m.id
            LEFT JOIN products p ON sr.item_type = 'product' AND sr.item_id = p.id
            ORDER BY sr.received_at DESC, sr.id DESC
            LIMIT 8
        ");
        $recent_receipts = $recent_receipts_stmt->fetchAll();
    } catch (Throwable $e) {
        $recent_receipts = [];
    }
    
    // 4. Stahnuti produktu na doma s prioritou podle prodejů
    $direct_sales_usage_sql = $has_direct_sales
        ? "COALESCE((SELECT SUM(ds.quantity) FROM direct_sales ds WHERE ds.product_id = p.id), 0)"
        : "0";

    $pr_stmt = $pdo->query("
        SELECT p.id, p.brand, p.name, p.price, p.is_active, COALESCE(NULLIF(p.ean, ''), '') AS ean,
        (
            COALESCE((SELECT SUM(vp.amount) FROM visit_products vp WHERE vp.product_id = p.id), 0)
            +
            $direct_sales_usage_sql
        ) as use_count
        FROM products p 
        ORDER BY use_count DESC, p.brand, p.name
    ");
    $all_products = $pr_stmt->fetchAll();
    $active_products = array_filter($all_products, function($p) { return $p['is_active'] == 1; });

    // 5. Salon-wide Statistics (Dashboard)
    $stats_total_work = 0;
    $stats_total_prod = 0;
    $stats_total_prod_clients = 0;
    $stats_total_prod_direct = 0;
    $stats_visit_count = 0;
    
    $date_clause = " WHERE 1=1 ";
    $direct_date_clause = " WHERE 1=1 ";
    if ($range === 'today') {
        $date_clause = " WHERE visit_date = CURDATE() ";
        $direct_date_clause = " WHERE sold_at = CURDATE() ";
    }
    if ($range === 'this_month') {
        $date_clause = " WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE()) ";
        $direct_date_clause = " WHERE MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE()) ";
    }
    if ($range === 'this_year') {
        $date_clause = " WHERE YEAR(visit_date) = YEAR(CURDATE()) ";
        $direct_date_clause = " WHERE YEAR(sold_at) = YEAR(CURDATE()) ";
    }

    // TOP produkty na doma (návštěvy + rychlý prodej)
    $top_home_products_stmt = $pdo->query(" 
        SELECT p.id, p.brand, p.name,
            (
                COALESCE((SELECT SUM(vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE vp.product_id = p.id" . str_replace('WHERE', ' AND ', $date_clause) . "), 0)
                +
                " . ($has_direct_sales
                    ? "COALESCE((SELECT SUM(ds.quantity) FROM direct_sales ds WHERE ds.product_id = p.id" . str_replace('WHERE', ' AND ', $direct_date_clause) . "), 0)"
                    : "0") . "
            ) AS total_qty,
            (
                COALESCE((SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE vp.product_id = p.id" . str_replace('WHERE', ' AND ', $date_clause) . "), 0)
                +
                " . ($has_direct_sales
                    ? "COALESCE((SELECT SUM(ds.unit_price * ds.quantity) FROM direct_sales ds WHERE ds.product_id = p.id" . str_replace('WHERE', ' AND ', $direct_date_clause) . "), 0)"
                    : "0") . "
            ) AS total_rev
        FROM products p
        WHERE p.is_active = 1
        HAVING total_qty > 0
        ORDER BY total_rev DESC, total_qty DESC, p.brand ASC, p.name ASC
        LIMIT 5
    ");
    $top_home_products = $top_home_products_stmt->fetchAll();

    $s_stmt = $pdo->query("SELECT SUM(price) as sw FROM visits" . $date_clause);
    $stats_total_work = (int)$s_stmt->fetchColumn();
    
    $p_stmt = $pdo->query("SELECT SUM(vp.price_sold * vp.amount) as sp FROM visit_products vp JOIN visits v ON vp.visit_id = v.id" . str_replace('WHERE', 'AND', $date_clause));
    $stats_total_prod_clients = (int)$p_stmt->fetchColumn();

    if ($has_direct_sales) {
        $dp_stmt = $pdo->query("SELECT SUM(unit_price * quantity) as sp FROM direct_sales" . $direct_date_clause);
        $stats_total_prod_direct = (int)$dp_stmt->fetchColumn();
    }
    $stats_total_prod = $stats_total_prod_clients + $stats_total_prod_direct;
    
    $v_stmt = $pdo->query("SELECT COUNT(*) FROM visits" . $date_clause);
    $stats_visit_count = (int)$v_stmt->fetchColumn();

    // Monthly breakdown
    $m_stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(visit_date, '%Y-%m') as ym, 
            SUM(price) as work_rev,
            COUNT(*) as v_count
        FROM visits 
        GROUP BY ym 
        ORDER BY ym DESC 
        LIMIT 12
    ");
    $monthly_stats = $m_stmt->fetchAll();
    
    // Add product revenue to monthly stats
    foreach($monthly_stats as $k => $ms) {
        $mp_stmt = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE DATE_FORMAT(v.visit_date, '%Y-%m') = ?");
        $mp_stmt->execute([$ms['ym']]);
        $monthly_visit_products = (int)$mp_stmt->fetchColumn();
        $monthly_direct_products = 0;

        if ($has_direct_sales) {
            $mdp_stmt = $pdo->prepare("SELECT SUM(unit_price * quantity) FROM direct_sales WHERE DATE_FORMAT(sold_at, '%Y-%m') = ?");
            $mdp_stmt->execute([$ms['ym']]);
            $monthly_direct_products = (int)$mdp_stmt->fetchColumn();
        }

        $monthly_stats[$k]['prod_rev'] = $monthly_visit_products + $monthly_direct_products;
    }

    // TOP 5 Clients
    $top_stmt = $pdo->query("
        SELECT c.id, c.first_name, c.last_name, SUM(v.price) as work_sum
        FROM clients c
        JOIN visits v ON c.id = v.client_id
        GROUP BY c.id
        ORDER BY work_sum DESC
        LIMIT 5
    ");
    $top_clients = $top_stmt->fetchAll();
    foreach($top_clients as $k => $tc) {
        $tcp_stmt = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE v.client_id = ?");
        $tcp_stmt->execute([$tc['id']]);
        $top_clients[$k]['prod_sum'] = (int)$tcp_stmt->fetchColumn();
    }

    // TOP 5 Materials by consumption
    $mat_stmt = $pdo->query("
        SELECT m.name, m.category, SUM(f.amount_g) as total_g 
        FROM formulas f 
        JOIN materials m ON f.material_id = m.id 
        JOIN visits v ON f.visit_id = v.id 
        " . $date_clause . "
        GROUP BY f.material_id 
        ORDER BY total_g DESC 
        LIMIT 5
    ");
    $top_materials = $mat_stmt->fetchAll();

    // --- PROFESIONÁLNÍ ÚČETNÍ LOGIKA ---
    $today_date = date('Y-m-d');
    $current_month = date('Y-m');
    $cz_months = [1=>"Leden", 2=>"Únor", 3=>"Březen", 4=>"Duben", 5=>"Květen", 6=>"Červen", 7=>"Červenec", 8=>"Srpen", 9=>"Září", 10=>"Říjen", 11=>"Listopad", 12=>"Prosinec"];
    $current_month_cz = $cz_months[(int)date('m')] . ' ' . date('Y');

    // 1. Dnešní tržba (Služby)
    $stmt_t_s = $pdo->prepare("SELECT SUM(price) as sum_s, COUNT(id) as count_v FROM visits WHERE visit_date = ? AND price IS NOT NULL");
    $stmt_t_s->execute([$today_date]);
    $today_stats = $stmt_t_s->fetch();

    // 2. Dnešní tržba (Produkty při návštěvách)
    $stmt_t_p = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) as sum_p FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE v.visit_date = ?");
    $stmt_t_p->execute([$today_date]);
    $today_p_sum = $stmt_t_p->fetchColumn() ?: 0;

    // 2b. Dnešní tržba (Rychlý prodej bez klienta)
    $today_direct_sales_sum = 0;
    $today_direct_sales_count = 0;
    $today_direct_sales_list = [];
    if ($has_direct_sales) {
        $stmt_t_dp = $pdo->prepare("SELECT SUM(unit_price * quantity) as sum_p, COUNT(*) as cnt FROM direct_sales WHERE sold_at = ?");
        $stmt_t_dp->execute([$today_date]);
        $today_direct_sales = $stmt_t_dp->fetch();
        $today_direct_sales_sum = (int)($today_direct_sales['sum_p'] ?? 0);
        $today_direct_sales_count = (int)($today_direct_sales['cnt'] ?? 0);
    }

    // 3. Seznam dnešních návštěv pro denní report
    $stmt_today_list = $pdo->prepare("
        SELECT v.*, c.first_name, c.last_name 
        FROM visits v 
        JOIN clients c ON v.client_id = c.id 
        WHERE v.visit_date = ? 
        ORDER BY v.id DESC
    ");
    $stmt_today_list->execute([$today_date]);
    $today_visits_list = $stmt_today_list->fetchAll();

    if ($has_direct_sales) {
        $stmt_today_direct_list = $pdo->prepare("
            SELECT ds.*, p.brand, p.name
            FROM direct_sales ds
            JOIN products p ON ds.product_id = p.id
            WHERE ds.sold_at = ?
            ORDER BY ds.id DESC
        ");
        $stmt_today_direct_list->execute([$today_date]);
        $today_direct_sales_list = $stmt_today_direct_list->fetchAll();
    }

    // 4. Měsíční souhrn aktuální
    $stmt_m_s = $pdo->prepare("SELECT SUM(price) as sum_s, COUNT(id) as count_v FROM visits WHERE visit_date LIKE ? AND price IS NOT NULL");
    $stmt_m_s->execute([$current_month . '%']);
    $m_stats_now = $stmt_m_s->fetch();

    $stmt_m_p = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) as sum_p FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE v.visit_date LIKE ?");
    $stmt_m_p->execute([$current_month . '%']);
    $m_prod_now = (int)($stmt_m_p->fetchColumn() ?: 0);

    if ($has_direct_sales) {
        $stmt_m_dp = $pdo->prepare("SELECT SUM(unit_price * quantity) as sum_p FROM direct_sales WHERE sold_at LIKE ?");
        $stmt_m_dp->execute([$current_month . '%']);
        $m_direct_now = (int)($stmt_m_dp->fetchColumn() ?: 0);
        $m_prod_now += $m_direct_now;
    }

    // 5. Robustní rozpis po dnech přes PHP (vždy odpovídá záhlaví)
    $stmt_m_all = $pdo->prepare("SELECT id, visit_date, price FROM visits WHERE visit_date LIKE ? ORDER BY visit_date DESC");
    $stmt_m_all->execute([$current_month . '%']);
    $month_visits = $stmt_m_all->fetchAll();
    
    $month_daily_breakdown = [];
    foreach($month_visits as $mv) {
        $d = $mv['visit_date'];
        if(!isset($month_daily_breakdown[$d])) {
            $month_daily_breakdown[$d] = ['visit_date' => $d, 'day_sum_s' => 0, 'day_sum_p' => 0];
        }
        $month_daily_breakdown[$d]['day_sum_s'] += (int)$mv['price'];
        
        // Produkty pro tuto konkretni navstevu
        $stmt_p_v = $pdo->prepare("SELECT SUM(price_sold * amount) FROM visit_products WHERE visit_id = ?");
        $stmt_p_v->execute([$mv['id']]);
        $month_daily_breakdown[$d]['day_sum_p'] += (int)$stmt_p_v->fetchColumn();
    }

    if ($has_direct_sales) {
        $stmt_m_direct_list = $pdo->prepare("SELECT sold_at, SUM(unit_price * quantity) as total_direct FROM direct_sales WHERE sold_at LIKE ? GROUP BY sold_at ORDER BY sold_at DESC");
        $stmt_m_direct_list->execute([$current_month . '%']);
        foreach ($stmt_m_direct_list->fetchAll() as $direct_row) {
            $d = $direct_row['sold_at'];
            if(!isset($month_daily_breakdown[$d])) {
                $month_daily_breakdown[$d] = ['visit_date' => $d, 'day_sum_s' => 0, 'day_sum_p' => 0];
            }
            $month_daily_breakdown[$d]['day_sum_p'] += (int)$direct_row['total_direct'];
        }
    }
    krsort($month_daily_breakdown);
    // Re-index pro loop
    $month_daily_breakdown = array_values($month_daily_breakdown);

    // Uzávěrka by view
    $show_accounting = (isset($_GET['view']) && $_GET['view'] === 'accounting');

    // ROČNÍ PŘEHLED TRŽEB
    $annual_stats_stmt = $pdo->query("
        SELECT 
            YEAR(visit_date) as rok,
            SUM(price) as sluzby,
            COUNT(*) as navstevy
        FROM visits
        WHERE price IS NOT NULL
        GROUP BY YEAR(visit_date)
        ORDER BY rok DESC
        LIMIT 5
    ");
    $annual_stats = $annual_stats_stmt->fetchAll();
    foreach($annual_stats as $k => $row) {
        $ap_stmt = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE YEAR(v.visit_date) = ?");
        $ap_stmt->execute([$row['rok']]);
        $annual_visit_products = (int)$ap_stmt->fetchColumn();
        $annual_direct_products = 0;

        if ($has_direct_sales) {
            $adp_stmt = $pdo->prepare("SELECT SUM(unit_price * quantity) FROM direct_sales WHERE YEAR(sold_at) = ?");
            $adp_stmt->execute([$row['rok']]);
            $annual_direct_products = (int)$adp_stmt->fetchColumn();
        }

        $annual_stats[$k]['produkty'] = $annual_visit_products + $annual_direct_products;
        $annual_stats[$k]['celkem'] = (int)$row['sluzby'] + $annual_stats[$k]['produkty'];
    }

    // HLÍDAČ: Ztracené klientky (pro Dashboard)
    $lost_clients = [];
    $all_c = $pdo->query("SELECT id, first_name, last_name, phone, preferred_interval, (SELECT MAX(visit_date) FROM visits WHERE client_id = clients.id) as last_date FROM clients")->fetchAll();
    foreach($all_c as $c) {
        if(!$c['last_date']) continue;
        $pref = $c['preferred_interval'] ?: 8;
        $days_since = (time() - strtotime($c['last_date'])) / 86400;
        if($days_since > ($pref * 7 + 14)) {
            $c['days_overdue'] = (int)($days_since - ($pref * 7));
            $lost_clients[] = $c;
        }
    }
    // Seřadit podle největšího zpoždění
    usort($lost_clients, function($a, $b) { return $b['days_overdue'] <=> $a['days_overdue']; });
}
?>
