<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) {
    redirect('dashboard/index.php');
}

$error = null;

// Handle direct access with token — auto-login
$token = trim($_GET['t'] ?? '');

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE qr_token = ? AND status = "Active" LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'user_id'  => (int)$user['user_id'],
            'username' => $user['username'],
            'full_name'=> $user['full_name'],
            'gmail'    => $user['gmail'] ?? '',
            'role'     => $user['role'],
        ];

        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE users SET otp_verified_at = ? WHERE user_id = ?')->execute([$now, $user['user_id']]);

        audit('LOGIN', 'auth', "User logged in via QR code ({$user['username']})");
        redirect('auth/loading.php');
    } else {
        $error = 'Invalid or expired QR code.';
    }
}

$company = settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code Login — <?= e($company['company_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app-extra.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-brand">
        <div class="big-logo"><?= icon('shield', '36') ?></div>
        <h1>QR Code <em>Login</em></h1>
        <p>Point your camera at a QR code to log in securely.</p>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <h2>Scan QR Code</h2>
            <p class="sub">Position the QR code within the frame</p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:14px;"><?= e($error) ?></div>
            <?php endif; ?>

            <div id="qr-reader" style="width:100%;max-width:350px;margin:0 auto;border:2px solid #dee2e6;border-radius:12px;background:#fff;min-height:200px;overflow:hidden;"></div>
            <div id="qr-status" style="color:#6c757d;font-size:14px;margin-top:12px;text-align:center;">Initializing scanner...</div>

            <div style="text-align:center;margin-top:20px;font-size:12px;">
                <a href="<?= BASE_URL ?>auth/login.php" style="color:#6366f1;text-decoration:none;">Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function() {
    var statusEl = document.getElementById('qr-status');
    var scanner = new Html5QrcodeScanner('qr-reader', {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    });

    scanner.render(
        function(decodedText) {
            statusEl.innerHTML = '<span style="color:#22c55e;">QR detected! Logging in...</span>';
            scanner.clear();
            // The QR code contains the full URL — navigate to it
            window.location.href = decodedText;
        },
        function(error) {
            // scanning in progress, keep going
        }
    );
})();
</script>
</body>
</html>
