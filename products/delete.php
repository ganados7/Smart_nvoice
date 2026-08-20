<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('products/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$used = $pdo->prepare("SELECT COUNT(*) AS c FROM invoice_items WHERE product_id=?");
$used->execute([$id]);
if ((int)$used->fetch()['c'] > 0) {
    set_flash('error', 'Cannot delete an item that was sold. Set it to Unavailable instead.');
    redirect('products/index.php');
}
$pdo->prepare('DELETE FROM products WHERE product_id=?')->execute([$id]);
audit('DELETE', 'products', "Deleted item #$id");
set_flash('success', 'Item deleted.');
redirect('products/index.php');