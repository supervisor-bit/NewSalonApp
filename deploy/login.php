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

// Zkontrolujeme, zda v DB existuje alespoň jeden uživatel
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$user_count = $stmt->fetchColumn();

$error = "";
$success = "";

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
            header("Location: index.php");
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
    <title>Přihlášení | Profi Karta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #c5a059;
            --primary-dark: #a68545;
            --bg-rail: #0f172a;
            --text: #1e293b;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: var(--text);
        }
        .login-card {
            background: #fff;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: fadein 0.5s ease-out;
        }
        @keyframes fadein { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .logo-area {
            background: var(--bg-rail);
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--primary);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2);
        }
        h1 { font-family: 'Outfit', sans-serif; font-size: 24px; margin-bottom: 8px; font-weight: 700; }
        p { color: #64748b; font-size: 14px; margin-bottom: 30px; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            box-sizing: border-box;
            transition: 0.2s;
            font-family: inherit;
        }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.1); }
        
        .btn-login {
            background: var(--bg-rail);
            color: #fff;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-login:hover { background: #1e293b; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.15); }
        
        .error { background: #fee2e2; color: #ef4444; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; font-weight: 600; }
        .success { background: #d1fae5; color: #10b981; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-area">
        <i data-lucide="shield-check" style="width:32px; height:32px;"></i>
    </div>
    
    <?php if ($user_count == 0): ?>
        <h1>Prvotní nastavení</h1>
        <p>Vítejte! Vytvořte si svůj první administrátorský účet pro přístup do karty.</p>
    <?php else: ?>
        <h1>Vítejte zpět</h1>
        <p>Zadejte své údaje pro přístup do salonu.</p>
    <?php endif; ?>

    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Uživatelské jméno</label>
            <input type="text" name="username" placeholder="Např. salon" required autofocus>
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">
            <i data-lucide="<?= ($user_count == 0) ? 'user-plus' : 'log-in' ?>" style="width:20px; height:20px;"></i>
            <?= ($user_count == 0) ? 'Vytvořit účet' : 'Přihlásit se' ?>
        </button>
    </form>
    
    <div style="margin-top:24px; font-size:12px; color:#cbd5e1; font-weight:600; text-transform:uppercase; letter-spacing:1px;">
        Luxury Salon System v2.0
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>
