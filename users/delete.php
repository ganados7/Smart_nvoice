<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('users/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT username FROM users WHERE user_id=?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { set_flash('error', 'User not found.'); redirect('users/index.php'); }

$stmt = $pdo->prepare('SELECT COUNT(*) c FROM invoices WHERE created_by=?');
$stmt->execute([$id]);
if ((int)$stmt->fetch()['c'] > 0) {
    set_flash('error', 'Cannot delete a user who issued invoices. Set them to Inactive instead.');
    redirect('users/index.php');
}
$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
audit('DELETE', 'users', "Deleted user '{$u['username']}'");
set_flash('success', 'User deleted.');
redirect('users/index.php');