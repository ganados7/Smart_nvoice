<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

if (current_user()) {
    redirect('dashboard/index.php');
}

$error = null;
$info = null;
$verified = false;

/* Step 1: User submits email to look up their pending account */
if (isset($_POST['lookup'])) {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE gmail = ? AND status = "Pending" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['verify_user'] = [
                'user_id'  => (int)$user['user_id'],
                'username' => $user['username'],
                'full_name'=> $user['full_name'],
                'gmail'    => $user['gmail'] ?? '',
                'role'     => $user['role'],
            ];
            $_SESSION['verify_otp_expires'] = time() + OTP_EXPIRY_SECONDS;
            $_SESSION['verify_otp_last_sent'] = time();

            $otpCode = create_activation_otp($user);
            $_SESSION['verify_otp_display'] = $otpCode;

            send_otp_email($email, $otpCode, 'account_activation');
            audit('OTP_SENT', 'auth', "Activation code sent to $email");
            set_flash('success', 'A verification code has been sent to your email.');
        } else {
            $error = 'No pending account found with that email address.';
        }
    }
}

/* Step 2: User submits OTP code */
$verifyUser = $_SESSION['verify_user'] ?? null;
if (isset($_POST['verify_otp']) && $verifyUser) {
    csrf_check();
    $code = trim($_POST['code'] ?? '');

    if (time() > (int)($_SESSION['verify_otp_expires'] ?? 0)) {
        $error = 'This code has expired. Please request a new one.';
    } elseif ($code === '' || strlen($code) !== 6) {
        $error = 'Please enter the 6-digit code.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$verifyUser['user_id']]);
        $user = $stmt->fetch();

        if ($user && verify_activation_otp($user, $code)) {
            $pdo->prepare('UPDATE users SET status = "Active" WHERE user_id = ?')->execute([$user['user_id']]);
            unset($_SESSION['verify_user'], $_SESSION['verify_otp_expires'], $_SESSION['verify_otp_last_sent'], $_SESSION['verify_otp_display']);
            audit('ACTIVATE', 'auth', "Account activated for '{$user['username']}'");
            $verified = true;
        } else {
            $error = 'Invalid or expired code. Please try again.';
        }
    }
}

/* Resend OTP */
if (isset($_GET['resend']) && $verifyUser) {
    $lastSent = (int)($_SESSION['verify_otp_last_sent'] ?? 0);
    $wait = $lastSent + OTP_RESEND_SECONDS - time();
    if ($wait > 0) {
        $error = 'Please wait ' . $wait . 's before requesting a new code.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$verifyUser['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $newCode = create_activation_otp($user);
            $_SESSION['verify_otp_expires'] = time() + OTP_EXPIRY_SECONDS;
            $_SESSION['verify_otp_last_sent'] = time();
            $_SESSION['verify_otp_display'] = $newCode;

            $gmail = trim((string)($user['gmail'] ?? ''));
            if ($gmail !== '') {
                send_otp_email($gmail, $newCode, 'account_activation');
            }

            $info = 'A new code has been sent to your email.';
            audit('OTP_SENT', 'auth', 'Activation code resent');
        }
    }
}

$company = settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Account — <?= e($company['company_name']) ?></title>
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
        <h1>Account <em>Verification</em></h1>
        <p>Enter the 6-digit code sent to your email to activate your account and start using Smart Invoice.</p>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <?php if ($verified): ?>
                <h2>Account Activated</h2>
                <p class="sub">Your account has been verified successfully.</p>
                <div style="text-align:center;margin:24px 0;">
                    <?= icon('check', '48') ?>
                </div>
                <p class="text-sm text-muted" style="text-align:center;">You can now log in using your email and password.</p>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-lg" style="width:100%;margin-top:16px;text-align:center;">Go to Login <?= icon('login', '16') ?></a>
            <?php elseif (!$verifyUser): ?>
                <h2>Verify Your Account</h2>
                <p class="sub">Enter the email address associated with your account to receive a verification code.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom:14px;"><?= e($error) ?></div>
                <?php endif; ?>

                <?php foreach (get_flash() as $f): ?>
                    <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>" style="margin-bottom:14px;"><?= e($f['message']) ?></div>
                <?php endforeach; ?>

                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input class="input" type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
                    </div>
                    <button type="submit" name="lookup" value="1" class="btn btn-primary btn-lg" style="width:100%;">Send Verification Code <?= icon('shield', '16') ?></button>
                </form>
            <?php else: ?>
                <h2>Check your inbox</h2>
                <p class="sub">We sent a verification code to <b><?= e(mask_email((string)($verifyUser['gmail'] ?? ''))) ?></b></p>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom:14px;"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($info): ?>
                    <div class="alert alert-success" style="margin-bottom:14px;"><?= e($info) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="verify_otp" value="1">
                    <div class="field">
                        <label for="code">6-Digit Code</label>
                        <input class="input" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus style="font-size:26px;letter-spacing:10px;text-align:center;font-weight:700;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Verify &amp; Activate <?= icon('shield', '16') ?></button>
                </form>
                <div class="row" style="justify-content:space-between;margin-top:18px;font-size:12.5px;">
                    <a href="<?= BASE_URL ?>auth/verify_account.php?resend=1" style="color:var(--accent-1);">Resend code</a>
                    <a href="<?= BASE_URL ?>auth/verify_account.php" class="text-muted">Use different email</a>
                </div>
            <?php endif; ?>
            <div class="auth-foot">© <?= date('Y') ?> <?= e($company['company_name']) ?></div>
        </div>
    </div>
</div>
</body>
</html>
