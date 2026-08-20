<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$paymentId = (int)($_GET['payment_id'] ?? 0);
if ($paymentId === 0) redirect('payments/index.php');

$stmt = $pdo->prepare(
    'SELECT p.*, r.receipt_number, r.receipt_date, i.invoice_number, i.total_amount, i.amount_paid, c.customer_name, c.address
     FROM payments p
     JOIN receipts r ON r.payment_id = p.payment_id
     JOIN invoices i ON i.invoice_id = p.invoice_id
     JOIN customers c ON c.customer_id = i.customer_id
     WHERE p.payment_id = ?'
);
$stmt->execute([$paymentId]);
$p = $stmt->fetch();
if (!$p) { set_flash('error', 'Receipt not found.'); redirect('payments/index.php'); }

$settings = settings();
$page_title = 'Receipt ' . $p['receipt_number'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <div>
        <h2 style="font-size:20px;">Receipt #<?= e($p['receipt_number']) ?></h2>
        <p class="text-muted text-sm"><?= status_badge('Verified') ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>payments/index.php"><?= icon('x', '16') ?> Back</a>
        <button class="btn btn-surface" onclick="window.print()"><?= icon('printer', '16') ?> Print Receipt</button>
    </div>
</div>

<div class="print-area">
    <div class="card" style="max-width:560px;margin:20px auto;">
        <div class="card-body" style="padding:30px;">
            <div class="doc" style="text-align:center;">
                <div class="brand-logo" style="margin:0 auto 12px;"><?= icon('receipt', '20') ?></div>
                <div class="doc-title" style="font-size:20px;"><?= e($settings['company_name']) ?></div>
                <p class="text-sm text-muted"><?= e($settings['company_address']) ?><br>TIN: <?= e($settings['company_tin']) ?><br><?= e($settings['company_phone']) ?></p>

                <div class="doc-divider" style="border-top:1px dashed #cbd5e1;margin:18px 0;"></div>

                <p><span class="text-muted text-sm">OFFICIAL RECEIPT</span><br>
                    <span class="num" style="font-family:var(--font-head);font-size:22px;font-weight:700;color:var(--accent-2);">#<?= e($p['receipt_number']) ?></span></p>

                <div style="text-align:left;margin:18px 0;font-size:13.5px;">
                    <div style="display:flex;justify-content:space-between;padding:4px 0;"><span class="text-muted">Received from</span><b><?= e($p['customer_name']) ?></b></div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;"><span class="text-muted">Invoice #</span><b class="mono">#<?= e($p['invoice_number']) ?></b></div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;"><span class="text-muted">Date</span><b><?= format_datetime($p['receipt_date']) ?></b></div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;"><span class="text-muted">Method</span><b><?= e($p['payment_method']) ?></b></div>
                    <?php if (!empty($p['payment_reference'])): ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;"><span class="text-muted">Reference</span><b class="mono"><?= e($p['payment_reference']) ?></b></div>
                    <?php endif; ?>
                </div>

                <div style="border-top:1px dashed #cbd5e1;border-bottom:1px dashed #cbd5e1;padding:14px 0;font-family:var(--font-head);">
                    <div style="display:flex;justify-content:space-between;">
                        <span>AMOUNT RECEIVED</span>
                        <span style="font-size:22px;font-weight:800;color:var(--accent-2);"><?= format_peso((float)$p['amount']) ?></span>
                    </div>
                </div>

                <p style="margin-top:20px;font-size:11.5px;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Amount in words</p>
                <p style="font-size:13px;font-style:italic;"><?= e(strtoupper(numberToWords($p['amount']))) ?> PESOS ONLY</p>

                <div class="doc-footer" style="margin-top:26px;"><?= e($settings['invoice_footer']) ?></div>
            </div>
        </div>
    </div>
</div>

<?php
function numberToWords(float $number): string
{
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter('en_PH', NumberFormatter::SPELLOUT);
        $words = trim($f->format($number));
        if ($words !== '') return $words;
    }
    return '—';
}
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>