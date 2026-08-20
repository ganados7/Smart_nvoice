<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('invoices/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM invoices WHERE invoice_id=?');
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { set_flash('error', 'Invoice not found.'); redirect('invoices/index.php'); }

$hasPayments = (int)$pdo->prepare('SELECT COUNT(*) c FROM payments WHERE invoice_id=?')->fetchColumn() === 0 ? false : true;
if ($hasPayments) {
    set_flash('error', 'Cannot cancel invoice with payments. Reverse the payments first.');
    redirect('invoices/index.php');
}

try {
    $pdo->beginTransaction();

    /* restore stock for physical items */
    $items = $pdo->prepare('SELECT product_id, quantity FROM invoice_items WHERE invoice_id=?');
    $items->execute([$id]);
    foreach ($items->fetchAll() as $it) {
        $prod = $pdo->prepare('SELECT is_service FROM products WHERE product_id=?');
        $prod->execute([$it['product_id']]);
        if ($p = $prod->fetch()) if (!$p['is_service']) {
            $pdo->prepare('UPDATE products SET quantity_in_stock = quantity_in_stock + ? WHERE product_id=?')
                ->execute([$it['quantity'], $it['product_id']]);
            $pdo->prepare('INSERT INTO inventory_transactions (product_id, invoice_id, transaction_type, quantity, note) VALUES (?,?,?,?,?)')
                ->execute([$it['product_id'], $id, 'IN', $it['quantity'], 'Cancelled invoice #' . $inv['invoice_number']]);
        }
    }

    /* reverse ledger entries */
    $entries = $pdo->prepare('SELECT * FROM general_ledger WHERE invoice_id=?');
    $entries->execute([$id]);
    foreach ($entries->fetchAll() as $e) {
        $pdo->prepare('INSERT INTO general_ledger (invoice_id, account_title, debit, credit, description) VALUES (?,?,?,?,?)')
            ->execute([$id, $e['account_title'], $e['credit'], $e['debit'], 'Reversal: cancelled invoice #' . $inv['invoice_number']]);
    }

    $pdo->prepare("UPDATE invoices SET invoice_status='Cancelled', payment_status='Cancelled' WHERE invoice_id=?")->execute([$id]);
    $pdo->commit();
    audit('CANCEL', 'invoices', 'Cancelled invoice #' . $inv['invoice_number']);
    set_flash('success', 'Invoice cancelled and ledger reversed.');
} catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('error', 'Failed to cancel: ' . $e->getMessage());
}
redirect('invoices/index.php');