<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';
// Načteme klienty seřazené podle příjmení a jména, včetně data poslední návštěvy
$stmt = $pdo->query("
    SELECT c.id, c.first_name, c.last_name, c.phone, MAX(v.visit_date) as last_visit_date 
    FROM clients c 
    LEFT JOIN visits v ON v.client_id = c.id 
    GROUP BY c.id, c.first_name, c.last_name, c.phone 
    ORDER BY c.last_name ASC, c.first_name ASC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KARTA - Mobilní Míchárna</title>
    <link rel="stylesheet" href="m-style.css">
    <link rel="manifest" href="manifest-m.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="icon.png">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <header class="m-header">
        <h1>KARTA <span style="font-weight:300;">MOBILE</span></h1>
    </header>

    <div class="m-search-wrap">
        <input type="text" id="m-search" class="m-search-input" placeholder="Hledat klientku (např. 'Nováková')...">
    </div>

    <ul class="m-client-list" id="client-list">
        <?php foreach ($clients as $c): ?>
            <a href="m-history.php?client_id=<?= $c['id'] ?>" class="m-client-item" data-name="<?= mb_strtolower($c['last_name'].' '.$c['first_name']) ?>">
                <div>
                    <div class="m-client-name"><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name']) ?></div>
                    <div class="m-client-date">
                        <i data-lucide="clock" style="width:12px;height:12px;vertical-align:middle;"></i> 
                        <?= $c['last_visit_date'] ? date('d.m.Y', strtotime($c['last_visit_date'])) : 'Nová klientka' ?>
                    </div>
                </div>
                <div class="m-client-arrow">
                    <i data-lucide="chevron-right"></i>
                </div>
            </a>
        <?php endforeach; ?>
        <div id="no-results" style="display:none; text-align:center; padding:30px; color:#94a3b8; font-weight:600;">Žádná klientka nenalezena.</div>
    </ul>

    <?php if (isset($_GET['success'])): ?>
        <div id="save-toast" class="m-toast">
            <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
            Receptura uložena!
        </div>
        <script>
            // Vyčistíme URL, aby při refreshu nápis "Uloženo" zmizel
            window.history.replaceState({}, document.title, window.location.pathname);

            setTimeout(() => {
                const toast = document.getElementById('save-toast');
                if(toast) {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => toast.remove(), 500);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <script>
        lucide.createIcons();
        
        // Jednoduché fulltext hledání
        document.getElementById('m-search').addEventListener('input', function(e) {
            let val = e.target.value.toLowerCase();
            let items = document.querySelectorAll('.m-client-item');
            let found = 0;
            items.forEach(item => {
                if(item.getAttribute('data-name').indexOf(val) > -1) {
                    item.style.display = 'flex';
                    found++;
                } else {
                    item.style.display = 'none';
                }
            });
            document.getElementById('no-results').style.display = found === 0 ? 'block' : 'none';
        });
    </script>
</body>
</html>
