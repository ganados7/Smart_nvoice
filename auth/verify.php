<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

if (current_user()) {
    redirect('dashboard/index.php');
}

$pending = $_SESSION['pending_user'] ?? null;
if (!$pending || empty($_SESSION['otp_expires'])) {
    redirect('auth/login.php');
}
$masked = mask_email((string)($pending['gmail'] ?? ''));

$error = null;
$info = null;

/* Resend request (e.g. ?resend=1) */
if (isset($_GET['resend'])) {
    $lastSent = (int)($_SESSION['otp_last_sent'] ?? 0);
    $wait = $lastSent + OTP_RESEND_SECONDS - time();
    if ($wait > 0) {
        $error = 'Please wait ' . $wait . 's before requesting a new code.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$pending['user_id']]);
        $user = $stmt->fetch();
        $newCode = create_login_otp($user);
            $_SESSION['otp_expires'] = time() + OTP_EXPIRY_SECONDS;
            $_SESSION['otp_last_sent'] = time();
            $_SESSION['otp_display'] = $newCode;
            $info = 'A new code has been generated.';
            audit('OTP_SENT', 'auth', 'Login code resent');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = trim($_POST['code'] ?? '');
    if (time() > (int)$_SESSION['otp_expires']) {
        $error = 'This code has expired. Please log in again to receive a new one.';
    } elseif ($code === '' || strlen($code) !== 6) {
        $error = 'Please enter the 6-digit code.';
    } else {
        $ok = verify_login_otp($pending, $code);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user'] = $pending;
            $_SESSION['otp_verified_at'] = time();
            unset($_SESSION['pending_user'], $_SESSION['otp_expires'], $_SESSION['otp_last_sent'], $_SESSION['otp_display']);
            audit('LOGIN', 'auth', 'User logged in (OTP verified)');
            redirect('dashboard/index.php');
        }
        $error = 'Invalid or expired code. Please try again.';
    }
}

$company = settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Login — <?= e($company['company_name']) ?></title>
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
        <h1>Two-step <em>verification</em></h1>
        <p>Enter the 6-digit code we sent to your Gmail to finish signing in securely.</p>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <h2>Check your inbox</h2>
            <p class="sub">We sent a login code to <b><?= e($masked) ?></b></p>

            <?php foreach (get_flash() as $f): ?>
                <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>" style="margin-bottom:14px;"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:14px;"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert alert-success" style="margin-bottom:14px;"><?= e($info) ?></div>
            <?php endif; ?>

            <?php $otpCode = $_SESSION['otp_display'] ?? null; if ($otpCode): ?>
                <div style="background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#60a5fa;border-radius:10px;padding:14px 16px;margin-bottom:14px;text-align:center;">
                    <div style="font-size:11px;color:#8fa3c2;margin-bottom:6px;">Your login code</div>
                    <div style="font-size:32px;font-weight:800;letter-spacing:8px;color:#3b82f6;"><?= e($otpCode) ?></div>
                    <div style="font-size:11px;color:#8fa3c2;margin-top:6px;">Expires in <?= (int)(OTP_EXPIRY_SECONDS / 60) ?> minutes</div>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="code">6-Digit Code</label>
                    <input class="input" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus style="font-size:26px;letter-spacing:10px;text-align:center;font-weight:700;">
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Verify &amp; Sign In <?= icon('shield', '16') ?></button>
            </form>
            <div class="row" style="justify-content:space-between;margin-top:18px;font-size:12.5px;">
                <a href="<?= BASE_URL ?>auth/verify.php?resend=1" style="color:var(--accent-1);">Resend code</a>
                <a href="<?= BASE_URL ?>auth/login.php" class="text-muted">Cancel</a>
            </div>
            <div class="auth-foot">© <?= date('Y') ?> <?= e($company['company_name']) ?></div>
        </div>
    </div>
</div>
</body>
</html>