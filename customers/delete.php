<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('customers/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT customer_name FROM customers WHERE customer_id=?');
$stmt->execute([$id]);
$c = $stmt->fetch();

$used = $pdo->prepare("SELECT COUNT(*) AS c FROM invoices WHERE customer_id=?");
$used->execute([$id]);
if ((int)$used->fetch()['c'] > 0) {
    set_flash('error', 'Cannot delete customer with existing invoices. Mark them cancelled first.');
    redirect('customers/index.php');
}
$pdo->prepare('DELETE FROM customers WHERE customer_id=?')->execute([$id]);
audit('DELETE', 'customers', "Deleted customer #$id: " . ($c['customer_name'] ?? ''));
set_flash('success', 'Customer deleted.');
redirect('customers/index.php');