<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('suppliers/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$pdo->prepare('DELETE FROM suppliers WHERE supplier_id=?')->execute([$id]);
audit('DELETE', 'suppliers', "Deleted supplier #$id");
set_flash('success', 'Supplier deleted.');
redirect('suppliers/index.php');