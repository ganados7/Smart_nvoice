<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$token = trim($_GET['t'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

// Look up user by QR token
$stmt = $pdo->prepare('SELECT * FROM users WHERE qr_token = ? AND status = "Active" LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    // Token not found or user not active
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired QR code']);
    exit;
}

// Create session - this user is now logged in
session_regenerate_id(true);

$_SESSION['user'] = [
    'user_id'  => (int)$user['user_id'],
    'username' => $user['username'],
    'full_name'=> $user['full_name'],
    'gmail'    => $user['gmail'] ?? '',
    'role'     => $user['role'],
];

// Set OTP verified timestamp (same as password login)
$now = date('Y-m-d H:i:s');
$pdo->prepare('UPDATE users SET otp_verified_at = ? WHERE user_id = ?')->execute([$now, $user['user_id']]);

// Audit log
audit('LOGIN', 'auth', "User logged in via QR code ({$user['username']})");

// Success - redirect to dashboard
http_response_code(200);
echo json_encode(['success' => true, 'redirect' => 'dashboard/index.php']);
exit;