<?php
// login.php
$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $is_https,
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

require 'db.php';

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$device_override = strtolower(trim((string)($_GET['device'] ?? $_POST['device'] ?? '')));

$is_tablet_client = preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $user_agent) === 1;
$is_phone_client = preg_match('/iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini|Mobile/i', $user_agent) === 1;
$is_mobile_client = $is_phone_client || $is_tablet_client;

if ($device_override === 'mobile' || $device_override === 'tablet') {
    $default_redirect = 'm-index.php';
} elseif ($device_override === 'desktop') {
    $default_redirect = 'index.php';
} else {
    $default_redirect = $is_mobile_client ? 'm-index.php' : 'index.php';
}

$redirect_target = $_GET['redirect'] ?? $_POST['redirect'] ?? $default_redirect;
$redirect_target = trim((string)$redirect_target);

if (
    $redirect_target === '' ||
    preg_match('#^(?:[a-z][a-z0-9+\-.]*:)?//#i', $redirect_target) ||
    strpos($redirect_target, "\r") !== false ||
    strpos($redirect_target, "\n") !== false
) {
    $redirect_target = $default_redirect;
}

$redirect_target = ltrim($redirect_target, '/');
if ($redirect_target === '' || strpos($redirect_target, 'login.php') === 0) {
    $redirect_target = $default_redirect;
}

if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_target);
    exit;
}

// Zkontrolujeme, zda v DB existuje alespoň jeden uživatel
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$user_count = $stmt->fetchColumn();

$error = "";
$success = "";
$show_http_notice = !$is_https;

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user_count == 0) {
        // Prvotní nastavení - vytvoření prvního admina
        if (!empty($user) && !empty($pass)) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$user, $hash]);
            $success = "Účet byl úspěšně vytvořen! Nyní se můžete přihlásit.";
            $user_count = 1; // Přepneme na login mód
        } else {
            $error = "Vyplňte prosím obě pole.";
        }
    } else {
        // Klasické přihlášení
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$user]);
        $row = $stmt->fetch();

        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $row['username'];
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            header('Location: ' . $redirect_target);
            exit;
        } else {
            $error = "Nesprávné jméno nebo heslo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení | Aura</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #c5a059;
            --primary-dark: #a68545;
            --bg-rail: #0f172a;
            --text: #1e293b;
            --border: #e2e8f0;
            --muted: #64748b;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(197, 160, 89, 0.16), transparent 0, transparent 38%),
                linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            padding: 24px;
        }

        .login-shell {
            width: min(1120px, 100%);
            min-height: calc(100vh - 48px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.12);
            animation: fadein 0.45s ease-out;
        }

        @keyframes fadein {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-splash {
            position: relative;
            background:
                linear-gradient(160deg, rgba(15,23,42,0.98) 0%, rgba(30,41,59,0.96) 52%, rgba(72,63,47,0.94) 100%);
            color: #fff;
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .login-splash::before,
        .login-splash::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(197, 160, 89, 0.12);
            filter: blur(2px);
        }

        .login-splash::before {
            width: 240px;
            height: 240px;
            top: -60px;
            right: -70px;
        }

        .login-splash::after {
            width: 180px;
            height: 180px;
            bottom: -40px;
            left: -50px;
        }

        .splash-top,
        .splash-bottom {
            position: relative;
            z-index: 1;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            color: #f8fafc;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 22px;
        }

        .splash-title {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(32px, 3vw, 44px);
            line-height: 1.05;
            margin: 0 0 14px 0;
            letter-spacing: -0.04em;
        }

        .splash-text {
            color: rgba(226, 232, 240, 0.86);
            font-size: 15px;
            line-height: 1.75;
            max-width: 520px;
            margin: 0 0 26px 0;
        }

        .splash-feature-list {
            display: grid;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .splash-feature-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 14px;
            color: #f8fafc;
        }

        .splash-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 28px;
        }

        .metric-card {
            padding: 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .metric-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(226, 232, 240, 0.72);
            margin-bottom: 6px;
            font-weight: 700;
        }

        .metric-value {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .splash-bottom {
            color: rgba(226, 232, 240, 0.75);
            font-size: 12px;
            line-height: 1.7;
        }

        .login-panel {
            padding: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.82);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
        }

        .logo-area {
            background: linear-gradient(135deg, var(--bg-rail), #1e293b);
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--primary);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.18);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--primary-dark);
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 30px;
            line-height: 1.1;
            margin: 0 0 8px 0;
            font-weight: 700;
            color: #0f172a;
        }

        .intro {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
            margin: 0 0 24px 0;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 14px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .error { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .success { background: #d1fae5; color: #047857; border-color: #a7f3d0; }
        .warning { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }

        .form-shell {
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06);
        }

        .form-group { text-align: left; margin-bottom: 16px; }

        label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            font-size: 15px;
            transition: 0.2s;
            font-family: inherit;
            background: #fbfdff;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.12);
            background: #fff;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--bg-rail), #1e293b);
            color: #fff;
            border: none;
            width: 100%;
            padding: 15px 16px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.18);
        }

        .login-footer {
            margin-top: 16px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .version-badge {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        @media (max-width: 920px) {
            body { padding: 12px; }
            .login-shell {
                min-height: auto;
                grid-template-columns: 1fr;
            }
            .login-splash,
            .login-panel {
                padding: 24px;
            }
            .splash-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<main class="login-shell">
    <section class="login-splash">
        <div class="splash-top">
            <div class="brand-badge">
                <i data-lucide="sparkles" style="width:14px; height:14px;"></i>
                Aura
            </div>

            <h2 class="splash-title">Moderní salonní přehled v jednom okně.</h2>
            <p class="splash-text">
                Klientské karty, historie návštěv, receptury, produkty na doma i rychlá práce z mobilu. Všechno přehledně a bez zbytečného hledání.
            </p>

            <ul class="splash-feature-list">
                <li><i data-lucide="users" style="width:16px; height:16px; color:#f6d899;"></i> Přehled klientek a jejich návštěv na jednom místě</li>
                <li><i data-lucide="beaker" style="width:16px; height:16px; color:#f6d899;"></i> Receptury, misky a poznámky pro každodenní práci</li>
                <li><i data-lucide="smartphone" style="width:16px; height:16px; color:#f6d899;"></i> Desktop i mobil nad stejnou databází</li>
            </ul>

            <div class="splash-metrics">
                <div class="metric-card">
                    <span class="metric-label">Přístup</span>
                    <div class="metric-value">Zabezpečené přihlášení</div>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Režim</span>
                    <div class="metric-value">Desktop + PWA</div>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Workflow</span>
                    <div class="metric-value">Rychlý provoz salonu</div>
                </div>
            </div>
        </div>

        <div class="splash-bottom">
            Přihlášení chrání přístup ke kartám klientek, historii návštěv i salonním datům. Pro ostrý provoz doporučujeme běh přes HTTPS.
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="logo-area">
                <i data-lucide="shield-check" style="width:32px; height:32px;"></i>
            </div>

            <div class="eyebrow">
                <i data-lucide="lock" style="width:13px; height:13px;"></i>
                Zabezpečený vstup
            </div>

            <?php if ($user_count == 0): ?>
                <h1>Prvotní nastavení</h1>
                <p class="intro">Vytvořte první administrátorský účet a otevřete si přístup do celé KARTY.</p>
            <?php else: ?>
                <h1>Vítejte zpět</h1>
                <p class="intro">Přihlaste se a pokračujte rovnou do salonního přehledu.</p>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
            <?php if ($show_http_notice): ?>
                <div class="alert warning">Lokální test běží přes HTTP, takže Chrome na mobilu může ukázat upozornění na nezabezpečené odeslání. Pro ostrý provoz použij HTTPS.</div>
            <?php endif; ?>

            <div class="form-shell">
                <form method="POST" autocomplete="on">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label>Uživatelské jméno</label>
                        <input type="text" name="username" placeholder="Např. salon" required autofocus autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>Heslo</label>
                        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-login">
                        <i data-lucide="<?= ($user_count == 0) ? 'user-plus' : 'log-in' ?>" style="width:20px; height:20px;"></i>
                        <?= ($user_count == 0) ? 'Vytvořit účet' : 'Přihlásit se' ?>
                    </button>
                </form>
            </div>

            <div class="login-footer">
                Po přihlášení pokračujete přímo do desktopové nebo mobilní verze podle zařízení.
            </div>

            <div class="version-badge">
                <i data-lucide="gem" style="width:13px; height:13px;"></i>
                Luxury Salon System v2.0
            </div>
        </div>
    </section>
</main>

<script>lucide.createIcons();</script>
</body>
</html>
