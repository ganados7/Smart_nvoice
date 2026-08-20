<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('invoices/index.php');

$stmt = $pdo->prepare(
    'SELECT i.*, c.customer_name, c.contact_number, c.email, c.address, u.full_name AS created_by_name
     FROM invoices i
     JOIN customers c ON c.customer_id = i.customer_id
     LEFT JOIN users u ON u.user_id = i.created_by
     WHERE i.invoice_id = ?'
);
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { set_flash('error', 'Invoice not found.'); redirect('invoices/index.php'); }

$items = $pdo->prepare(
    'SELECT ii.*, p.product_name, p.is_service FROM invoice_items ii
     JOIN products p ON p.product_id = ii.product_id WHERE ii.invoice_id = ?'
);
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $pdo->prepare(
    'SELECT p.*, r.receipt_number FROM payments p LEFT JOIN receipts r ON r.payment_id = p.payment_id
     WHERE p.invoice_id = ? ORDER BY p.payment_id'
);
$payments->execute([$id]);
$payments = $payments->fetchAll();

$settings = settings();
$page_title = 'Invoice #' . $inv['invoice_number'];
require_once __DIR__ . '/../includes/header.php';

$balance = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
?>
<div class="page-head no-print">
    <div>
        <h2 style="font-size:20px;">Invoice #<?= e($inv['invoice_number']) ?></h2>
        <p class="text-muted text-sm"><?= status_badge($inv['payment_status']) ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>invoices/index.php"><?= icon('x', '16') ?> Back</a>
        <button class="btn btn-surface" onclick="window.print()"><?= icon('printer', '16') ?> Print</button>
        <?php if ($inv['invoice_status'] === 'Issued' && $inv['payment_status'] !== 'Paid'): ?>
        <a class="btn btn-success" href="<?= BASE_URL ?>payments/create.php?invoice_id=<?= $id ?>&amount=<?= $balance ?>"><?= icon('wallet', '16') ?> Record Payment</a>
        <?php endif; ?>
    </div>
</div>

<div class="print-area">
    <div class="card">
        <div class="card-body" style="padding:34px;">
            <div class="doc">
                <div class="doc-header">
                    <div>
                        <div class="brand-logo" style="margin-bottom:12px;"><?= icon('zap', '20') ?></div>
                        <div class="doc-title"><span>INVOICE</span><?= e($settings['company_name']) ?></div>
                        <p class="text-sm text-muted" style="margin-top:6px;">
                            <?= e($settings['company_address']) ?><br>
                            TIN: <?= e($settings['company_tin']) ?> · <?= e($settings['company_phone']) ?><br>
                            <?= e($settings['company_email']) ?>
                        </p>
                    </div>
                    <div class="doc-no">
                        <span class="text-muted text-sm">INVOICE NO.</span><br>
                        <span class="num">#<?= e($inv['invoice_number']) ?></span><br>
                        <span class="text-muted text-sm"><?= status_badge($inv['payment_status']) ?></span>
                    </div>
                </div>

                <div class="doc-meta">
                    <div class="meta-box">
                        <h4>Bill To</h4>
                        <p><b><?= e($inv['customer_name']) ?></b><br>
                        <?= e($inv['address'] ?? '') ?><br>
                        <?= e($inv['contact_number'] ?? '') ?><br>
                        <?= e($inv['email'] ?? '') ?></p>
                    </div>
                    <div class="meta-box">
                        <h4>Invoice Details</h4>
                        <p>Date: <b><?= format_date($inv['invoice_date']) ?></b><br>
                        Due date: <b><?= format_date($inv['due_date']) ?></b><br>
                        Prepared by: <?= e($inv['created_by_name'] ?? '—') ?></p>
                    </div>
                </div>

                <table class="table">
                    <thead><tr><th>Item</th><th class="num" style="text-align:right">Qty</th><th class="num" style="text-align:right">Unit Price</th><th class="num" style="text-align:right">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><b><?= e($it['product_name']) ?></b><?= $it['is_service'] ? ' <span class="muted text-sm">(service)</span>' : '' ?></td>
                            <td class="num" style="text-align:right"><?= (int)$it['quantity'] ?></td>
                            <td class="num" style="text-align:right"><?= format_peso((float)$it['unit_price']) ?></td>
                            <td class="num" style="text-align:right"><?= format_peso((float)$it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="doc-total" style="background:var(--surface-2);border:1px solid var(--border);">
                    <div class="row"><span>Subtotal</span><span><?= format_peso((float)$inv['subtotal']) ?></span></div>
                    <?php if ((float)$inv['discount'] > 0): ?>
                    <div class="row"><span>Discount</span><span>− <?= format_peso((float)$inv['discount']) ?></span></div>
                    <?php endif; ?>
                    <?php if ((float)$inv['tax_amount'] > 0): ?>
                    <div class="row"><span>Tax</span><span><?= format_peso((float)$inv['tax_amount']) ?></span></div>
                    <?php endif; ?>
                    <div class="row grand"><span>Total</span><span class="num"><?= format_peso((float)$inv['total_amount']) ?></span></div>
                    <div class="row"><span>Paid</span><span><?= format_peso((float)$inv['amount_paid']) ?></span></div>
                    <div class="row" style="color:#fbbf24;"><span>Balance Due</span><span class="num"><?= format_peso($balance) ?></span></div>
                </div>

                <?php if (trim((string)$inv['notes'])): ?>
                    <p class="text-sm text-muted mt-2"><b>Notes:</b> <?= nl2br(e($inv['notes'])) ?></p>
                <?php endif; ?>

                <?php if ($payments): ?>
                <div class="mt-3">
                    <h4 class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;">Payment History</h4>
                    <table class="table">
                        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num" style="text-align:right">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="muted"><?= format_date($p['payment_date'], 'M d, Y') ?></td>
                                <td><?= e($p['payment_method']) ?></td>
                                <td class="muted mono"><?= e($p['payment_reference'] ?? '—') ?></td>
                                <td class="num" style="text-align:right"><?= format_peso((float)$p['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="doc-footer"><?= e($settings['invoice_footer']) ?></div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>