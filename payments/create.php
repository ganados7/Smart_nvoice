<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'Cash';
    $reference = trim($_POST['payment_reference'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE invoice_id=? AND invoice_status="Issued"');
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch();
    if (!$inv || $amount <= 0) {
        set_flash('error', 'Invalid invoice or amount.');
        redirect('payments/index.php');
    }
    $balance = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
    $amount = min($amount, $balance); /* cap at balance */

    try {
        $pdo->beginTransaction();

        /* insert payment */
        $stmt = $pdo->prepare(
            'INSERT INTO payments (invoice_id, payment_method, amount, payment_reference, payment_status, created_by) VALUES (?,?,?,?,\'Verified\',?)'
        );
        $stmt->execute([$invoiceId, $method, $amount, $reference, current_user()['user_id']]);
        $paymentId = (int)$pdo->lastInsertId();

        /* receipt */
        $receiptNo = next_receipt_number();
        $pdo->prepare('INSERT INTO receipts (payment_id, receipt_number) VALUES (?,?)')->execute([$paymentId, $receiptNo]);

        /* update invoice */
        $newPaid = (float)$inv['amount_paid'] + $amount;
        $status = $newPaid >= (float)$inv['total_amount'] ? 'Paid' : 'Partial';
        $pdo->prepare('UPDATE invoices SET amount_paid=?, payment_status=? WHERE invoice_id=?')
            ->execute([$newPaid, $status, $invoiceId]);

        /* double-entry: DR Cash, CR Accounts Receivable */
        post_ledger([
            ['account_title' => 'Cash', 'debit' => $amount, 'credit' => 0],
            ['account_title' => 'Accounts Receivable', 'debit' => 0, 'credit' => $amount],
        ], $invoiceId, $paymentId, null, 'Payment ' . $receiptNo);

        $pdo->commit();
        audit('CREATE', 'payments', "Payment $receiptNo " . number_format($amount, 2) . " for invoice #{$inv['invoice_number']}");
        set_flash('success', "Payment of " . number_format($amount, 2) . " recorded. Receipt #$receiptNo");
        redirect("payments/receipt.php?payment_id=$paymentId");
    } catch (Throwable $e) {
        $pdo->rollBack();
        set_flash('error', 'Failed to record payment: ' . $e->getMessage());
    }
}

$page_title = 'Record Payment';
require_once __DIR__ . '/../includes/header.php';

$presetInvoice = (int)($_GET['invoice_id'] ?? 0);
$presetAmount = (float)($_GET['amount'] ?? 0);

$invoices = $pdo->query(
    "SELECT i.invoice_id, i.invoice_number, i.total_amount, i.amount_paid, c.customer_name
     FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
     WHERE i.invoice_status='Issued' AND i.payment_status != 'Paid'
     ORDER BY i.invoice_id DESC"
)->fetchAll();
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <div class="card-head"><h2>Record Payment</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>payments/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field" style="grid-column:1/-1;">
                    <label for="invoice_id">Invoice <span class="req">*</span></label>
                    <select class="select" id="invoice_id" name="invoice_id" required>
                        <option value="">— Select invoice —</option>
                        <?php foreach ($invoices as $i): $bal = (float)$i['total_amount'] - (float)$i['amount_paid']; ?>
                            <option value="<?= $i['invoice_id'] ?>" data-balance="<?= $bal ?>" <?= $i['invoice_id'] === $presetInvoice ? 'selected' : '' ?>>
                                #<?= e($i['invoice_number']) ?> — <?= e($i['customer_name']) ?> (<?= format_peso($bal) ?> due)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="amount">Amount (₱) <span class="req">*</span></label>
                    <input class="input" type="number" step="0.01" min="0.01" id="amount" name="amount" value="<?= $presetAmount > 0 ? number_format($presetAmount, 2, '.', '') : '' ?>" required>
                </div>
                <div class="field">
                    <label for="payment_method">Payment Method</label>
                    <select class="select" id="payment_method" name="payment_method">
                        <?php foreach (['Cash', 'Bank Transfer', 'GCash', 'QR Payment', 'Check', 'Credit Card'] as $m): ?>
                            <option value="<?= $m ?>"><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label for="payment_reference">Reference No. (optional)</label>
                    <input class="input" id="payment_reference" name="payment_reference" placeholder="e.g. GCash ref / check no.">
                </div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Record &amp; Print Receipt</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>payments/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
const amt = document.getElementById('amount');
if (amt.value === '') amt.value = document.querySelector('#invoice_id option:checked')?.dataset?.balance || '';
document.getElementById('invoice_id').addEventListener('change', e => {
    amt.value = e.target.selectedOptions[0]?.dataset?.balance || '';
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>