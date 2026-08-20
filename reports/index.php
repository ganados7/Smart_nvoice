<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';

$tab = $_GET['tab'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

$qfrom = $pdo->quote($from);
$qto = $pdo->quote($to);

/* ---------------- SALES REPORT ---------------- */
$sales = null;
if ($tab === 'sales') {
    $sales = $pdo->query(
        "SELECT i.invoice_number, c.customer_name, i.invoice_date, i.subtotal, i.tax_amount, i.discount,
                i.total_amount, i.amount_paid, (i.total_amount - i.amount_paid) AS balance, i.payment_status
         FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
         WHERE i.invoice_status='Issued' AND DATE(i.invoice_date) BETWEEN $qfrom AND $qto
         ORDER BY i.invoice_id"
    )->fetchAll();
    $salesTot = array_sum(array_map(fn($r) => (float)$r['total_amount'], $sales));
    $salesPaid = array_sum(array_map(fn($r) => (float)$r['amount_paid'], $sales));
    $salesTax = array_sum(array_map(fn($r) => (float)$r['tax_amount'], $sales));
}

/* ---------------- EXPENSE REPORT ---------------- */
$expenses = $pdo->query(
    "SELECT * FROM expenses WHERE expense_date BETWEEN $qfrom AND $qto ORDER BY expense_date"
)->fetchAll();
$expByCat = $pdo->query(
    "SELECT category, SUM(amount) AS total FROM expenses WHERE expense_date BETWEEN $qfrom AND $qto GROUP BY category ORDER BY total DESC"
)->fetchAll();
$expTotal = array_sum(array_map(fn($r) => (float)$r['amount'], $expenses));

/* ---------------- INCOME STATEMENT ---------------- */
$revenue = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) AS v FROM invoices WHERE invoice_status='Issued' AND DATE(invoice_date) BETWEEN $qfrom AND $qto"
)->fetch()['v'];
$salesTax = (float)$pdo->query(
    "SELECT COALESCE(SUM(tax_amount),0) AS v FROM invoices WHERE invoice_status='Issued' AND DATE(invoice_date) BETWEEN $qfrom AND $qto"
)->fetch()['v'];
$netIncome = $revenue - $expTotal;

/* ---------------- BALANCE SHEET (simplified) ---------------- */
$cash = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE payment_status='Verified'")->fetch()['v'];
$ar = (float)$pdo->query(
    "SELECT COALESCE(SUM(GREATEST(total_amount - amount_paid,0)),0) AS v FROM invoices WHERE invoice_status='Issued' AND payment_status IN ('Pending','Partial','Overdue')"
)->fetch()['v'];
$inventory = (float)$pdo->query("SELECT COALESCE(SUM(quantity_in_stock * cost_price),0) AS v FROM products WHERE is_service=0")->fetch()['v'];
$assets = $cash + $ar + $inventory;
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Financial Reports</h2>
        <p class="text-muted text-sm">Period: <b><?= format_date($from) ?></b> to <b><?= format_date($to) ?></b></p>
    </div>
    <div class="actions">
        <form method="GET" class="row" style="gap:8px;align-items:center;">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:auto;">
            <span class="text-muted">to</span>
            <input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:auto;">
            <button class="btn btn-surface btn-sm" type="submit"><?= icon('refresh', '16') ?> Apply</button>
        </form>
        <?php if ($tab === 'sales'): ?>
            <a class="btn btn-ghost" href="<?= BASE_URL ?>invoices/export.php"><?= icon('download', '16') ?> Export CSV</a>
        <?php endif; ?>
        <button class="btn btn-surface" onclick="window.print()"><?= icon('printer', '16') ?> Print</button>
    </div>
</div>

<div class="card mb-2">
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $tabs = [['sales', 'Sales Report'], ['expenses', 'Expenses'], ['income', 'Income Statement'], ['balance', 'Balance Sheet']];
        foreach ($tabs as [$k, $l]):
            $url = BASE_URL . 'reports/index.php?tab=' . $k . '&from=' . urlencode($from) . '&to=' . urlencode($to);
        ?>
            <a class="btn <?= $tab === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= $url ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="print-area">
<?php if ($tab === 'sales'): ?>
    <div class="card">
        <div class="card-body" style="padding:30px;">
            <div class="doc-header" style="border-bottom:1px solid var(--border);padding-bottom:16px;">
                <div>
                    <div class="doc-title"><span>REPORT</span>Sales Report</div>
                    <p class="text-sm text-muted"><?= format_date($from) ?> — <?= format_date($to) ?></p>
                </div>
                <div class="kpi" style="flex-direction:column;align-items:flex-end;gap:2px;border:none;box-shadow:none;background:transparent;">
                    <div class="k-label">Gross Revenue</div>
                    <div class="k-value" style="font-size:24px;"><?= format_peso($salesTot) ?></div>
                    <div class="k-sub"><?= count($sales) ?> invoices · <?= format_peso($salesPaid) ?> collected</div>
                </div>
            </div>
            <table class="table mt-2">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th class="num" style="text-align:right">Subtotal</th><th class="num" style="text-align:right">Tax</th><th class="num" style="text-align:right">Total</th><th class="num" style="text-align:right">Balance</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $r): ?>
                    <tr>
                        <td class="mono strong">#<?= e($r['invoice_number']) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td class="muted"><?= format_date($r['invoice_date']) ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$r['subtotal']) ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$r['tax_amount']) ?></td>
                        <td class="num" style="text-align:right"><b><?= format_peso((float)$r['total_amount']) ?></b></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$r['balance']) ?></td>
                        <td><?= status_badge($r['payment_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($tab === 'expenses'): ?>
    <div class="card">
        <div class="card-body" style="padding:30px;">
            <div class="doc-header" style="border-bottom:1px solid var(--border);padding-bottom:16px;">
                <div>
                    <div class="doc-title"><span>REPORT</span>Expense Report</div>
                    <p class="text-sm text-muted"><?= format_date($from) ?> — <?= format_date($to) ?></p>
                </div>
                <div class="kpi" style="flex-direction:column;align-items:flex-end;gap:2px;border:none;box-shadow:none;background:transparent;">
                    <div class="k-label">Total Expenses</div>
                    <div class="k-value" style="font-size:24px;color:#fb7185;"><?= format_peso($expTotal) ?></div>
                </div>
            </div>
            <table class="table mt-2">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Vendor</th><th class="num" style="text-align:right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $r): ?>
                    <tr>
                        <td class="muted"><?= format_date($r['expense_date']) ?></td>
                        <td><span class="badge badge-sky"><?= e($r['category']) ?></span></td>
                        <td><?= e($r['description'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['vendor'] ?? '—') ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$r['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($tab === 'income'): ?>
    <div class="card" style="max-width:620px;margin:0 auto;">
        <div class="card-body" style="padding:34px;">
            <div class="doc-title" style="text-align:center;margin-bottom:4px;"><span>FINANCIAL STATEMENT</span>Income Statement</div>
            <p class="text-sm text-muted" style="text-align:center;">For the period <?= format_date($from) ?> — <?= format_date($to) ?></p>
            <div class="divider"></div>
            <table class="table">
                <tbody>
                    <tr><td class="strong" style="font-size:14px;">REVENUE</td><td style="text-align:right"></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Sales Revenue (issued invoices)</td><td class="num" style="text-align:right"><?= format_peso($revenue) ?></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Less: Output VAT included</td><td class="num" style="text-align:right">− <?= format_peso($salesTax) ?></td></tr>
                    <tr><td class="strong" style="padding-top:10px;">Total Revenue</td><td class="num strong" style="text-align:right;padding-top:10px;"><?= format_peso($revenue) ?></td></tr>
                    <tr><td colspan="2" style="padding:0;height:12px;"></td></tr>
                    <tr><td class="strong" style="font-size:14px;">EXPENSES</td><td style="text-align:right"></td></tr>
                    <?php foreach ($expByCat as $c): ?>
                        <tr><td class="text-muted" style="padding-left:26px;"><?= e($c['category']) ?></td><td class="num" style="text-align:right"><?= format_peso((float)$c['total']) ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="strong" style="padding-top:10px;">Total Expenses</td><td class="num strong" style="text-align:right;padding-top:10px;"><?= format_peso($expTotal) ?></td></tr>
                </tbody>
            </table>
            <div style="margin-top:24px;padding:16px 18px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);display:flex;justify-content:space-between;align-items:center;">
                <b style="font-size:15px;">NET INCOME</b>
                <span class="num" style="font-size:22px;font-weight:800;color:<?= $netIncome >= 0 ? '#34d399' : '#fb7185' ?>;"><?= format_peso($netIncome) ?></span>
            </div>
        </div>
    </div>
<?php elseif ($tab === 'balance'): ?>
    <div class="card" style="max-width:620px;margin:0 auto;">
        <div class="card-body" style="padding:34px;">
            <div class="doc-title" style="text-align:center;margin-bottom:4px;"><span>FINANCIAL STATEMENT</span>Balance Sheet</div>
            <p class="text-sm text-muted" style="text-align:center;">As of <?= format_date($to) ?></p>
            <div class="divider"></div>
            <table class="table">
                <tbody>
                    <tr><td class="strong" style="font-size:14px;">ASSETS</td><td style="text-align:right"></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Cash &amp; Bank (collected payments)</td><td class="num" style="text-align:right"><?= format_peso($cash) ?></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Accounts Receivable (outstanding)</td><td class="num" style="text-align:right"><?= format_peso($ar) ?></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Inventory (at cost)</td><td class="num" style="text-align:right"><?= format_peso($inventory) ?></td></tr>
                    <tr><td class="strong" style="padding-top:10px;">Total Assets</td><td class="num strong" style="text-align:right;padding-top:10px;"><?= format_peso($assets) ?></td></tr>
                    <tr><td colspan="2" style="padding:0;height:12px;"></td></tr>
                    <tr><td class="strong" style="font-size:14px;">LIABILITIES &amp; EQUITY</td><td style="text-align:right"></td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Accounts Payable</td><td class="num" style="text-align:right">₱0.00</td></tr>
                    <tr><td class="text-muted" style="padding-left:26px;">Owner's Equity (net assets)</td><td class="num" style="text-align:right"><?= format_peso($assets) ?></td></tr>
                    <tr><td class="strong" style="padding-top:10px;">Total Liabilities &amp; Equity</td><td class="num strong" style="text-align:right;padding-top:10px;"><?= format_peso($assets) ?></td></tr>
                </tbody>
            </table>
            <p class="text-sm text-muted mt-2" style="text-align:center;">* Simplified statement — AP module not yet implemented.</p>
        </div>
    </div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>