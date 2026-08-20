<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) {
    redirect('dashboard/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] === 'Active') {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'user_id'   => (int)$user['user_id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
            ];
            audit('LOGIN', 'auth', 'User logged in');
            redirect('dashboard/index.php');
        }
        $error = 'Invalid username or password.';
    }
}
$company = settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($company['company_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app-extra.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-brand">
        <div class="big-logo"><?= icon('zap', '36') ?></div>
        <h1>Smart <em>E-Invoicing</em> &amp; Accounting System</h1>
        <p>Streamline your invoicing, track payments, and keep your books balanced — all in one sleek, powered-up dashboard built for medium-sized enterprises.</p>
        <p class="text-sm text-muted" style="margin-top:28px;">
            Modules: Invoices · Payments · General Ledger · Inventory · Reports
        </p>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <h2>Welcome back 👋</h2>
            <p class="sub">Sign in to access your account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Sign In <?= icon('login', '16') ?></button>
            </form>
            <div class="auth-hint">Default account: <b>admin</b> / <b>admin123</b></div>
            <div class="auth-foot">© <?= date('Y') ?> <?= e($company['company_name']) ?></div>
        </div>
    </div>
</div>
</body>
</html>