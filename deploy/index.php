<?php require_once 'includes/logic_init.php'; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura | Salonní přehled</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    
    <!-- PWA Support -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="icon.png">
    <meta name="theme-color" content="#0f172a">

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const allActiveProducts = <?= json_encode(array_values($active_products ?: [])) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        window.CSRF_TOKEN = CSRF_TOKEN;
        
        // PWA Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js?v=<?= filemtime(__DIR__ . '/sw.js') ?>')
                    .then(reg => {
                        reg.update();
                        console.log('✅ Service Worker připraven');
                    })
                    .catch(e => console.error('❌ Chyba Service Workera:', e));
            });
        }
    </script>
</head>
<body>

<?php  if ($setup_needed): ?>
    <div style="margin: 50px auto; text-align: center;"><h1>Systém není nainstalován</h1><a href="setup.php">Spustit instalaci</a></div>
<?php  exit; endif; ?>

<div class="app-container">
    <?php  if($zprava): ?><div id="toast-msg" class="msg"><?= htmlspecialchars($zprava) ?></div><?php endif; ?>

    <!-- NAVIGATION RAIL -->
    <nav class="nav-rail">
        <a href="#" class="rail-item rail-btn-plus" onclick="ukazNovaKlientka()" title="Nový klient">
            <i data-lucide="plus" style="width:24px; height:24px;"></i>
        </a>
        <div style="border-top:1px solid rgba(255,255,255,0.1); width:32px; margin:5px 0;"></div>
        <a href="index.php" class="rail-item <?= (!$show_stats && !$show_settings && !$show_accounting && !$show_sales) ? 'active' : '' ?>" id="nav-clients" title="Klienti">
            <i data-lucide="users" style="width:24px; height:24px;"></i>
        </a>
        <div style="margin-top:auto;">
            <a href="index.php?view=sales" class="rail-item <?= $show_sales ? 'active' : '' ?>" id="nav-sales" title="Rychlý prodej">
                <i data-lucide="shopping-bag" style="width:24px; height:24px;"></i>
            </a>
            <a href="index.php?view=settings" class="rail-item <?= $show_settings ? 'active' : '' ?>" id="nav-settings" title="Správa salonu">
                <i data-lucide="settings" style="width:24px; height:24px;"></i>
            </a>
            <a href="index.php?view=accounting" class="rail-item <?= $show_accounting ? 'active' : '' ?>" id="nav-accounting" title="Účetnictví a statistiky">
                <i data-lucide="banknote" style="width:24px; height:24px;"></i>
            </a>
            <a href="logout.php" class="rail-item" title="Odhlásit se" style="color:#ef4444; margin-top:12px;">
                <i data-lucide="log-out" style="width:24px; height:24px;"></i>
            </a>
        </div>
    </nav>

<!-- Templates moved to includes/modals.php -->

<script>
    const MATERIALS_DATA = <?= json_encode(array_values(array_map(function($m) { 
        return [
            'id' => $m['id'], 
            'name' => $m['name'], 
            'label' => trim($m['category'] . ' - ' . $m['name']),
            'needs_buying' => (int)$m['needs_buying'],
            'shopping_qty' => (int)($m['shopping_qty'] ?? 1),
            'stock_state' => (string)($m['stock_state'] ?? 'none'),
            'use_count' => (int)$m['use_count'],
            'category' => $m['category']
        ]; 
    }, $materials)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const PRODUCTS_DATA = <?= json_encode(array_values(array_map(function($p) { 
        return [
            'id' => $p['id'],
            'brand' => $p['brand'],
            'name' => $p['name'],
            'label' => trim($p['brand'] . ' - ' . $p['name']),
            'price' => $p['price'],
            'use_count' => (int)($p['use_count'] ?? 0)
        ]; 
    }, $active_products)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="app.js?v=<?= time() ?>"></script>

<!-- Global dropdown moved to includes/modals.php -->

<!-- LEVÝ PANEL -->
<div class="sidebar" style="display: <?= $show_client_karta ? 'flex' : 'none' ?>;">
    <?php
        $client_group_counts = ['all' => 0, 'active' => 0, 'followup' => 0, 'new' => 0, 'inactive' => 0];
        foreach ($clients as $client_meta) {
            $is_active_meta = isset($client_meta['is_active']) ? (int)$client_meta['is_active'] : 1;
            if (!$is_active_meta) {
                $client_group_counts['inactive']++;
                continue;
            }

            $client_group_counts['all']++;
            $last_v_meta = $client_meta['last_visit_date'] ?? null;
            $pref_i_meta = (int)($client_meta['preferred_interval'] ?: 8);
            if (!$last_v_meta) {
                $client_group_counts['new']++;
                continue;
            }
            $days_since_meta = (time() - strtotime($last_v_meta)) / 86400;
            $limit_days_meta = $pref_i_meta * 7;
            if ($days_since_meta > $limit_days_meta) {
                $client_group_counts['followup']++;
            } else {
                $client_group_counts['active']++;
            }
        }
    ?>
    <div class="sidebar-header">
        <h2>Seznam klientů</h2>
        <div class="search-container">
            <i data-lucide="search"></i>
            <input type="text" id="hledani" class="search-bar" placeholder="Hledat klienta nebo telefon..." oninput="hledejKlientku()">
        </div>
        <div class="sidebar-filters" aria-label="Skupiny klientů">
            <button type="button" class="sidebar-filter-chip active" onclick="setClientGroupFilter('all', this)">Vše <span><?= $client_group_counts['all'] ?></span></button>
            <button type="button" class="sidebar-filter-chip" onclick="setClientGroupFilter('active', this)">Aktivní <span><?= $client_group_counts['active'] ?></span></button>
            <button type="button" class="sidebar-filter-chip" onclick="setClientGroupFilter('followup', this)">Oslovit <span><?= $client_group_counts['followup'] ?></span></button>
            <button type="button" class="sidebar-filter-chip" onclick="setClientGroupFilter('new', this)">Nové <span><?= $client_group_counts['new'] ?></span></button>
            <button type="button" class="sidebar-filter-chip" onclick="setClientGroupFilter('inactive', this)">Neaktivní <span><?= $client_group_counts['inactive'] ?></span></button>
        </div>
    </div>
    <div class="client-list">
        <?php  foreach ($clients as $c): ?>
            <?php  
                $initials = mb_strtoupper(mb_substr($c['first_name'], 0, 1) . mb_substr($c['last_name'], 0, 1));
                $client_is_active = isset($c['is_active']) ? (int)$c['is_active'] : 1;
                $client_is_favorite = isset($c['is_favorite']) ? (int)$c['is_favorite'] : 0;
                $status_color = '#10b981';
                $reason = 'Aktivní klient';
                $client_group = 'active';
                $followup_detail = '';

                $last_v = $c['last_visit_date'] ?? null;
                $last_visit_label = $last_v ? 'Naposledy ' . date('d. m. Y', strtotime($last_v)) : 'Zatím bez návštěvy';
                $pref_i = (int)($c['preferred_interval'] ?: 8);

                if (!$client_is_active) {
                    $status_color = '#94a3b8';
                    $reason = 'Neaktivní klient';
                } elseif (!$last_v) {
                    $status_color = '#94a3b8';
                    $reason = 'Nový klient bez historie';
                    $client_group = 'new';
                } else {
                    $days_since = (time() - strtotime($last_v)) / 86400;
                    $limit_days = $pref_i * 7;
                    $days_overdue = max(0, (int)floor($days_since - $limit_days));

                    if ($days_since > ($limit_days + 14)) {
                        $status_color = '#ef4444';
                        $reason = 'Dlouho nebyla';
                        $client_group = 'followup';
                        $followup_detail = '+' . $days_overdue . ' dní';
                    } elseif ($days_since > $limit_days) {
                        $status_color = '#f59e0b';
                        $reason = 'Měla by přijít';
                        $client_group = 'followup';
                        $followup_detail = '+' . $days_overdue . ' dní';
                    }
                }
            ?>
            <div onclick="window.location.href='index.php?client_id=<?=$c['id']?>'" class="client-row <?= ($c['id'] == $client_id) ? 'active' : '' ?> <?= !$client_is_active ? 'is-inactive' : '' ?>" data-phone="<?= htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-group="<?= htmlspecialchars($client_group, ENT_QUOTES, 'UTF-8') ?>" data-is-active="<?= $client_is_active ?>" style="<?= !$client_is_active ? 'display:none;' : '' ?>">
                <div class="avatar"><?= $initials ?></div>
                <div class="client-info">
                    <div class="client-name-line">
                        <h3><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></h3>
                        <?php if ($client_is_favorite): ?>
                            <span class="client-favorite-star" title="Oblíbený klient">
                                <i data-lucide="star" style="width:12px; height:12px; fill:currentColor;"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p>
                        <i data-lucide="phone" style="width:10px; height:10px; opacity:0.5;"></i>
                        <?= htmlspecialchars($c['phone'] ?: 'Bez tel.') ?>
                    </p>
                    <p class="client-secondary-line">
                        <i data-lucide="calendar-days" style="width:10px; height:10px; opacity:0.5;"></i>
                        <?= htmlspecialchars($last_visit_label) ?>
                        <?php if ($followup_detail !== ''): ?>
                            <span class="client-followup-badge" title="<?= htmlspecialchars($reason) ?>"><?= htmlspecialchars($followup_detail) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <span title="Stav retence: <?= $reason ?>" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $status_color ?>; box-shadow: 0 0 5px <?= $status_color ?>44; flex-shrink:0; margin-right: 5px;"></span>
                <!-- KEBAB MENU TLAČÍTKO -->
                <div onclick="event.preventDefault(); event.stopPropagation();">
                    <button class="btn-menu" type="button" onclick='toggleMenu(event, <?= $c['id'] ?>, <?= json_encode($c['first_name']) ?>, <?= json_encode($c['last_name']) ?>, <?= json_encode($c['phone']) ?>, <?= (int)($c['preferred_interval'] ?? 0) ?>, <?= json_encode($c['client_tags'] ?? '') ?>, <?= (int)($c['is_favorite'] ?? 0) ?>, <?= (int)($c['is_active'] ?? 1) ?>)'>⋮</button>
                </div>
            </div>
        <?php  endforeach; ?>
    </div>
    <!-- POČÍTADLO KLIENTŮ -->
    <div style="padding:15px; border-top:1px solid #e2e8f0; color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; background:#fff; border-radius: 0 0 0 20px;">
        Zobrazeno: <span id="clients-visible-count"><?= $client_group_counts['all'] ?></span> / <?= count($clients) ?> klientů
    </div>
</div>

<!-- PRAVÝ PANEL -->
<div class="main-content">
    
<!-- Main modals moved to includes/modals.php -->

    <!-- POHLED: ÚČETNÍ DASHBOARD / UZÁVĚRKY -->
    <div id="accounting-box" class="karta-container" style="display: <?= $show_accounting ? 'flex' : 'none' ?>; background:#f8fafc; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
        <div class="karta-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding:45px 35px; border-bottom:none; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:20px; flex:1;">
                <div style="background:linear-gradient(135deg, #c5a059 0%, #92733c 100%); color:#fff; width:55px; height:55px; border-radius:18px; display:flex; align-items:center; justify-content:center; box-shadow: 0 8px 20px rgba(197,160,89,0.3); flex-shrink:0;">
                    <i data-lucide="banknote" style="width:28px; height:28px;"></i>
                </div>
                <div>
                    <h2 style="margin:0; font-family:'Outfit'; font-size:30px; color:#fff; letter-spacing:-0.5px;">Panel financí</h2>
                    <p style="margin:0; font-size:14px; color:rgba(255,255,255,0.6);">Denní uzávěrky a kompletní statistiky salonu</p>
                </div>
            </div>
            <!-- EXPORTY -->
            <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
                <a href="export.php?range=<?= $range ?: 'this_month' ?>&type=csv" class="btn-outline" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:#fff; text-transform:none; padding:10px 18px;" title="Export do Excelu">
                    <i data-lucide="file-spreadsheet" style="width:16px;height:16px;color:#10b981;"></i> CSV
                </a>
                <a href="export.php?range=<?= $range ?: 'this_month' ?>&type=pdf" target="_blank" class="btn-outline" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:#fff; text-transform:none; padding:10px 18px;" title="Tisk / PDF">
                    <i data-lucide="file-text" style="width:16px;height:16px;color:#ef4444;"></i> PDF
                </a>
            </div>
        </div>

        <!-- ODSUNUTÁ LIŠTA S PŘEPÍNAČI -->
        <div style="padding: 20px 35px; background: #fff; border-bottom: 1px solid #e2e8f0; display:flex; justify-content: flex-start;">
            <div class="acc-tabs-group" style="background:#f1f5f9; padding:5px; border-radius:15px; width:max-content; display:flex; gap:5px;">
                <button type="button" id="acc-btn-dnes" class="acc-tab-btn-v2 active" onclick="prepniAccounting('dnes')" style="padding:10px 25px; border-radius:12px; font-weight:700; border:none; cursor:pointer;">Dnešní přehled</button>
                <button type="button" id="acc-btn-mesic" class="acc-tab-btn-v2" onclick="prepniAccounting('mesic')" style="padding:10px 25px; border-radius:12px; font-weight:700; border:none; cursor:pointer;">Měsíční souhrn & Statistiky</button>
                <button type="button" id="acc-btn-nakup" class="acc-tab-btn-v2" onclick="prepniAccounting('nakup')" style="padding:10px 25px; border-radius:12px; font-weight:700; border:none; cursor:pointer; display:flex; align-items:center; gap:8px;">
                    Nákupní seznam
                    <?php if(count($shopping_list) > 0): ?>
                        <span id="shopping-badge-count" style="background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:10px;"><?= count($shopping_list) ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div class="karta-content" style="padding:35px; background:transparent;">
            
            <!-- VIEW: DNES -->
            <div id="acc-view-dnes" class="acc-view">
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:32px;">
                    <div class="stat-card-acc-v2">
                        <div class="icon-box"><i data-lucide="scissors"></i></div>
                        <span class="label">Služby dnes</span>
                        <div class="value"><?= number_format($today_stats['sum_s'] ?: 0, 0, ',', ' ') ?> Kč</div>
                        <span class="sub"><?= $today_stats['count_v'] ?>x návštěva</span>
                    </div>
                    <div class="stat-card-acc-v2">
                        <div class="icon-box"><i data-lucide="package"></i></div>
                        <span class="label">Produkty klientům</span>
                        <div class="value"><?= number_format($today_p_sum, 0, ',', ' ') ?> Kč</div>
                        <span class="sub">Při návštěvách</span>
                    </div>
                    <div class="stat-card-acc-v2" style="border-top:3px solid #0ea5e9;">
                        <div class="icon-box"><i data-lucide="shopping-bag"></i></div>
                        <span class="label">Rychlý prodej</span>
                        <div class="value"><?= number_format($today_direct_sales_sum, 0, ',', ' ') ?> Kč</div>
                        <span class="sub"><?= $today_direct_sales_count ?>x bez klienta</span>
                    </div>
                    <div class="stat-card-acc-v2 gold-theme">
                        <div class="icon-box"><i data-lucide="wallet"></i></div>
                        <span class="label">Celkem k vybrání</span>
                        <div class="value"><?= number_format(($today_stats['sum_s'] ?: 0) + $today_p_sum + $today_direct_sales_sum, 0, ',', ' ') ?> Kč</div>
                        <span class="sub">Služby + všechny produkty</span>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr); gap:24px; align-items:start;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 class="sekce-nadpis" style="margin:0;">Seznam dnešních klientů</h3>
                            <span style="font-size:12px; color:#94a3b8; font-weight:700; text-transform:uppercase;"><?= date('d. m. Y') ?></span>
                        </div>
                        
                        <div class="acc-list-premium">
                            <?php if(empty($today_visits_list)): ?>
                                <div style="padding:60px; text-align:center; color:#94a3b8; font-style:italic; background:#fff; border-radius:20px;">Dnes zatím žádné vyúčtované návštěvy.</div>
                            <?php else: foreach($today_visits_list as $tv): ?>
                                <div class="acc-row-v2">
                                    <div class="row-avatar"><?= mb_strtoupper(mb_substr($tv['first_name'],0,1).mb_substr($tv['last_name'],0,1)) ?></div>
                                    <div class="row-info">
                                        <div class="name"><?= htmlspecialchars($tv['first_name'] . ' ' . $tv['last_name']) ?></div>
                                        <div class="note"><?= htmlspecialchars($tv['note'] ?: 'Základní ošetření') ?></div>
                                    </div>
                                    <div class="row-amount"><?= number_format($tv['price'], 0, ',', ' ') ?> Kč</div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:18px;">
                        <div class="sekce" style="margin-bottom:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;">
                                <span class="sekce-nadpis" style="margin:0;">Dnešní rychlé prodeje</span>
                                <span style="font-size:11px; color:#64748b; font-weight:700;"><?= $today_direct_sales_count ?> záznamů</span>
                            </div>

                            <?php if(empty($today_direct_sales_list)): ?>
                                <div style="padding:16px; background:#f8fafc; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; font-size:13px;">Zatím tu není žádný samostatný prodej bez klienta.</div>
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <?php foreach($today_direct_sales_list as $ds): ?>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                            <div>
                                                <div style="font-size:13px; font-weight:800; color:#334155;"><?= htmlspecialchars(trim(($ds['brand'] ?? '') . ' ' . ($ds['name'] ?? ''))) ?></div>
                                                <div style="font-size:12px; color:#64748b;"><?= (int)$ds['quantity'] ?> ks<?php if (!empty($ds['note'])): ?> • <?= htmlspecialchars($ds['note']) ?><?php endif; ?></div>
                                            </div>
                                            <div style="text-align:right;">
                                                <div style="font-size:14px; font-weight:800; color:#10b981;"><?= number_format(((int)$ds['unit_price'] * (int)$ds['quantity']), 0, ',', ' ') ?> Kč</div>
                                                <div style="font-size:11px; color:#94a3b8;"><?= date('d. m.', strtotime($ds['sold_at'])) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- VIEW: NÁKUPNÍ SEZNAM (HLÍDAČ) -->
            <div id="acc-view-nakup" class="acc-view" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
                    <h3 class="sekce-nadpis" style="margin:0;">Věci k nákupu (Hlídač)</h3>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <div style="background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; padding:8px 12px; border-radius:10px; font-size:12px; font-weight:700; display:flex; align-items:center; gap:6px;">
                            Rozdělané <span id="material-opened-count"><?= (int)$opened_materials_count ?></span>
                        </div>
                        <div style="background:#fff1f2; border:1px solid #fecdd3; color:#be123c; padding:8px 12px; border-radius:10px; font-size:12px; font-weight:700; display:flex; align-items:center; gap:6px;">
                            Dochází <span id="material-low-count"><?= (int)$low_materials_count ?></span>
                        </div>
                        <div id="shopping-counter-box" style="background:#fef2f2; border:1px solid #fee2e2; color:#be123c; padding:8px 15px; border-radius:10px; font-size:13px; font-weight:600; display:<?= empty($shopping_list) ? 'none' : 'flex' ?>; align-items:center; gap:8px; flex-wrap:wrap;">
                            <i data-lucide="shopping-cart" style="width:16px;height:16px;"></i>
                            Aktuálně chybí <span id="shopping-counter-val"><?= count($shopping_list) ?></span> položek • <span id="shopping-counter-qty"><?= (int)$shopping_total_qty ?></span> ks
                        </div>
                        <div id="material-state-note" style="width:100%; text-align:right; color:#64748b; font-size:12px; display:<?= (($opened_materials_count + $low_materials_count) > 0) ? 'block' : 'none' ?>;">
                            `Dochází` se přidá do nákupního seznamu automaticky, `Rozdělané` zůstává jen jako přehled.
                        </div>
                    </div>
                </div>
                
                <div class="acc-list-premium" id="shopping-list-container">
                    <!-- Prázdný stav (vždy v DOMu, zobrazen jen když je potřeba) -->
                    <div id="shopping-empty-state" style="padding:80px 40px; text-align:center; background:#fff; border-radius:24px; border:2px dashed #e2e8f0; display:<?= empty($shopping_list) ? 'block' : 'none' ?>;">
                        <div id="shopping-empty-icon-wrap" style="background:<?= (($opened_materials_count + $low_materials_count) > 0) ? '#fff7ed' : '#f1f5f9' ?>; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                            <i id="shopping-empty-icon" data-lucide="check-circle-2" style="width:30px; height:30px; color:<?= (($opened_materials_count + $low_materials_count) > 0) ? '#f59e0b' : '#10b981' ?>;"></i>
                        </div>
                        <h4 id="shopping-empty-title" style="font-family:'Outfit'; font-size:20px; color:var(--primary); margin-bottom:8px;"><?= (($opened_materials_count + $low_materials_count) > 0) ? 'Košík je prázdný' : 'Všechno máme!' ?></h4>
                        <p id="shopping-empty-text" style="color:#64748b; font-size:14px; margin:0;"><?= (($opened_materials_count + $low_materials_count) > 0) ? 'Pokud něco označíš jako `Dochází`, objeví se to tady automaticky.' : 'Nákupní lístek je momentálně prázdný.' ?></p>
                    </div>

                    <!-- Seznam položek -->
                    <div id="shopping-list-rows">
                        <?php foreach($shopping_list as $sl): ?>
                            <?php $shoppingQty = max(1, (int)($sl['shopping_qty'] ?? 1)); ?>
                            <div class="acc-row-v2 shopping-row" data-id="<?= $sl['id'] ?>" data-qty="<?= $shoppingQty ?>" style="padding:18px 25px;">
                                <div class="row-avatar" style="background:#fff7ed; color:#f97316;">
                                    <i data-lucide="package" style="width:18px;height:18px;"></i>
                                </div>
                                <div class="row-info">
                                    <div class="name" style="font-size:18px;"><?= htmlspecialchars($sl['name']) ?></div>
                                    <div class="note" style="font-size:12px; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?= htmlspecialchars($sl['category']) ?> (<?= htmlspecialchars($sl['brand']) ?>) • objednat <span class="shopping-qty-inline"><?= $shoppingQty ?></span> ks</div>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
                                    <div style="display:flex; align-items:center; gap:8px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:6px 8px;">
                                        <button type="button" onclick="changeShoppingQty(<?= $sl['id'] ?>, -1, this)" style="border:none; background:#fff; color:#9a3412; width:24px; height:24px; border-radius:7px; cursor:pointer; font-weight:800; font-size:15px; line-height:1;">−</button>
                                        <span class="shopping-qty-value" style="min-width:22px; text-align:center; font-weight:800; color:#9a3412;"><?= $shoppingQty ?></span>
                                        <button type="button" onclick="changeShoppingQty(<?= $sl['id'] ?>, 1, this)" style="border:none; background:#fff; color:#9a3412; width:24px; height:24px; border-radius:7px; cursor:pointer; font-weight:800; font-size:15px; line-height:1;">+</button>
                                        <span style="font-size:11px; font-weight:700; color:#9a3412; text-transform:uppercase;">ks</span>
                                    </div>
                                    <button type="button" class="btn-ulozit" onclick="toggleShoppingPC(<?= $sl['id'] ?>, this, true)" style="background:#f1f5f9; color:var(--primary); border:none; padding:10px 20px; font-size:13px; font-weight:700; border-radius:10px; display:flex; align-items:center; gap:8px; margin:0;">
                                        <i data-lucide="check" style="width:16px;height:16px;color:#10b981;"></i> Označit jako koupené
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- VIEW: MĚSÍC -->
            <div id="acc-view-mesic" class="acc-view" style="display:none;">

                <!-- ROČNÍ PŘEHLED TRŽEB -->
                <?php if (!empty($annual_stats)): ?>
                <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:20px; padding:28px 32px; margin-bottom:32px; color:#fff;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="background:linear-gradient(135deg,#c5a059,#92733c); width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                                <i data-lucide="trending-up" style="width:20px; height:20px;"></i>
                            </div>
                            <div>
                                <div style="font-family:'Outfit'; font-size:20px; font-weight:800; letter-spacing:-0.5px;">Roční přehled tržeb</div>
                                <div style="font-size:12px; color:rgba(255,255,255,0.5);">Služby + produkty za každý rok</div>
                            </div>
                        </div>
                        <?php
                            $grand_sluzby = array_sum(array_column($annual_stats, 'sluzby'));
                            $grand_produkty = array_sum(array_column($annual_stats, 'produkty'));
                            $grand_celkem = array_sum(array_column($annual_stats, 'celkem'));
                        ?>
                        <div style="text-align:right;">
                            <div style="font-size:11px; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Celkem za všechny roky</div>
                            <div style="font-family:'Outfit'; font-size:28px; font-weight:800; color:#c5a059; letter-spacing:-1px;"><?= number_format($grand_celkem, 0, ',', ' ') ?> Kč</div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php
                            $max_annual = max(array_column($annual_stats, 'celkem')) ?: 1;
                        ?>
                        <?php foreach($annual_stats as $yr): ?>
                        <?php
                            $bar_w_s = ($yr['sluzby'] / $max_annual) * 100;
                            $bar_w_p = ($yr['produkty'] / $max_annual) * 100;
                        ?>
                        <div style="background:rgba(255,255,255,0.05); border-radius:14px; padding:16px 20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <span style="font-family:'Outfit'; font-size:22px; font-weight:800; color:rgba(255,255,255,0.9);"><?= $yr['rok'] ?></span>
                                    <span style="font-size:11px; color:rgba(255,255,255,0.4); text-transform:uppercase;"><?= $yr['navstevy'] ?> návštěv</span>
                                    <!-- Export tlačítka pro daný rok -->
                                    <a href="export.php?range=year_monthly&type=csv&year=<?= $yr['rok'] ?>" title="Stáhnout CSV pro rok <?= $yr['rok'] ?>" style="text-decoration:none; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#34d399; font-size:10px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-lucide="download" style="width:10px;height:10px;"></i> CSV
                                    </a>
                                    <a href="export.php?range=year_monthly&type=pdf&year=<?= $yr['rok'] ?>" target="_blank" title="PDF přehled roku <?= $yr['rok'] ?>" style="text-decoration:none; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; font-size:10px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-lucide="file-text" style="width:10px;height:10px;"></i> PDF
                                    </a>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:20px; font-weight:800; color:#c5a059; font-family:'Outfit';"><?= number_format($yr['celkem'], 0, ',', ' ') ?> Kč</div>
                                    <div style="font-size:11px; color:rgba(255,255,255,0.4);">
                                        <span style="color:rgba(197,160,89,0.8);"><?= number_format($yr['sluzby'], 0, ',', ' ') ?></span> služby
                                        + <span style="color:#10b981;"><?= number_format($yr['produkty'], 0, ',', ' ') ?></span> produkty Kč
                                    </div>
                                </div>
                            </div>
                            <!-- Progress bar: služby + produkty -->
                            <div style="height:8px; background:rgba(255,255,255,0.08); border-radius:8px; overflow:hidden; display:flex;">
                                <div style="height:100%; width:<?= $bar_w_s ?>%; background:linear-gradient(90deg, #c5a059, #f0c070); border-radius:8px 0 0 8px; transition:width 0.5s;"></div>
                                <div style="height:100%; width:<?= $bar_w_p ?>%; background:linear-gradient(90deg, #10b981, #34d399);"></div>
                            </div>
                            <div style="display:flex; gap:16px; margin-top:6px; font-size:10px; color:rgba(255,255,255,0.4);">
                                <span><span style="display:inline-block; width:8px; height:8px; background:#c5a059; border-radius:2px; margin-right:4px;"></span>Služby</span>
                                <span><span style="display:inline-block; width:8px; height:8px; background:#10b981; border-radius:2px; margin-right:4px;"></span>Produkty</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- HLAVNÍ STATISTICKÉ KARTY -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:32px;">
                    <div class="stat-card-acc-v2" style="background:#fff; border:1px solid #e2e8f0; text-align:center; padding:20px;">
                        <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Celkový obrat</div>
                        <div style="font-size:22px; font-weight:800; color:var(--text); font-family:'Outfit';"><?= number_format($stats_total_work + $stats_total_prod, 0, ',', ' ') ?> Kč</div>
                    </div>
                    <div class="stat-card-acc-v2" style="background:#fff; border:1px solid #e2e8f0; text-align:center; padding:20px; border-top: 3px solid var(--primary);">
                        <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Služby</div>
                        <div style="font-size:22px; font-weight:800; color:var(--primary-dark); font-family:'Outfit';"><?= number_format($stats_total_work, 0, ',', ' ') ?> Kč</div>
                    </div>
                    <div class="stat-card-acc-v2" style="background:#fff; border:1px solid #e2e8f0; text-align:center; padding:20px; border-top: 3px solid #10b981;">
                        <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Produkty</div>
                        <div style="font-size:22px; font-weight:800; color:#10b981; font-family:'Outfit';"><?= number_format($stats_total_prod, 0, ',', ' ') ?> Kč</div>
                        <div style="margin-top:8px; font-size:11px; color:#64748b; line-height:1.5;">
                            Klienti: <?= number_format($stats_total_prod_clients, 0, ',', ' ') ?> Kč<br>
                            Rychlý prodej: <?= number_format($stats_total_prod_direct, 0, ',', ' ') ?> Kč
                        </div>
                    </div>
                    <div class="stat-card-acc-v2" style="background:#fff; border:1px solid #e2e8f0; text-align:center; padding:20px; border-top: 3px solid #f59e0b;">
                        <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Návštěvy</div>
                        <div style="font-size:22px; font-weight:800; color:#b45309; font-family:'Outfit';"><?= $stats_visit_count ?></div>
                    </div>
                </div>

                <!-- GRAF TRŽEB -->
                <div style="background:#fff; padding:25px; border-radius:20px; border:1px solid #e2e8f0; margin-bottom:32px;">
                    <h3 class="sekce-nadpis" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                        <span>Trendy tržeb (posledních 12 měsíců)</span>
                        <span style="font-size:12px; color:#94a3b8; font-weight:700;"><?= $current_month_cz ?></span>
                    </h3>
                    <?php 
                        $max_val = 1;
                        foreach($monthly_stats as $m) $max_val = max($max_val, $m['work_rev'] + $m['prod_rev']);
                        $chart_stats = array_reverse($monthly_stats); 
                    ?>
                    <div style="display:flex; align-items:flex-end; height:180px; gap:12px; padding-bottom:30px; border-bottom:1px solid #f1f5f9; position:relative;">
                        <?php foreach($chart_stats as $m): 
                            $h1 = ($m['work_rev'] / $max_val) * 100;
                            $h2 = ($m['prod_rev'] / $max_val) * 100;
                        ?>
                            <div style="flex:1; display:flex; flex-direction:column; align-items:center; height:100%; justify-content:flex-end; position:relative;">
                                <div style="width:100%; max-width:35px; display:flex; flex-direction:column; border-radius:4px 4px 0 0; overflow:hidden; height:100%; justify-content:flex-end;">
                                    <div style="height:<?= $h2 ?>%; background:#10b981; opacity:0.8;" title="Produkty: <?= number_format($m['prod_rev']) ?> Kč"></div>
                                    <div style="height:<?= $h1 ?>%; background:var(--primary); opacity:0.9;" title="Práce: <?= number_format($m['work_rev']) ?> Kč"></div>
                                </div>
                                <div style="position:absolute; bottom:-25px; font-size:10px; color:#94a3b8; font-weight:700; white-space:nowrap; transform:rotate(-45deg);"><?= date('m/y', strtotime($m['ym'].'-01')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:32px;">
                    <!-- Denní tržebník -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 class="sekce-nadpis" style="margin:0;">Denní tržebník</h3>
                            <span style="font-size:11px; color:#94a3b8; font-weight:700; text-transform:uppercase;">Aktuální měsíc</span>
                        </div>
                        <div class="acc-list-premium">
                            <?php if(empty($month_daily_breakdown)): ?>
                                <div style="padding:60px; text-align:center; color:#94a3b8; font-style:italic; background:#fff; border-radius:20px;">Tento měsíc zatím žádná vyúčtovaná data.</div>
                            <?php else: foreach($month_daily_breakdown as $md): ?>
                                <?php $total_day = ($md['day_sum_s'] ?: 0) + ($md['day_sum_p'] ?: 0); ?>
                                <div class="acc-row-v2">
                                    <div class="row-avatar" style="background:#eef2f6; color:#94a3b8; font-size:12px; flex-direction:column; gap:2px;">
                                        <span><?= date('d', strtotime($md['visit_date'])) ?></span>
                                        <span style="font-size:9px; opacity:0.7;"><?= date('m', strtotime($md['visit_date'])) ?></span>
                                    </div>
                                    <div class="row-info">
                                        <div class="name"><?= date('d. m. Y', strtotime($md['visit_date'])) ?></div>
                                        <div class="note">
                                            <span style="color:var(--text); font-weight:600;"><?= number_format($md['day_sum_s'] ?: 0, 0, ',', ' ') ?> Kč</span> služby + 
                                            <span style="color:#10b981; font-weight:600;"><?= number_format($md['day_sum_p'] ?: 0, 0, ',', ' ') ?> Kč</span> produkty
                                        </div>
                                    </div>
                                    <div class="row-amount" style="font-size:18px;"><?= number_format($total_day, 0, ',', ' ') ?> Kč</div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <!-- Měsíční historie -->
                        <h3 class="sekce-nadpis" style="margin-top:40px;">Měsíční historie</h3>
                        <div style="background:#fff; border-radius:20px; border:1px solid #e2e8f0; overflow:hidden;">
                            <table style="width:100%; border-collapse: collapse; font-size:13px;">
                                <thead>
                                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left;">
                                        <th style="padding:15px 20px; color:#64748b; font-weight:700; text-transform:uppercase; font-size:10px;">Období</th>
                                        <th style="padding:15px 20px; color:#64748b; font-weight:700; text-transform:uppercase; font-size:10px;">Služby</th>
                                        <th style="padding:15px 20px; color:#64748b; font-weight:700; text-transform:uppercase; font-size:10px;">Produkty</th>
                                        <th style="padding:15px 20px; color:#64748b; font-weight:700; text-transform:uppercase; font-size:10px; text-align:right;">Celkem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach(array_slice($monthly_stats, 0, 6) as $ms): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:12px 20px; font-weight:700; color:#334155;"><?= $cz_months[(int)date('m', strtotime($ms['ym'].'-01'))] . ' ' . date('Y', strtotime($ms['ym'].'-01')) ?></td>
                                        <td style="padding:12px 20px; color:var(--primary-dark);"><?= number_format($ms['work_rev'], 0, ',', ' ') ?> Kč</td>
                                        <td style="padding:12px 20px; color:#10b981;"><?= number_format($ms['prod_rev'], 0, ',', ' ') ?> Kč</td>
                                        <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--text);"><?= number_format($ms['work_rev'] + $ms['prod_rev'], 0, ',', ' ') ?> Kč</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- BOČNÍ PANEL STATISTIK -->
                    <div>
                        <h3 class="sekce-nadpis">TOP 5 klientů</h3>
                        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:30px;">
                            <?php foreach($top_clients as $idx => $tc): ?>
                            <div style="background:#fff; padding:15px; border-radius:15px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px;">
                                <div style="width:40px; height:40px; border-radius:12px; background:<?= $idx==0 ? 'var(--primary)' : '#f1f5f9' ?>; color:<?= $idx==0 ? '#fff' : '#64748b' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px;">
                                    <?= mb_strtoupper(mb_substr($tc['first_name'],0,1).mb_substr($tc['last_name'],0,1)) ?>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:700; font-size:14px; color:var(--text);"><?= htmlspecialchars($tc['first_name'] . ' ' . $tc['last_name']) ?></div>
                                    <div style="font-size:11px; color:#94a3b8;"><?= number_format($tc['work_sum'] + $tc['prod_sum'], 0, ',', ' ') ?> Kč celkem</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 class="sekce-nadpis">Top produkty na doma</h3>
                        <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:30px;">
                            <?php if(empty($top_home_products)): ?>
                                <div style="background:#fff; padding:14px 15px; border-radius:12px; border:1px solid #e2e8f0; color:#94a3b8; font-size:12px;">Zatím tu nejsou žádná prodejní data.</div>
                            <?php else: foreach($top_home_products as $idx => $tp): ?>
                            <div style="background:#fff; padding:12px 15px; border-radius:12px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                                <div style="min-width:0;">
                                    <div style="font-weight:700; font-size:13px; color:var(--text);"><?= htmlspecialchars(trim(($tp['brand'] ?? '') . ' ' . ($tp['name'] ?? ''))) ?></div>
                                    <div style="font-size:11px; color:#64748b;"><?= (int)$tp['total_qty'] ?> ks • <?= number_format((int)$tp['total_rev'], 0, ',', ' ') ?> Kč</div>
                                </div>
                                <div style="font-size:11px; font-weight:800; color:var(--primary); background:#fff8e6; border-radius:999px; padding:4px 8px;">#<?= $idx + 1 ?></div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <h3 class="sekce-nadpis">Spotřeba materiálů</h3>
                        <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:30px;">
                            <?php foreach($top_materials as $tm): ?>
                            <div style="background:#fff; padding:12px 15px; border-radius:12px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:700; font-size:13px; color:var(--text);"><?= htmlspecialchars($tm['name']) ?></div>
                                    <div style="font-size:10px; color:#94a3b8; text-transform:uppercase;"><?= htmlspecialchars($tm['category']) ?></div>
                                </div>
                                <div style="font-weight:800; color:var(--primary); font-size:14px;"><?= number_format($tm['total_g'], 0, ',', ' ') ?> g</div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- RETENCE -->
                        <div style="background:#fff1f2; padding:20px; border-radius:20px; border:1px solid #fecaca;">
                            <h3 style="margin:0 0 15px 0; font-family:'Outfit'; font-size:16px; color:#be123c; display:flex; align-items:center; gap:8px;">
                                <i data-lucide="heart" style="width:18px; height:18px;"></i>
                                Potřebují péči?
                            </h3>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <?php if(empty($lost_clients)): ?>
                                    <div style="font-size:12px; color:#be123c; opacity:0.7;">Všichni klienti se vrací!</div>
                                <?php else: foreach(array_slice($lost_clients, 0, 3) as $lc): ?>
                                    <a href="index.php?client_id=<?= $lc['id'] ?>" style="text-decoration:none; background:#fff; padding:10px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; border:1px solid #fecaca;">
                                        <span style="font-weight:700; font-size:12px; color:#334155;"><?= htmlspecialchars($lc['first_name'] . ' ' . $lc['last_name']) ?></span>
                                        <span style="font-size:10px; background:#fff1f2; color:#be123c; padding:2px 6px; border-radius:6px; font-weight:700;"><?= $lc['days_overdue'] ?> dní</span>
                                    </a>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- SAMOSTATNÁ STRÁNKA: RYCHLÝ PRODEJ -->
    <div id="direct-sales-box" class="karta-container" style="display: <?= $show_sales ? 'flex' : 'none' ?>; background:#f8fafc; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
        <div class="karta-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding:45px 35px; border-bottom:none; display:flex; align-items:center;">
            <div style="display:flex; align-items:center; gap:20px; flex:1;">
                <div style="background:linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%); color:#fff; width:55px; height:55px; border-radius:18px; display:flex; align-items:center; justify-content:center; box-shadow: 0 8px 20px rgba(14,165,233,0.28); flex-shrink:0;">
                    <i data-lucide="shopping-bag" style="width:28px; height:28px;"></i>
                </div>
                <div>
                    <h2 style="margin:0; font-family:'Outfit'; font-size:30px; color:#fff; letter-spacing:-0.5px;">Rychlý prodej</h2>
                    <p style="margin:0; font-size:14px; color:rgba(255,255,255,0.68);">Samostatný prodej produktů bez zakládání klienta — vše se započítá do tržeb i statistik.</p>
                </div>
            </div>
        </div>

        <div class="karta-content" style="padding:35px; background:transparent;">
            <div class="direct-sales-stats-grid" style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:20px; margin-bottom:28px;">
                <div class="stat-card-acc-v2">
                    <div class="icon-box"><i data-lucide="shopping-bag"></i></div>
                    <span class="label">Dnes bez klienta</span>
                    <div class="value"><?= number_format($today_direct_sales_sum, 0, ',', ' ') ?> Kč</div>
                    <span class="sub"><?= $today_direct_sales_count ?>x prodej</span>
                </div>
                <div class="stat-card-acc-v2">
                    <div class="icon-box"><i data-lucide="calendar-range"></i></div>
                    <span class="label">Tento měsíc</span>
                    <div class="value"><?= number_format($m_direct_now, 0, ',', ' ') ?> Kč</div>
                    <span class="sub">Součet rychlého prodeje</span>
                </div>
                <div class="stat-card-acc-v2">
                    <div class="icon-box"><i data-lucide="bar-chart-3"></i></div>
                    <span class="label">Celkem rychlý prodej</span>
                    <div class="value"><?= number_format($stats_total_prod_direct, 0, ',', ' ') ?> Kč</div>
                    <span class="sub">V historii salonu</span>
                </div>
                <div class="stat-card-acc-v2 gold-theme">
                    <div class="icon-box"><i data-lucide="package"></i></div>
                    <span class="label">Produkty celkem</span>
                    <div class="value"><?= number_format($stats_total_prod, 0, ',', ' ') ?> Kč</div>
                    <span class="sub">Klienti + rychlý prodej</span>
                </div>
            </div>

            <div class="direct-sales-main-grid" style="display:grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.85fr); gap:24px; align-items:start;">
                <div class="sekce direct-sales-form-card" style="margin-bottom:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <span class="sekce-nadpis" style="margin:0;">Nový prodej bez klienta</span>
                        <span style="font-size:10px; font-weight:800; color:#0f766e; background:#ccfbf1; padding:4px 8px; border-radius:999px;">Víc produktů najednou</span>
                    </div>
                    <form action="direct_sale.php" method="POST" id="direct-sale-form" style="display:flex; flex-direction:column; gap:14px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="direct-sales-products-panel">
                            <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Položky nákupu</label>
                            <div class="direct-sales-row-head">
                                <span>Produkt</span>
                                <span>Ks</span>
                                <span>Cena</span>
                                <span></span>
                            </div>
                            <div id="direct-sale-products-wrapper"></div>
                            <button type="button" onclick="pridatProduktRow('direct-sale-products-wrapper')" style="background:none; border:none; color:var(--primary); font-size:12px; font-weight:800; cursor:pointer; padding:4px 0 0 0; text-transform:uppercase; letter-spacing:0.4px; align-self:flex-start;">
                                + Přidat další produkt
                            </button>
                            <div style="font-size:12px; color:#64748b; margin-top:8px;">Používá stejný našeptávač jako produkty u návštěvy a můžete přidat více položek najednou.</div>
                        </div>
                        <div id="direct-sale-live-summary" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:12px;">
                                <div style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:4px;">Položky</div>
                                <div id="direct-sale-line-count" style="font-size:20px; font-weight:800; color:#0f172a;">0</div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:12px;">
                                <div style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:4px;">Ks celkem</div>
                                <div id="direct-sale-qty-total" style="font-size:20px; font-weight:800; color:#0f172a;">0</div>
                            </div>
                            <div style="background:#ecfdf5; border:1px solid #bbf7d0; border-radius:14px; padding:12px;">
                                <div style="font-size:10px; font-weight:800; color:#059669; text-transform:uppercase; margin-bottom:4px;">Mezisoučet</div>
                                <div id="direct-sale-subtotal" style="font-size:20px; font-weight:800; color:#059669;">0 Kč</div>
                            </div>
                        </div>
                        <div id="direct-sale-summary-warning" style="display:none; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:12px; padding:10px 12px; font-size:12px; line-height:1.5;"></div>
                        <div class="direct-sales-meta-grid">
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Datum prodeje</label>
                                <input type="date" name="sold_at" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Poznámka</label>
                                <input type="text" name="note" maxlength="255" placeholder="např. walk-in zákazník nebo doporučení z ulice">
                            </div>
                        </div>
                        <button type="submit" class="btn-ulozit" style="width:100%; justify-content:center; margin-top:2px;">
                            <i data-lucide="check" style="width:18px;height:18px;"></i>
                            Uložit prodej do tržeb
                        </button>
                    </form>
                    <script>
                        window.refreshDirectSaleBoxes = function() {
                            const wrapper = document.getElementById('direct-sale-products-wrapper');
                            const lineEl = document.getElementById('direct-sale-line-count');
                            const qtyEl = document.getElementById('direct-sale-qty-total');
                            const subtotalEl = document.getElementById('direct-sale-subtotal');
                            if (!wrapper || !lineEl || !qtyEl || !subtotalEl) return;

                            let lines = 0;
                            let qty = 0;
                            let subtotal = 0;

                            wrapper.querySelectorAll('.product-row').forEach((row) => {
                                const searchVal = (row.querySelector('.product-search')?.value || '').trim();
                                const qtyVal = Math.max(1, parseFloat(String(row.querySelector('.product-amount')?.value || '1').replace(',', '.')) || 1);
                                const priceVal = Math.max(0, parseFloat(String(row.querySelector('.product-price')?.value || '0').replace(',', '.')) || 0);
                                const hasAnyValue = searchVal !== '' || priceVal > 0 || qtyVal > 1;

                                if (!hasAnyValue) return;
                                lines += 1;
                                qty += qtyVal;
                                subtotal += qtyVal * priceVal;
                            });

                            lineEl.textContent = String(lines);
                            qtyEl.textContent = String(qty);
                            subtotalEl.textContent = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 2 }).format(subtotal) + ' Kč';
                        };

                        document.addEventListener('DOMContentLoaded', function() {
                            const form = document.getElementById('direct-sale-form');
                            if (!form || form.dataset.inlineSummaryBound === '1') return;

                            ['input', 'change', 'click'].forEach((eventName) => {
                                form.addEventListener(eventName, () => setTimeout(() => window.refreshDirectSaleBoxes(), 0));
                            });

                            [0, 120, 400, 1000].forEach((delay) => {
                                setTimeout(() => window.refreshDirectSaleBoxes(), delay);
                            });

                            form.dataset.inlineSummaryBound = '1';
                        });
                    </script>
                </div>

                <div class="direct-sales-side-stack" style="display:flex; flex-direction:column; gap:18px;">
                    <div class="sekce" style="margin-bottom:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;">
                            <span class="sekce-nadpis" style="margin:0;">Dnešní rychlé prodeje</span>
                            <span style="font-size:11px; color:#64748b; font-weight:700;"><?= $today_direct_sales_count ?> záznamů</span>
                        </div>

                        <?php if(empty($today_direct_sales_list)): ?>
                            <div style="padding:16px; background:#f8fafc; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; font-size:13px;">Zatím tu není žádný samostatný prodej bez klienta.</div>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <?php foreach($today_direct_sales_list as $ds): ?>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                        <div>
                                            <div style="font-size:13px; font-weight:800; color:#334155;"><?= htmlspecialchars(trim(($ds['brand'] ?? '') . ' ' . ($ds['name'] ?? ''))) ?></div>
                                            <div style="font-size:12px; color:#64748b;"><?= (int)$ds['quantity'] ?> ks<?php if (!empty($ds['note'])): ?> • <?= htmlspecialchars($ds['note']) ?><?php endif; ?></div>
                                        </div>
                                        <div style="display:flex; align-items:flex-start; gap:10px;">
                                            <div style="text-align:right;">
                                                <div style="font-size:14px; font-weight:800; color:#10b981;"><?= number_format(((int)$ds['unit_price'] * (int)$ds['quantity']), 0, ',', ' ') ?> Kč</div>
                                                <div style="font-size:11px; color:#94a3b8;"><?= date('d. m.', strtotime($ds['sold_at'])) ?></div>
                                            </div>
                                            <button type="button" onclick="ukazSmazatModal('delete_direct_sale.php?id=<?= (int)$ds['id'] ?>&redirect=sales')" title="Smazat prodej" style="border:none; background:#fff1f2; color:#e11d48; width:30px; height:30px; border-radius:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                                <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sekce" style="margin-bottom:0;">
                        <span class="sekce-nadpis">Jak to funguje</span>
                        <ul style="margin:0; padding-left:18px; color:#475569; font-size:14px; line-height:1.8;">
                            <li>nemusíte zakládat nového klienta do databáze</li>
                            <li>prodej se započítá do dnešních tržeb i statistik</li>
                            <li>částka se objeví také v exportech CSV a PDF</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NASTAVENÍ A SPRÁVA SALONU -->
    <div id="settings-dashboard-box" class="karta-container" style="display: <?= $show_settings ? 'flex' : 'none' ?>; background:#f8fafc; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.08);">
        <div class="karta-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding:45px 35px; border-bottom:none; display:flex; align-items:center;">
            <div style="display:flex; align-items:center; gap:20px; flex:1;">
                <div style="background:linear-gradient(135deg, #c5a059 0%, #92733c 100%); color:#fff; width:55px; height:55px; border-radius:18px; display:flex; align-items:center; justify-content:center; box-shadow: 0 8px 20px rgba(197,160,89,0.3); flex-shrink:0;">
                    <i data-lucide="settings" style="width:28px; height:28px;"></i>
                </div>
                <div>
                    <h2 style="margin:0; font-family:'Outfit'; font-size:30px; color:#fff; letter-spacing:-0.5px;">Správa salonu</h2>
                    <p style="margin:0; font-size:14px; color:rgba(255,255,255,0.6);">Konfigurace profilu, barev, produktů a informací o aplikaci</p>
                </div>
            </div>
        </div>

        <!-- TAB NAVIGACE -->
        <div style="padding: 20px 35px; background: #fff; border-bottom: 1px solid #e2e8f0; display:flex; justify-content: flex-start;">
            <div class="acc-tabs-group" style="background:#f1f5f9; padding:5px; border-radius:15px; width:max-content; display:flex; gap:5px;">
                <button type="button" id="set-tab-btn-profile" class="acc-tab-btn-v2 active" onclick="prepniSettings('profile')">Můj profil</button>
                <button type="button" id="set-tab-btn-materials" class="acc-tab-btn-v2" onclick="prepniSettings('materials')">Číselník barev</button>
                <button type="button" id="set-tab-btn-products" class="acc-tab-btn-v2" onclick="prepniSettings('products')">Produkty na doma</button>
                <button type="button" id="set-tab-btn-about" class="acc-tab-btn-v2" onclick="prepniSettings('about')">O aplikaci</button>
            </div>
        </div>

        <div class="karta-content" style="padding:35px; background:transparent; overflow-y: auto;">
            
            <!-- TAB: MŮJ PROFIL -->
            <div id="set-view-profile" class="acc-view">
                <div style="max-width: 600px;">
                    <form method="POST" action="index.php?view=settings">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="active_tab" value="profile">
                        
                        <div class="sekce">
                            <span class="sekce-nadpis">Uživatelský účet</span>
                            <div style="margin-bottom: 20px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Uživatelské jméno</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px;">
                            </div>
                        </div>

                        <div class="sekce">
                            <span class="sekce-nadpis">Změna hesla</span>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Současné heslo (pro potvrzení změn)</label>
                                <input type="password" name="current_password" required style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Nové heslo</label>
                                    <input type="password" name="new_password" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Potvrzení nového hesla</label>
                                    <input type="password" name="confirm_password" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px;">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn-ulozit" style="width:100%; justify-content:center; margin:0;">
                                <i data-lucide="save" style="width:20px;height:20px;"></i> Uložit nastavení profilu
                            </button>
                        </div>
                    </form>

                    <div class="sekce" style="margin-top: 40px; border-top: 1px dashed #cbd5e1; padding-top: 30px;">
                        <span class="sekce-nadpis" style="display:flex; align-items:center; gap:8px;">
                            <i data-lucide="shield-check" style="width:20px;height:20px;color:#10b981;"></i> Zabezpečení a Zálohy
                        </span>
                        <p style="color:#64748b; font-size:13px; line-height:1.5; margin-bottom:15px;">
                            Pro váš maximální klid si můžete kdykoliv stáhnout kompletní kopii celé databáze (klienti, návštěvy, použité barvy). V případě potřeby z ní dokážeme systém obnovit přesně do tohoto stavu.
                        </p>
                        <a href="backup_db.php" class="btn-ulozit" style="background:#0f172a; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; padding:12px 20px; width:100%; border-radius:10px;">
                            <i data-lucide="download-cloud" style="width:18px;height:18px;"></i>
                            Stáhnout zálohu databáze (.sql)
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB: MATERIÁLY (Bývalý modál) -->
            <div id="set-view-materials" class="acc-view" style="display:none;">
                <div style="display:flex; gap:30px; align-items:flex-start;">
                    <!-- Levá strana: Formulář -->
                    <div style="flex:0 0 350px; position:sticky; top:0;">
                        <h3 class="sekce-nadpis">Přidat / Upravit odstín</h3>
                        <form id="material-form" action="save_material.php" method="POST" style="background:#fff; padding:25px; border-radius:20px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:15px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
                            <input type="hidden" name="id" id="edit-mat-id" value="">
                            <input type="hidden" name="redirect_to" value="settings_materials">
                            <div>
                                <label class="diag-label" style="margin-top:0;">Značka / Produkt</label>
                                <input type="text" name="category" id="edit-mat-cat" placeholder="např. Majirel" required>
                            </div>
                            <div>
                                <label class="diag-label" style="margin-top:0;">Odstín</label>
                                <input type="text" name="name" id="edit-mat-name" placeholder="např. 10.1" required>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
                                <button type="submit" id="btn-mat-submit" class="btn-primary" style="width:100%; height:45px;">
                                    <i data-lucide="plus"></i> <span>Přidat do číselníku</span>
                                </button>
                                <button type="button" id="btn-mat-cancel" class="btn-cancel" style="display:none; margin:0; height:45px;" onclick="cancelMatEdit()">Zrušit úpravu</button>
                            </div>
                        </form>
                    </div>
                    <!-- Pravá strana: Seznam -->
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 class="sekce-nadpis" style="margin:0;">Seznam odstínů v salonu</h3>
                            <div style="position:relative; width:220px;">
                                <i data-lucide="search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:#94a3b8;"></i>
                                <input type="text" id="mat-hledani" placeholder="Rychlé hledání..." oninput="hledejMaterial()" style="padding-left:35px; height:36px; font-size:12px; border-radius:10px;">
                            </div>
                        </div>
                        <div style="background:#fff; border-radius:20px; border:1px solid #e2e8f0; padding:10px; min-height:400px;">
                            <?php  
                            $mc = [];
                            if (isset($all_materials) && is_array($all_materials)) {
                                foreach($all_materials as $m) $mc[$m['category']][] = $m;
                            }
                            if(empty($mc)): ?>
                                <div style="padding:40px; text-align:center; color:#94a3b8;">Číselník je zatím prázdný.</div>
                            <?php else: foreach($mc as $cat => $items): ?>
                                <div class="acc-header" onclick="toggleAccordion(this)">
                                    <div class="acc-title">
                                        <i data-lucide="chevron-right" class="acc-icon"></i>
                                        <?= htmlspecialchars($cat) ?>
                                        <span style="margin-left:8px; font-size:10px; opacity:0.6; font-weight:400; text-transform:none;">(<?= count($items) ?> položek)</span>
                                    </div>
                                </div>
                                <div class="acc-content">
                                    <div class="acc-grid">
                                    <?php  foreach($items as $m): ?>
                                        <div class="mat-item" data-category="<?= htmlspecialchars(strtolower($cat)) ?>" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border-radius:12px; border:1px solid transparent; transition:0.2s; <?= !$m['is_active'] ? 'opacity:0.5; filter:grayscale(1);' : '' ?>">
                                            <span style="font-weight:600; font-size:13px; color:#334155;"><?= htmlspecialchars($m['name']) ?></span>
                                            <div style="display:flex; gap:4px;">
                                                <button type="button" onclick="editMat(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['category'])) ?>', '<?= htmlspecialchars(addslashes($m['name'])) ?>')" class="btn-menu" style="padding:4px;" title="Upravit">
                                                    <i data-lucide="pencil" style="width:14px; height:14px;"></i>
                                                </button>
                                                <button type="button" onclick="toggleMatAjax(<?= $m['id'] ?>, this)" class="btn-menu" style="padding:4px; color: <?= $m['is_active'] ? '#94a3b8' : '#10b981' ?>;" title="<?= $m['is_active'] ? 'Skrýt' : 'Zobrazit' ?>">
                                                    <i data-lucide="<?= $m['is_active'] ? 'eye-off' : 'eye' ?>" style="width:14px; height:14px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: PRODUKTY (Bývalý modál) -->
            <div id="set-view-products" class="acc-view" style="display:none;">
                <div style="display:flex; gap:30px; align-items:flex-start;">
                    <!-- Levá strana: Formulář -->
                    <div style="flex:0 0 350px; position:sticky; top:0;">
                        <h3 class="sekce-nadpis">Přidat produkt na doma</h3>
                        <form id="product-form" action="save_home_product.php" method="POST" style="background:#fff; padding:25px; border-radius:20px; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:15px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
                            <input type="hidden" name="id" id="edit-prod-id" value="">
                            <input type="hidden" name="redirect_to" value="settings_products">
                            <div>
                                <label class="diag-label" style="margin-top:0;">Značka / Kategorie</label>
                                <input type="text" name="brand" id="edit-prod-brand" placeholder="např. Kérastase" required>
                            </div>
                            <div>
                                <label class="diag-label" style="margin-top:0;">Název produktu</label>
                                <input type="text" name="name" id="edit-prod-name" placeholder="např. Absolut Repair Shampoo" required>
                            </div>
                            <div>
                                <label class="diag-label" style="margin-top:0;">Prodejní cena (Kč)</label>
                                <input type="number" name="price" id="edit-prod-price" placeholder="Včetně DPH" required>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
                                <button type="submit" id="btn-prod-submit" class="btn-primary" style="width:100%; height:45px;">
                                    <i data-lucide="plus"></i> <span>Přidat produkt</span>
                                </button>
                                <button type="button" id="btn-prod-cancel" class="btn-cancel" style="display:none; margin:0; height:45px;" onclick="cancelProdEdit()">Zrušit úpravu</button>
                            </div>
                        </form>
                    </div>
                    <!-- Pravá strana: Seznam -->
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 class="sekce-nadpis" style="margin:0;">Prodejní sortiment</h3>
                            <div style="position:relative; width:220px;">
                                <i data-lucide="search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:#94a3b8;"></i>
                                <input type="text" id="prod-hledani" placeholder="Hledat v produktech..." oninput="hledejProdukt()" style="padding-left:35px; height:36px; font-size:12px; border-radius:10px;">
                            </div>
                        </div>
                        <div style="background:#fff; border-radius:20px; border:1px solid #e2e8f0; padding:10px; min-height:400px;">
                            <?php  
                            $pc = [];
                            if (isset($all_products) && is_array($all_products)) {
                                foreach($all_products as $p) $pc[$p['brand']][] = $p;
                            }
                            if(empty($pc)): ?>
                                <div style="padding:40px; text-align:center; color:#94a3b8;">Žádné produkty k prodeji.</div>
                            <?php else: 
                                foreach($pc as $brand => $items): ?>
                                <div class="acc-header" onclick="toggleAccordion(this)">
                                    <div class="acc-title">
                                        <i data-lucide="chevron-right" class="acc-icon"></i>
                                        <?= htmlspecialchars($brand) ?>
                                        <span style="margin-left:8px; font-size:10px; opacity:0.6; font-weight:400; text-transform:none;">(<?= count($items) ?> produktů)</span>
                                    </div>
                                </div>
                                <div class="acc-content">
                                    <div class="acc-grid">
                                    <?php foreach($items as $p): ?>
                                        <div class="prod-item" data-category="<?= htmlspecialchars(strtolower($brand)) ?>" style="display:flex; justify-content:space-between; align-items:center; padding:12px 18px; background:#f8fafc; border-radius:15px; border:1px solid transparent; transition:0.2s; <?= !$p['is_active'] ? 'opacity:0.5; filter:grayscale(1);' : '' ?>">
                                            <div style="display:flex; flex-direction:column; gap:2px;">
                                                <span style="font-weight:700; font-size:14px; color:#334155;"><?= htmlspecialchars($p['name']) ?></span>
                                                <span style="font-size:12px; color:#10b981; font-weight:800;"><?= number_format($p['price'], 0, '', ' ') ?> Kč</span>
                                            </div>
                                            <div style="display:flex; gap:8px;">
                                                <button type="button" onclick="editProd(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['brand'])) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>)" class="btn-menu" style="padding:6px;" title="Upravit">
                                                    <i data-lucide="pencil" style="width:16px; height:16px;"></i>
                                                </button>
                                                <button type="button" onclick="toggleProdAjax(<?= $p['id'] ?>, this)" class="btn-menu" style="padding:6px; color: <?= $p['is_active'] ? '#94a3b8' : '#10b981' ?>;" title="<?= $p['is_active'] ? 'Skrýt' : 'Zobrazit' ?>">
                                                    <i data-lucide="<?= $p['is_active'] ? 'eye-off' : 'eye' ?>" style="width:16px; height:16px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: O APLIKACI -->
            <div id="set-view-about" class="acc-view" style="display:none;">
                <div style="display:grid; grid-template-columns: 1.15fr 0.85fr; gap:24px; align-items:start;">
                    <div class="sekce" style="margin-bottom:0;">
                        <span class="sekce-nadpis">O aplikaci Aura</span>
                        <h3 style="margin:0 0 10px 0; font-family:'Outfit'; font-size:24px; color:#0f172a;">Aura – salonní pomocník pro každodenní provoz</h3>
                        <p style="color:#475569; font-size:14px; line-height:1.7; margin-bottom:18px;">
                            Aplikace slouží pro vedení klientských karet, historii návštěv, receptur, produktů na doma, nákupního seznamu a rychlé práce z mobilu přímo u míchacího pultu.
                        </p>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div style="padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Desktop</div>
                                <div style="font-size:14px; font-weight:700; color:#334155;">Klienti, historie, statistiky, nákup</div>
                            </div>
                            <div style="padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Mobil</div>
                                <div style="font-size:14px; font-weight:700; color:#334155;">Rychlé míchání a zápis návštěv</div>
                            </div>
                            <div style="padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Sdílená data</div>
                                <div style="font-size:14px; font-weight:700; color:#334155;">Desktop i mobil pracují nad stejnou databází</div>
                            </div>
                            <div style="padding:14px 16px; border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0;">
                                <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Bezpečí</div>
                                <div style="font-size:14px; font-weight:700; color:#334155;">Přihlášení, zálohy databáze a oddělený deploy</div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:18px;">
                        <div class="sekce" style="margin-bottom:0;">
                            <span class="sekce-nadpis">Co aplikace umí</span>
                            <ul style="margin:0; padding-left:18px; color:#475569; line-height:1.8; font-size:14px;">
                                <li>evidence klientů a jejich historie</li>
                                <li>ukládání receptur v miskách včetně poměru</li>
                                <li>produkty na doma a vyúčtování návštěv</li>
                                <li>rychlé opakování minulé receptury</li>
                                <li>mobilní práce přes PWA rozhraní</li>
                            </ul>
                        </div>

                        <div class="sekce" style="margin-bottom:0;">
                            <span class="sekce-nadpis">Tip pro provoz</span>
                            <p style="margin:0; color:#475569; font-size:14px; line-height:1.7;">
                                Pro klidný provoz doporučujeme pravidelně stahovat zálohu databáze a po větších úpravách držet v synchronizaci i složku <code>deploy/</code>.
                            </p>
                        </div>

                        <div class="sekce pwa-install-card" style="margin-bottom:0;">
                            <span class="sekce-nadpis">Desktop aplikace</span>
                            <p style="margin:0 0 12px 0; color:#475569; font-size:14px; line-height:1.7;">
                                Auru si můžete na počítači nainstalovat jako samostatnou aplikaci do Docku nebo nabídky Start a otevírat ji ve vlastním okně.
                            </p>
                            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                                <button type="button" id="install-desktop-app-btn" class="btn-ulozit btn-install-app" onclick="installDesktopApp()" style="display:none; margin-top:0; width:auto;">
                                    <i data-lucide="download" style="width:16px; height:16px;"></i>
                                    Nainstalovat na tento počítač
                                </button>
                                <span id="desktop-install-status" class="pwa-status-note">V Chrome nebo Edge můžete aplikaci připnout přes menu prohlížeče.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KARTA KLIENTKY -->
    <div id="client-karta-box" class="karta-container" style="display: <?= $show_client_karta ? 'flex' : 'none' ?>;">
        <?php if ($active_client): ?>
            
        <?php  if(!empty($active_client['allergy_note'])): ?>
            <div class="alert-box" style="margin: 24px 32px 0 32px;"><i data-lucide="alert-triangle" style="width:20px;height:20px;"></i> Alergické upozornění: <?= nl2br(htmlspecialchars($active_client['allergy_note'])) ?></div>
        <?php  endif; ?>

        <div class="karta-header">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px;">
                <div style="flex:1; min-width:250px;">
                    <h1 class="karta-title" style="display:flex; align-items:center; gap:10px;">
                        <?= htmlspecialchars($active_client['first_name'] . ' ' . $active_client['last_name']) ?>
                        <?php  if($vip_status): ?>
                            <span title="VIP Klient (Top 10% tržeb)" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color:white; padding:4px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(245,158,11,0.3);">
                                <i data-lucide="star" style="width:14px; height:14px; fill:currentColor;"></i>
                            </span>
                        <?php  endif; ?>
                    </h1>
                    <span class="karta-subtitle" style="display:flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.09 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/></svg>
                        <?= htmlspecialchars($active_client['phone'] ?: 'Telefon nezadán') ?>
                    </span>
                    <?php $client_tags_list = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', (string)($active_client['client_tags'] ?? ''))))); ?>
                    <?php if (!empty($client_tags_list)): ?>
                        <div class="client-tag-list">
                            <?php foreach (array_slice($client_tags_list, 0, 8) as $tag): ?>
                                <span class="client-tag-chip"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right; display:flex; gap:15px; align-items:center;">
                    <div style="text-align:right; background:rgba(255,255,255,0.5); padding:8px 15px; border-radius:12px; border:1px solid #e2e8f0;">
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Práce</div>
                        <div style="font-size:15px; font-weight:700; color:#475569;"><?= number_format($total_spent_work, 0, ',', ' ') ?> Kč</div>
                    </div>
                    <div style="text-align:right; background:rgba(255,255,255,0.5); padding:8px 15px; border-radius:12px; border:1px solid #e2e8f0;">
                        <div style="font-size:10px; color:var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Produkty (<?= $total_products_count ?> ks)</div>
                        <div style="font-size:15px; font-weight:700; color:var(--primary-dark);"><?= number_format($total_spent_products, 0, ',', ' ') ?> Kč</div>
                    </div>
                    <div style="text-align:right; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding:12px 20px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02); min-width:140px;">
                        <div style="font-size:22px; font-weight:700; color:#1e293b; font-family:'Outfit',sans-serif; letter-spacing:-0.5px;"><?= number_format($total_spent, 0, ',', ' ') ?> Kč</div>
                        <div style="font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-top:2px;"><?= $total_visits ?> návštěv</div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                <?php  if(!empty($active_client['allergy_note'])): ?>
                    <button class="chip-btn chip-allergy" onclick="ukazUpravuVarovani()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Alergické upozornění: <?= mb_substr(htmlspecialchars($active_client['allergy_note']), 0, 28) ?>…
                    </button>
                <?php  else: ?>
                    <button class="chip-btn chip-action" onclick="ukazUpravuVarovani()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Bez alergií
                    </button>
                <?php  endif; ?>

                <button class="chip-btn chip-diag" onclick="ukazUpravuDiagnostiky()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
                    Diagnostika
                </button>


                <?php  if($avg_interval): ?>
                    <span class="chip-btn chip-purple" style="cursor:default;" title="Průměrný odstup mezi návštěvami">
                        <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i>
                        Cca každých <?= round($avg_interval / 7, 1) ?> týdnů
                    </span>
                <?php  endif; ?>

                <?php 
                    $nv = $active_client['next_visit_date'] ?? null;
                    $nvDays = $nv ? (int)((strtotime($nv) - time()) / 86400) : null;
                ?>
                <?php  if ($nv): ?>
                    <span class="chip-btn <?= $nvDays <= 7 ? 'chip-allergy' : ($nvDays <= 14 ? 'chip-amber' : 'chip-diag') ?>" style="cursor:default;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Příště: <?= date('d. m. Y', strtotime($nv)) ?> (<?= $nvDays < 0 ? 'propásnuto' : "za $nvDays dní" ?>)
                    </span>
                <?php  endif; ?>
                
                <div style="flex:1;"></div>

                <?php  if (!empty($visits)): ?>
                    <button class="chip-btn chip-action" onclick='pouzijSablonu(<?= htmlspecialchars(json_encode($visits[0]["formulas_json"] ?? "{}"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($visits[0]["services_json"] ?? "{}"), ENT_QUOTES, "UTF-8") ?>)'>
                        <i data-lucide="copy" style="width:14px; height:14px;"></i>
                        Podle poslední receptury
                    </button>
                <?php  endif; ?>
                
                <button class="chip-btn chip-purple" onclick="ukazNovaNavsteva()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nová návštěva
                </button>
            </div>

                    <div style="margin-top:16px; display:flex; gap:20px; flex-wrap:wrap; padding:12px 18px; background:#f0fdf4; border-radius:12px; border:1px solid #dcfce7; font-size:13px; color:#166534;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="opacity:0.6; font-weight:600; text-transform:uppercase; font-size:11px; letter-spacing:0.5px;">Vlasy:</span>
                            <b style="font-weight:700;"><?= htmlspecialchars($active_client['hair_texture'] ?? '—') ?> / <?= htmlspecialchars($active_client['hair_condition'] ?? '—') ?></b>
                        </div>
                        <span style="opacity:0.2;">|</span>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="opacity:0.6; font-weight:600; text-transform:uppercase; font-size:11px; letter-spacing:0.5px;">Základ:</span>
                            <b style="font-weight:700;"><?= htmlspecialchars($active_client['base_tone'] ?: '—') ?></b>
                        </div>
                        <span style="opacity:0.2;">|</span>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="opacity:0.6; font-weight:600; text-transform:uppercase; font-size:11px; letter-spacing:0.5px;">Šediny:</span>
                            <b style="font-weight:700;"><?= htmlspecialchars($active_client['gray_percentage'] ?: '—') ?></b>
                        </div>
                    </div>
                </div>

                <div class="karta-content">
                <!-- POHLED 1: HISTORIE -->
                <div id="history-box">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; gap:15px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:20px; flex:1;">
                            <span class="sekce-nadpis" style="margin:0;">Historie návštěv</span>
                            <?php  if (!empty($visits)): ?>
                                <div class="search-container" style="max-width:220px;">
                                    <i data-lucide="search"></i>
                                    <input type="text" id="history-search" class="search-bar" placeholder="Hledat datum..." oninput="filtrHistorii(this.value)" onkeyup="filtrHistorii(this.value)">
                                </div>
                            <?php  endif; ?>
                        </div>
                    </div>

                    <?php  if (empty($visits)): ?>
                        <div class="empty-state-v2">
                            <div class="empty-state-v2-icon">
                                <i data-lucide="sparkles"></i>
                            </div>
                            <h3>Zatím žádná historie</h3>
                            <p>Tento klient u nás ještě nemá žádnou návštěvu. Klikněte na tlačítko níže a zadejte první návštěvu.</p>
                            <button type="button" class="btn-primary" onclick="ukazNovaNavsteva()">+ Zadat první návštěvu</button>
                        </div>
                    <?php  else: ?>
                        <?php  foreach($visits as $visitIndex => $v): ?>
                            <?php  
                                // Návštěva je uzavřená až po vyúčtování, ne jen kvůli rychlé poznámce.
                                $vDone = (int)($v['price'] ?? 0) > 0; 
                                
                                // Prepare summary for checkout/payment dialog
                                $sHtml = "";
                                $total_products_sum = 0;
                                $total_material_g = 0;
                                $total_color_g = 0;
                                $total_oxidant_g = 0;
                                $ratio_labels = [];
                                $ratio_summary = 'Neuvedeno';
                                
                                if(!empty($v['formulas'])) {
                                    $sHtml .= "<div style='font-weight:700; font-size:11px; text-transform:uppercase; color:#64748b; margin-bottom:5px; border-bottom:1px solid #e2e8f0; padding-bottom:3px;'>Receptury:</div>";
                                    $grouped = [];
                                    foreach($v['formulas'] as $f) {
                                        $bn = $f['bowl_name'] ?: 'Miska 1';
                                        if (!isset($grouped[$bn])) {
                                            $grouped[$bn] = [
                                                'ratio' => (string)($f['mix_ratio'] ?? ''),
                                                'items' => []
                                            ];
                                        }
                                        if ($grouped[$bn]['ratio'] === '' && !empty($f['mix_ratio'])) {
                                            $grouped[$bn]['ratio'] = (string)$f['mix_ratio'];
                                        }
                                        $grouped[$bn]['items'][] = $f;

                                        $amt = (float)($f['amount_g'] ?? 0);
                                        $total_material_g += $amt;
                                        $formulaText = mb_strtolower(trim(($f['category'] ?? '') . ' ' . ($f['name'] ?? '')));
                                        if (strpos($formulaText, 'oxid') !== false || strpos($formulaText, 'oxyd') !== false) {
                                            $total_oxidant_g += $amt;
                                        } else {
                                            $total_color_g += $amt;
                                        }
                                    }
                                    foreach($grouped as $bn => $bowlData) {
                                        if (!empty($bowlData['ratio'])) {
                                            $ratio_labels[] = $bn . ' ' . $bowlData['ratio'];
                                        }
                                        $ratioText = !empty($bowlData['ratio']) ? ' · poměr ' . htmlspecialchars($bowlData['ratio']) : '';
                                        $sHtml .= "<div style='font-size:11px; font-weight:700; color:#94a3b8; font-style:italic; margin:5px 0 2px 0;'>".htmlspecialchars($bn).$ratioText.":</div>";
                                        foreach($bowlData['items'] as $f) {
                                            $sHtml .= "<div style='font-size:13px; color:#475569; margin-bottom:2px; padding-left:10px;'>• " . htmlspecialchars($f['category'] . ' – ' . $f['name']) . " <b>" . $f['amount_g'] . "g</b></div>";
                                        }
                                    }

                                    if (!empty($ratio_labels)) {
                                        $ratio_summary = implode(' • ', $ratio_labels);
                                    } elseif ($total_color_g > 0 && $total_oxidant_g > 0) {
                                        $ratio_value = rtrim(rtrim(number_format($total_oxidant_g / $total_color_g, 2, ',', ''), '0'), ',');
                                        $ratio_summary = '1:' . $ratio_value;
                                    }
                                }
                                
                                 if(!empty($v['products'])) {
                                    if($sHtml) $sHtml .= "<div style='margin-top:10px; border-top:1px solid #cbd5e1; padding-top:10px;'></div>";
                                    $sHtml .= "<div style='font-weight:700; font-size:11px; text-transform:uppercase; color:var(--primary); margin-bottom:5px; border-bottom:1px solid #dcfce7; padding-bottom:3px;'>Produkty:</div>";
                                    foreach($v['products'] as $p) {
                                        $amt = (int)($p['amount'] ?? 1);
                                        $linePrice = $p['price_sold'] * $amt;
                                        $total_products_sum += $linePrice;
                                        $amtText = ($amt > 1) ? " <span style='color:#94a3b8; font-size:11px;'>({$amt}ks)</span>" : "";
                                        $sHtml .= "<div style='font-size:13px; color:#166534; margin-bottom:2px; padding-left:10px;'>+ " . htmlspecialchars($p['brand'] . ' – ' . $p['name']) . $amtText . " <b style='float:right;'>" . number_format($linePrice, 0, ',', ' ') . " Kč</b></div>";
                                    }
                                    $sHtml .= "<div style='text-align:right; font-weight:bold; color:var(--primary-dark); font-size:12px; margin-top:5px; border-top:1px dashed #dcfce7; padding-top:5px;'>Produkty celkem: ".number_format($total_products_sum, 0, ',', ' ')." Kč</div>";
                                }
                                
                                if(!$sHtml) $sHtml = "<div style='font-size:13px; color:#94a3b8; font-style:italic;'>Bez receptury a produktů</div>";
                            ?>
                            <div class="visit-card <?= $vDone ? 'visit-done' : 'visit-pending' ?><?= $visitIndex > 0 ? ' is-collapsed' : '' ?>" data-search-date="<?= date('d. m. Y', strtotime($v['visit_date'])) ?>">

                                <!-- VISIT HEADER -->
                                <div class="visit-card-header visit-card-header-toggle" onclick="toggleVisitCard(this)">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="font-weight:700; font-size:14px; color:var(--text);">
                                            <?= date('d. m. Y', strtotime($v['visit_date'])) ?>
                                        </span>
                                        <?php  if($vDone): ?>
                                            <span class="chip-btn chip-green" style="cursor:default; padding:3px 9px;">
                                                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                Vyúčtováno
                                            </span>
                                        <?php  else: ?>
                                            <span class="chip-btn chip-action" style="cursor:default; padding:3px 9px; background:#fffbeb; color:#92400e; border-color:#fde68a;">
                                                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                Čeká na platbu
                                            </span>
                                        <?php  endif; ?>
                                    </div>
                                    <div class="visit-card-header-right">
                                        <div style="display:flex; align-items:center; gap:4px;">
                                            <?php  if($v['s_metal_detox'] ?? 0): ?><span class="history-service-icon active" title="Metal Detox"><i data-lucide="shield-check"></i></span><?php endif; ?>
                                            <?php  if($v['s_trim'] ?? 0): ?><span class="history-service-icon active" title="Stříhání"><i data-lucide="scissors"></i></span><?php endif; ?>
                                            <?php  if($v['s_blow'] ?? 0): ?><span class="history-service-icon active" title="Foukání"><i data-lucide="wind"></i></span><?php endif; ?>
                                            <?php  if($v['s_curl'] ?? 0): ?><span class="history-service-icon active" title="Kulmování"><i data-lucide="spline"></i></span><?php endif; ?>
                                            <?php  if($v['s_iron'] ?? 0): ?><span class="history-service-icon active" title="Žehlení"><i data-lucide="minus"></i></span><?php endif; ?>
                                        </div>
                                        <?php  if($vDone && $v['price'] > 0): ?>
                                            <span style="font-weight:700; font-size:17px; color:#166534;"><?= number_format($v['price'], 0, ',', ' ') ?> Kč</span>
                                        <?php  endif; ?>
                                        <button type="button" class="visit-card-toggle" aria-label="Rozbalit nebo sbalit detail návštěvy" onclick="event.stopPropagation(); toggleVisitCard(this.closest('.visit-card-header'))">
                                            <i data-lucide="chevron-down" style="width:16px; height:16px;"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- VISIT BODY -->
                                <div class="visit-card-body">
                                    <?php  if(!$vDone): ?>
                                        <div style="text-align:center; margin-bottom:14px;">

                                            <button class="btn-ulozit" style="margin:0; background:#10b981; padding:9px 20px; font-size:13px; width:auto; display:inline-flex; align-items:center; gap:6px;" type="button" onclick='ukazCheckout(<?= $v['id'] ?>, <?= $total_products_sum ?>, <?= htmlspecialchars(json_encode((string)($v['note'] ?? "")), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($sHtml), ENT_QUOTES, "UTF-8") ?>, <?= $v['price'] ?? 0 ?>)'>
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                                Vyúčtovat a uzavřít
                                            </button>
                                        </div>
                                    <?php  endif; ?>

                                    <?php  if(!empty($v['formulas'])): ?>
                                        <div class="visit-summary-panel">
                                            <div class="visit-summary-item">
                                                <span class="visit-summary-label">Materiál celkem</span>
                                                <span class="visit-summary-value"><?= number_format($total_material_g, 0, ',', ' ') ?> g</span>
                                            </div>
                                            <div class="visit-summary-item">
                                                <span class="visit-summary-label">Barva</span>
                                                <span class="visit-summary-value"><?= $total_color_g > 0 ? number_format($total_color_g, 0, ',', ' ') . ' g' : '—' ?></span>
                                            </div>
                                            <div class="visit-summary-item">
                                                <span class="visit-summary-label">Oxidant</span>
                                                <span class="visit-summary-value"><?= $total_oxidant_g > 0 ? number_format($total_oxidant_g, 0, ',', ' ') . ' g' : '—' ?></span>
                                            </div>
                                            <div class="visit-summary-item">
                                                <span class="visit-summary-label">Misky</span>
                                                <span class="visit-summary-value"><?= count($grouped) ?></span>
                                            </div>
                                            <div class="visit-summary-item visit-summary-item-wide">
                                                <span class="visit-summary-label">Poměr</span>
                                                <span class="visit-summary-value visit-summary-value-small"><?= htmlspecialchars($ratio_summary) ?></span>
                                            </div>
                                        </div>
                                    <?php  endif; ?>

                                    <?php  if(!empty($v['note'])): ?>
                                        <p style="margin:0 0 12px 0; font-size:13px; color:#64748b; font-style:italic; padding:8px; background:#f8fafc; border-radius:6px; border-left:3px solid #cbd5e1;"><?= nl2br(htmlspecialchars($v['note'])) ?></p>
                                    <?php  endif; ?>

                                    <?php  if(!empty($v['formulas'])): ?>
                                        <?php 
                                        $grouped = [];
                                        foreach($v['formulas'] as $f) {
                                            $b = $f['bowl_name'] ?: 'Miska 1';
                                            if(!isset($grouped[$b])) {
                                                $grouped[$b] = [
                                                    'ratio' => (string)($f['mix_ratio'] ?? ''),
                                                    'items' => []
                                                ];
                                            }
                                            if ($grouped[$b]['ratio'] === '' && !empty($f['mix_ratio'])) {
                                                $grouped[$b]['ratio'] = (string)$f['mix_ratio'];
                                            }
                                            $grouped[$b]['items'][] = $f;
                                        }
                                        foreach($grouped as $bName => $bowlData): ?>
                                            <div style="margin-bottom:10px;">
                                                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                                    <?= htmlspecialchars($bName) ?>
                                                    <?php if (!empty($bowlData['ratio'])): ?>
                                                        <span class="mix-ratio-pill">Poměr <?= htmlspecialchars($bowlData['ratio']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="padding-left:16px;">
                                                    <?php  foreach($bowlData['items'] as $f): ?>
                                                        <div style="font-size:13px; color:#475569; margin-bottom:2px;">
                                                            <span style="color:#94a3b8; margin-right:4px;">•</span><?= htmlspecialchars($f['category'] . ' – ' . $f['name']) ?> <b style="color:#253344;"><?= $f['amount_g'] ?>g</b>
                                                        </div>
                                                    <?php  endforeach; ?>
                                                </div>
                                            </div>
                                        <?php  endforeach; ?>
                                    <?php  endif; ?>

                                    <?php  if(!empty($v['products'])): ?>
                                        <div style="margin-top:15px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                                            <div style="font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px; display:flex; align-items:center; gap:5px;">
                                                <i data-lucide="shopping-bag" style="width:12px; height:12px;"></i> Produkty na doma
                                            </div>
                                            <div style="padding-left:16px;">
                                                <?php  foreach($v['products'] as $p): ?>
                                                    <?php  $amt = (int)($p['amount'] ?? 1); ?>
                                                    <div style="font-size:13px; color:#475569; margin-bottom:2px;">
                                                        <span style="color:#10b981; margin-right:4px;">+</span><?= htmlspecialchars($p['brand'] . ' – ' . $p['name']) ?> 
                                                        <?php  if($amt > 1): ?>
                                                            <span style="color:#94a3b8; font-size:11px;">(<?= $amt ?>ks)</span>
                                                        <?php  endif; ?>
                                                        <b style="color:#253344;"><?= number_format($p['price_sold'] * $amt, 0, ',', ' ') ?> Kč</b>
                                                    </div>
                                                <?php  endforeach; ?>
                                            </div>
                                        </div>
                                    <?php  endif; ?>
                                </div>

                                <!-- VISIT FOOTER ACTIONS -->
                                <div class="visit-card-actions">
                                    <a href="#" onclick='pouzijSablonu(<?= htmlspecialchars(json_encode($v['formulas_json'] ?? "{}"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($v['services_json'] ?? "{}"), ENT_QUOTES, "UTF-8") ?>)' class="chip-btn chip-purple" title="Použije recepturu a služby z této návštěvy pro nový záznam">
                                        <i data-lucide="copy" style="width:12px; height:12px;"></i>
                                        Zopakovat recepturu
                                    </a>
                                    <a href="#" onclick='ukazUpravitNavstevu(<?= $v['id'] ?>, "<?= htmlspecialchars($v['visit_date']) ?>", <?= htmlspecialchars(json_encode($v['formulas_json'] ?? "{}"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($v['products_json'] ?? "[]"), ENT_QUOTES, "UTF-8") ?>, <?= (int)($v['s_metal_detox'] ?? 0) ?>, <?= (int)($v['s_trim'] ?? 0) ?>, <?= (int)($v['s_blow'] ?? 0) ?>, <?= (int)($v['s_curl'] ?? 0) ?>, <?= (int)($v['s_iron'] ?? 0) ?>)' class="chip-btn chip-action">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Receptury / Produkty
                                    </a>
                                    <?php  if($vDone): ?>
                                    <a href="javascript:void(0)" onclick='ukazCheckout(<?= $v['id'] ?>, <?= $total_products_sum ?>, <?= htmlspecialchars(json_encode((string)($v['note'] ?? "")), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($sHtml), ENT_QUOTES, "UTF-8") ?>, <?= $v['price'] ?? 0 ?>)' class="chip-btn chip-action">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        Platba
                                    </a>
                                    <?php  endif; ?>
                                    <a href="#" onclick="ukazSmazatModal('delete_visit.php?id=<?= $v['id'] ?>&client_id=<?= $active_client['id'] ?>&source=pc')" class="chip-btn chip-danger" style="margin-left:auto;">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Smazat
                                    </a>
                                </div>
                            </div>
                        <?php  endforeach; ?>
                        
                        <div id="history-empty-filter" class="empty-state" style="display:none; padding:40px; background:#f8fafc; border:1px dashed #e2e8f0; color:#94a3b8; border-radius:16px; margin:20px 0;">
                            <i data-lucide="calendar-x" style="width:24px; height:24px; margin-bottom:10px; opacity:0.5;"></i><br>
                            Žádná návštěva neodpovídá zadanému datu.
                        </div>
                    <?php  endif; ?>
                </div>

                <!-- POHLED 2: NOVÁ NÁVŠTĚVA -->
                <form id="new-visit-box" style="display:none;" action="save_visit.php" method="POST">
                    <input type="hidden" name="client_id" value="<?= $active_client['id'] ?>">
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <span class="sekce-nadpis" style="margin:0;">Záznam nové návštěvy</span>
                        <a href="javascript:void(0)" onclick="ukazHistorii()" style="color:#ef4444; text-decoration:none;">Zrušit</a>
                    </div>
                    <div class="sekce">
                        <span class="sekce-nadpis">Termín procedury & Poskytnuté služby</span>
                        <input type="date" name="visit_date" value="<?= date('Y-m-d') ?>" required title="Datum">
                        
                        <div class="services-grid">
                            <label class="service-chip">
                                <input type="checkbox" name="s_metal_detox" value="1">
                                <span class="service-label"><i data-lucide="shield-check"></i> Metal Detox</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_trim" value="1">
                                <span class="service-label"><i data-lucide="scissors"></i> Stříhání</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_blow" value="1">
                                <span class="service-label"><i data-lucide="wind"></i> Foukání</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_curl" value="1">
                                <span class="service-label"><i data-lucide="spline"></i> Kulmování</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_iron" value="1">
                                <span class="service-label"><i data-lucide="minus"></i> Žehlení</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="sekce">
                        <span class="sekce-nadpis">L'Oréal Receptury</span>
                        <p style="font-size:13px; color:#64748b; margin-top:-5px; margin-bottom:15px; background:#f8fafc; padding:10px; border-radius:6px; border:1px dashed #cbd5e1; display:flex; align-items:center; gap:8px;"><i data-lucide="lightbulb" style="width:24px;height:24px;color:#f59e0b;flex-shrink:0;"></i><span><b>Rychlé psaní:</b> Šipkami na klávesnici zvolte odstín a přes <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Enter</kbd> odskočte na gramy. Na poli gramů stiskem <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Enter</kbd> přidáte novou barvu pod tu stávající, a stiskem <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Ctrl + Enter</kbd> (Mac: Cmd+Enter) okamžitě vytvoříte rovnou celou novou Misku.</span></p>
                        <div id="bowls-wrapper-new">
                            <!-- Misky -->
                        </div>
                        <button type="button" class="btn-outline" onclick="pridatMisku('bowls-wrapper-new')">+ PŘIDAT DALŠÍ MISKU</button>
                    </div>

                    <div class="sekce" style="border-top:1px solid #e2e8f0; padding-top:20px; margin-top:20px;">
                        <span class="sekce-nadpis" style="display:flex; align-items:center; gap:8px;">
                            <i data-lucide="shopping-bag" style="width:18px;height:18px;color:var(--primary);"></i> Produkty na doma
                        </span>
                        <div id="products-wrapper-new"></div>
                        <button type="button" class="btn-outline" onclick="pridatProduktRow('products-wrapper-new')">+ PŘIDAT PRODUKT PRO KLIENTA</button>
                    </div>
                    
                    <div style="display:flex; gap:12px; align-items:center; margin-top:24px; flex-wrap:wrap;">
                        <button type="button" class="btn-cancel" onclick="ukazHistorii()" style="margin-top:0; width:auto; min-width:160px;">Zrušit</button>
                        <button type="submit" class="btn-ulozit" style="margin-top:0; flex:1;">Uložit návštěvu a recepturu</button>
                    </div>
                </form>

                <!-- POHLED 3: ÚPRAVA NÁVŠTĚVY -->
                <form id="edit-visit-box" style="display:none;" action="edit_visit.php" method="POST">
                    <input type="hidden" name="client_id" value="<?= $active_client['id'] ?>">
                    <input type="hidden" name="visit_id" id="edit-visit-id">
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <span class="sekce-nadpis" style="margin:0;">Upravit zápis z návštěvy</span>
                        <a href="javascript:void(0)" onclick="ukazHistorii()" style="color:#ef4444; text-decoration:none;">Zrušit úpravy</a>
                    </div>
                    <div class="sekce">
                        <span class="sekce-nadpis">Termín procedury & Poskytnuté služby</span>
                        <input type="date" name="visit_date" id="edit-visit-date" required title="Datum">
                        
                        <div class="services-grid">
                            <label class="service-chip">
                                <input type="checkbox" name="s_metal_detox" id="edit-s-metal-detox" value="1">
                                <span class="service-label"><i data-lucide="shield-check"></i> Metal Detox</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_trim" id="edit-s-trim" value="1">
                                <span class="service-label"><i data-lucide="scissors"></i> Stříhání</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_blow" id="edit-s-blow" value="1">
                                <span class="service-label"><i data-lucide="wind"></i> Foukání</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_curl" id="edit-s-curl" value="1">
                                <span class="service-label"><i data-lucide="spline"></i> Kulmování</span>
                            </label>
                            <label class="service-chip">
                                <input type="checkbox" name="s_iron" id="edit-s-iron" value="1">
                                <span class="service-label"><i data-lucide="minus"></i> Žehlení</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="sekce">
                        <span class="sekce-nadpis">L'Oréal Receptury</span>
                        <p style="font-size:13px; color:#64748b; margin-top:-5px; margin-bottom:15px; background:#f8fafc; padding:10px; border-radius:6px; border:1px dashed #cbd5e1; display:flex; align-items:center; gap:8px;"><i data-lucide="lightbulb" style="width:24px;height:24px;color:#f59e0b;flex-shrink:0;"></i><span><b>Rychlé psaní:</b> Šipkami na klávesnici zvolte odstín a přes <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Enter</kbd> odskočte na gramy. Na poli gramů stiskem <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Enter</kbd> přidáte novou barvu pod tu stávající, a stiskem <kbd style="background:#e2e8f0; padding:2px 5px; border-radius:4px; font-weight:bold; color:black;">Ctrl + Enter</kbd> (Mac: Cmd+Enter) okamžitě vytvoříte rovnou celou novou Misku.</span></p>
                        <div id="bowls-wrapper-edit">
                            <!-- Misky JS -->
                        </div>
                        <button type="button" class="btn-outline" onclick="pridatMisku('bowls-wrapper-edit')">+ PŘIDAT DALŠÍ MISKU</button>
                    </div>

                    <div class="sekce" style="border-top:1px solid #e2e8f0; padding-top:20px; margin-top:20px;">
                        <span class="sekce-nadpis" style="display:flex; align-items:center; gap:8px;">
                            <i data-lucide="shopping-bag" style="width:18px;height:18px;color:var(--primary);"></i> Produkty na doma
                        </span>
                        <div id="products-wrapper-edit"></div>
                        <button type="button" class="btn-outline" onclick="pridatProduktRow('products-wrapper-edit')">+ PŘIDAT PRODUKT PRO KLIENTA</button>
                    </div>
                    
                    <div style="display:flex; gap:12px; align-items:center; margin-top:24px; flex-wrap:wrap;">
                        <button type="button" class="btn-cancel" onclick="ukazHistorii()" style="margin-top:0; width:auto; min-width:160px;">Zrušit úpravy</button>
                        <button type="submit" class="btn-ulozit" style="margin-top:0; flex:1;">Uložit změny návštěvy</button>
                    </div>
                </form>

            </div>
        </div>
    </div> <!-- Konec karta-content -->
    
    <?php  else: ?>
            <div class="karta-content" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:100px 20px; text-align:center; color:#94a3b8;">
                <div style="background:#f1f5f9; width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:20px;">
                    <i data-lucide="users" style="width:40px; height:40px; color:#cbd5e1;"></i>
                </div>
                <h2 style="font-family:'Outfit'; color:#334155; margin-bottom:8px;">Karta klienta</h2>
                <p style="font-size:14px; max-width:300px; line-height:1.6;">Vyberte klienta v levém seznamu pro zobrazení historie a receptur, nebo přidejte nového přes tlačítko + v menu.</p>
            </div>
        <?php  endif; // Konec if ($active_client) ?>
    </div> <!-- Koncový tag client-karta-box -->

</div> <!-- Koncový tag main-content -->

<script>
    lucide.createIcons();
</script>
    </div> <!-- End .app-container -->

    <script>
        lucide.createIcons();
    </script>
    <?php require_once 'includes/modals.php'; ?>
    <?php if ($show_settings): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'profile';
            prepniSettings(tab);
        });
    </script>
    <?php endif; ?>

    <!-- LIVE SYNC: Automatické hlídání změn z mobilu -->
    <?php if (isset($active_client) && $active_client): ?>
    <script>
        (function() {
            // Zjistíme nejvyšší ID návštěvy, které aktuálně vidíme
            // Použijeme PHP k vytažení max ID z pole $visits
            <?php 
                $v_cnt = count($visits);
                $max_id = 0;
                foreach ($visits as $v) if ((int)$v['id'] > $max_id) $max_id = (int)$v['id'];
                
                $f_stmt = $pdo->prepare("SELECT MAX(id) as max_f FROM formulas WHERE visit_id IN (SELECT id FROM visits WHERE client_id = ?)");
                $f_stmt->execute([(int)$active_client['id']]);
                $max_f = (int)($f_stmt->fetchColumn() ?: 0);
                
                $initial_snapshot = $v_cnt . "_" . $max_id . "_" . $max_f;
            ?>
            let currentSnapshot = '<?= $initial_snapshot ?>';
            const clientId = <?= (int)$active_client['id'] ?>;
            
            setInterval(async () => {
                // Seznam prvků, které signalizují, že uživatel něco píše nebo upravuje
                const editors = [
                    document.getElementById('new-visit-box'),
                    document.getElementById('edit-visit-box'),
                    document.getElementById('checkout-modal'),
                    document.getElementById('edit-client-profile-modal'),
                    document.getElementById('edit-diagnostics-modal')
                ];
                
                // Pokud je některý z editorů viditelný, nebudeme obnovovat (nechceme smazat rozdělanou práci)
                const isWriting = editors.some(ed => {
                    if (!ed) return false;
                    const style = window.getComputedStyle(ed);
                    return style.display !== 'none';
                });

                if (isWriting) return;

                try {
                    const response = await fetch(`pulse_check.php?client_id=${clientId}&snapshot=${currentSnapshot}`);
                    const data = await response.json();
                    
                    if (data.new_data) {
                        console.log('Detekována změna! Provádím plynulou aktualizaci karty...');
                        currentSnapshot = data.snapshot; // Aktualizujeme snapshot pro další kontrolu
                        
                        // Uděláme kartu lehce průhlednou, aby bylo vidět, že se něco děje
                        const kartaElement = document.querySelector('.client-karta-box');
                        if (kartaElement) kartaElement.style.opacity = '0.5';
                        
                        try {
                            const response = await fetch(window.location.href);
                            const html = await response.text();
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            const newNode = doc.querySelector('.client-karta-box');
                            if (newNode && kartaElement) {
                                kartaElement.innerHTML = newNode.innerHTML;
                                kartaElement.style.opacity = '1';
                                // Znovu zinicializujeme ikonky a případné skripty
                                if (window.lucide) window.lucide.createIcons();
                                console.log('Karta úspěšně aktualizována bez bliknutí.');
                            } else {
                                // Pokud by selhala výměna uzlu, raději reloadneme úplně
                                window.location.reload();
                            }
                        } catch (e) {
                            window.location.reload();
                        }
                    }
                } catch (err) {
                    // Tichá ignorace chyb sítě
                }
            }, 5000); // Kontrola každých 5 vteřin
        })();
    </script>
    <?php endif; ?>
</body>
</html>
