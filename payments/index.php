<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$page_title = 'Payments';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$stmt = !$q ? $pdo->query(
    "SELECT p.*, r.receipt_number, i.invoice_number, i.total_amount, i.amount_paid, c.customer_name
     FROM payments p
     JOIN invoices i ON i.invoice_id = p.invoice_id
     LEFT JOIN receipts r ON r.payment_id = p.payment_id
     JOIN customers c ON c.customer_id = i.customer_id
     ORDER BY p.payment_id DESC"
) : null;
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*, r.receipt_number, i.invoice_number, i.total_amount, i.amount_paid, c.customer_name
         FROM payments p
         JOIN invoices i ON i.invoice_id = p.invoice_id
         LEFT JOIN receipts r ON r.payment_id = p.payment_id
         JOIN customers c ON c.customer_id = i.customer_id
         WHERE i.invoice_number LIKE :q OR c.customer_name LIKE :q OR p.payment_reference LIKE :q
         ORDER BY p.payment_id DESC"
    );
    $stmt->execute([':q' => "%$q%"]);
}
$rows = $stmt->fetchAll();
$total_collected = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='Verified'")->fetch()['s'];
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Payments</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> payment(s) · total collected: <b><?= format_peso($total_collected) ?></b></p>
    </div>
    <div class="actions">
        <div class="search-box"><?= icon('search', '16') ?>
            <input class="input" type="text" data-table-search="#paymentsTable" placeholder="Search payments...">
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>payments/create.php"><?= icon('plus', '16') ?> Record Payment</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('wallet') ?></div><h3>No payments recorded</h3><p>Record a payment against an invoice.</p></div>
        <?php else: ?>
        <table class="table" id="paymentsTable">
            <thead><tr><th>Receipt</th><th>Invoice</th><th>Customer</th><th>Method</th><th>Reference</th><th>Date</th><th class="num" style="text-align:right">Amount</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono strong"><?= e($r['receipt_number'] ?? '—') ?></td>
                    <td class="mono">#<?= e($r['invoice_number']) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><span class="badge badge-sky"><?= e($r['payment_method']) ?></span></td>
                    <td class="muted mono"><?= e($r['payment_reference'] ?? '—') ?></td>
                    <td class="muted"><?= format_date($r['payment_date'], 'M d, Y h:i A') ?></td>
                    <td class="num" style="text-align:right;color:#34d399;"><?= format_peso((float)$r['amount']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>payments/receipt.php?payment_id=<?= $r['payment_id'] ?>" title="Receipt"><?= icon('printer', '16') ?></a>
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>invoices/view.php?id=<?= $r['invoice_id'] ?>" title="Invoice"><?= icon('eye', '16') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>