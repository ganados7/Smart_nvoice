<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

/**
 * Generate a cryptographically random 6-digit OTP code.
 */
function generate_otp(): string
{
    return (string) random_int(100000, 999999);
}

/**
 * Mask an email address, e.g. j****@gmail.com
 */
function mask_email(string $email): string
{
    $pos = strpos($email, '@');
    if ($pos === false) return $email;
    $local = substr($email, 0, $pos);
    $domain = substr($email, $pos);
    $shown = strlen($local) > 2 ? substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) : $local[0] . '***';
    return $shown . $domain;
}

/**
 * Generate and store a login OTP for the given user.
 * Returns the 6-digit code (to be displayed on screen).
 */
function create_login_otp(array $user): string
{
    global $pdo;
    $code = generate_otp();
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_SECONDS);

    $stmt = $pdo->prepare(
        'INSERT INTO login_codes (user_id, code, purpose, expires_at) VALUES (?,?,?,?)'
    );
    $stmt->execute([$user['user_id'], $code, 'login', $expires]);

    return $code;
}

/**
 * Validate a submitted OTP code against the latest stored login code for a user.
 * Returns true on success.
 */
function verify_login_otp(array $user, string $submitted): bool
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT * FROM login_codes
          WHERE user_id = ? AND purpose = "login" AND used = 0
          ORDER BY code_id DESC LIMIT 1'
    );
    $stmt->execute([$user['user_id']]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if (hash_equals($row['code'], trim($submitted)) && strtotime($row['expires_at']) > time()) {
        $pdo->prepare('UPDATE login_codes SET used = 1 WHERE code_id = ?')->execute([$row['code_id']]);
        return true;
    }
    return false;
}