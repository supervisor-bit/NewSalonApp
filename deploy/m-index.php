<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';
// Načteme klienty seřazené podle příjmení a jména, včetně data poslední návštěvy
$stmt = $pdo->query("
    SELECT c.id, c.first_name, c.last_name, c.phone, MAX(v.visit_date) as last_visit_date, COUNT(v.id) as visit_count
    FROM clients c 
    LEFT JOIN visits v ON v.client_id = c.id 
    GROUP BY c.id, c.first_name, c.last_name, c.phone 
    ORDER BY c.last_name ASC, c.first_name ASC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$max_client_id = 0;
$latest_visit_stamp = '0';
$total_visit_count = 0;
foreach ($clients as $client_row) {
    $max_client_id = max($max_client_id, (int)($client_row['id'] ?? 0));
    $visit_stamp = !empty($client_row['last_visit_date']) ? strtotime($client_row['last_visit_date']) : 0;
    if ($visit_stamp > (int)$latest_visit_stamp) {
        $latest_visit_stamp = (string)$visit_stamp;
    }
    $total_visit_count += (int)($client_row['visit_count'] ?? 0);
}
$clients_snapshot = count($clients) . '_' . $max_client_id . '_' . $latest_visit_stamp . '_' . $total_visit_count;

if (isset($_GET['ajax']) && $_GET['ajax'] === 'clients_pulse') {
    header('Content-Type: application/json');
    $old_snapshot = (string)($_GET['snapshot'] ?? '');
    echo json_encode([
        'new_data' => ($old_snapshot !== '' && $old_snapshot !== $clients_snapshot),
        'snapshot' => $clients_snapshot,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Aura - Mobilní míchárna</title>
    <link rel="stylesheet" href="m-style.css">
    <link rel="manifest" href="manifest-m.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="icon.png">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        // PWA Registration for Mobile
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('✅ Mobilní míchárna připravena'))
                    .catch(e => console.error('❌ Chyba Service Workera:', e));
            });
        }
    </script>
</head>
<body>
    <header class="m-header">
        <h1>Aura <span style="font-weight:300;">MOBILE</span></h1>
    </header>

    <div class="m-search-wrap">
        <input type="text" id="m-search" class="m-search-input" placeholder="Hledat klienta (např. 'Novák')...">
    </div>

    <ul class="m-client-list" id="client-list" data-snapshot="<?= htmlspecialchars($clients_snapshot, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($clients as $c): ?>
            <?php
                $visitCount = (int)($c['visit_count'] ?? 0);
                $visitLabel = $visitCount === 1
                    ? '1 návštěva'
                    : (($visitCount >= 2 && $visitCount <= 4) ? $visitCount . ' návštěvy' : $visitCount . ' návštěv');
            ?>
            <a href="m-history.php?client_id=<?= $c['id'] ?>" class="m-client-item" data-name="<?= mb_strtolower($c['last_name'].' '.$c['first_name']) ?>">
                <div class="m-client-main">
                    <div class="m-client-name"><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name']) ?></div>
                    <div class="m-client-meta">
                        <div class="m-client-date">
                            <i data-lucide="clock" style="width:12px;height:12px;vertical-align:middle;"></i>
                            <?= $c['last_visit_date'] ? date('d.m.Y', strtotime($c['last_visit_date'])) : 'Nový klient' ?>
                        </div>
                    </div>
                </div>
                <div class="m-client-side">
                    <span class="m-client-visits"><?= htmlspecialchars($visitLabel) ?></span>
                    <div class="m-client-arrow">
                        <i data-lucide="chevron-right"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        <div id="no-results" style="display:<?= empty($clients) ? 'block' : 'none' ?>; text-align:center; padding:30px; color:#94a3b8; font-weight:600;">
            <?= empty($clients) ? 'Zatím tu nejsou žádní klienti.' : 'Žádný klient nenalezen.' ?>
        </div>
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
        if (window.lucide) window.lucide.createIcons();

        const searchInput = document.getElementById('m-search');
        const clientList = document.getElementById('client-list');
        const noResults = document.getElementById('no-results');
        let currentSnapshot = clientList?.dataset.snapshot || '';

        function applyMobileFilter(value = '') {
            const val = value.toLowerCase().trim();
            const items = document.querySelectorAll('.m-client-item');
            let found = 0;

            items.forEach(item => {
                if (item.getAttribute('data-name').indexOf(val) > -1) {
                    item.style.display = 'flex';
                    found++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (noResults) {
                if (items.length === 0) {
                    noResults.textContent = 'Zatím tu nejsou žádní klienti.';
                    noResults.style.display = 'block';
                } else {
                    noResults.textContent = 'Žádný klient nenalezen.';
                    noResults.style.display = found === 0 ? 'block' : 'none';
                }
            }
        }

        searchInput.addEventListener('input', function(e) {
            applyMobileFilter(e.target.value);
        });

        async function refreshMobileClientList() {
            if (!clientList || document.hidden) return;
            if (document.activeElement === searchInput) return;

            try {
                const pulseResponse = await fetch(`${window.location.pathname}?ajax=clients_pulse&snapshot=${encodeURIComponent(currentSnapshot)}`, {
                    cache: 'no-store'
                });
                const pulseData = await pulseResponse.json();

                if (!pulseData.new_data) {
                    if (pulseData.snapshot) currentSnapshot = pulseData.snapshot;
                    return;
                }

                const currentSearch = searchInput.value || '';
                const pageResponse = await fetch(`${window.location.pathname}?t=${Date.now()}`, {
                    cache: 'no-store'
                });
                const html = await pageResponse.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newList = doc.getElementById('client-list');

                if (newList) {
                    clientList.innerHTML = newList.innerHTML;
                    clientList.dataset.snapshot = newList.dataset.snapshot || pulseData.snapshot || currentSnapshot;
                    currentSnapshot = clientList.dataset.snapshot || currentSnapshot;
                    applyMobileFilter(currentSearch);
                    if (window.lucide) window.lucide.createIcons();
                } else {
                    window.location.reload();
                }
            } catch (err) {
                // Tichá ignorace při krátkém výpadku sítě
            }
        }

        setInterval(refreshMobileClientList, 5000);
    </script>
</body>
</html>
