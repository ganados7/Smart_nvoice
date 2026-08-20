<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$year = date('Y');
$month = date('m');

$stats = [];

/* sales today */
$row = $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) AS sales_today, COUNT(*) AS inv_today
     FROM invoices WHERE invoice_status='Issued' AND DATE(invoice_date)=CURDATE()"
)->fetch();
$stats['sales_today'] = (float)$row['sales_today'];
$stats['inv_today'] = (int)$row['inv_today'];

/* sales this month */
$row = $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) AS sales_month
     FROM invoices WHERE invoice_status='Issued' AND YEAR(invoice_date)=$year AND MONTH(invoice_date)=$month"
)->fetch();
$stats['sales_month'] = (float)$row['sales_month'];

/* outstanding AR */
$row = $pdo->query(
    "SELECT COALESCE(SUM(GREATEST(total_amount - amount_paid,0)),0) AS ar
     FROM invoices WHERE invoice_status='Issued' AND payment_status IN ('Pending','Partial','Overdue')"
)->fetch();
$stats['outstanding'] = (float)$row['ar'];

/* paid this month */
$row = $pdo->query(
    "SELECT COALESCE(SUM(amount),0) AS collected
     FROM payments WHERE payment_status='Verified' AND YEAR(payment_date)=$year AND MONTH(payment_date)=$month"
)->fetch();
$stats['collected'] = (float)$row['collected'];

/* expenses this month */
$row = $pdo->query(
    "SELECT COALESCE(SUM(amount),0) AS exp_month
     FROM expenses WHERE YEAR(expense_date)=$year AND MONTH(expense_date)=$month"
)->fetch();
$stats['expenses'] = (float)$row['exp_month'];

/* low stock count */
$stats['low_stock'] = (int)$pdo->query(
    "SELECT COUNT(*) AS c FROM products WHERE is_service=0 AND status='Available' AND quantity_in_stock <= reorder_level"
)->fetch()['c'];

/* overdue count */
$stats['overdue'] = (int)$pdo->query(
    "SELECT COUNT(*) AS c FROM invoices
     WHERE invoice_status='Issued' AND payment_status IN ('Pending','Partial','Overdue')
       AND due_date IS NOT NULL AND due_date < CURDATE()"
)->fetch()['c'];

/* monthly revenue vs expenses (last 6 months) */
$chart = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = mktime(0,0,0, (int)$month - $i, 1, (int)$year);
    $m = date('m', $ts); $y = date('Y', $ts);
    $rev = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0) AS v FROM invoices WHERE invoice_status='Issued' AND YEAR(invoice_date)=:y AND MONTH(invoice_date)=:m"
    );
    $rev->execute([':y'=>$y, ':m'=>$m]);
    $exp = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE YEAR(expense_date)=:y AND MONTH(expense_date)=:m"
    );
    $exp->execute([':y'=>$y, ':m'=>$m]);
    $chart[] = [
        'label' => date('M', $ts),
        'rev'   => (float)$rev->fetch()['v'],
        'exp'   => (float)$exp->fetch()['v'],
    ];
}

/* recent invoices */
$recent = $pdo->query(
    "SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.total_amount, i.amount_paid, i.payment_status,
            c.customer_name
     FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
     WHERE i.invoice_status='Issued'
     ORDER BY i.invoice_id DESC LIMIT 8"
)->fetchAll();

/* overdue list */
$overdues = $pdo->query(
    "SELECT i.invoice_number, c.customer_name, i.due_date, GREATEST(i.total_amount - i.amount_paid,0) AS balance
     FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
     WHERE i.invoice_status='Issued' AND i.payment_status IN ('Pending','Partial','Overdue')
       AND i.due_date IS NOT NULL AND i.due_date < CURDATE()
     ORDER BY i.due_date LIMIT 6"
)->fetchAll();
?>
<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-icon grad-1"><?= icon('invoice') ?></div>
        <div><div class="k-label">Sales Today</div><div class="k-value"><?= format_peso($stats['sales_today']) ?></div><div class="k-sub"><?= $stats['inv_today'] ?> invoices issued</div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-2"><?= icon('trending') ?></div>
        <div><div class="k-label">Sales This Month</div><div class="k-value"><?= format_peso($stats['sales_month']) ?></div><div class="k-sub"><?= date('F Y') ?></div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-3"><?= icon('wallet') ?></div>
        <div><div class="k-label">Received This Month</div><div class="k-value"><?= format_peso($stats['collected']) ?></div><div class="k-sub">collected payments</div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-4"><?= icon('banknotes') ?></div>
        <div><div class="k-label">Outstanding AR</div><div class="k-value"><?= format_peso($stats['outstanding']) ?></div><div class="k-sub">unpaid balance</div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-5"><?= icon('layers') ?></div>
        <div><div class="k-label">Expenses (Month)</div><div class="k-value"><?= format_peso($stats['expenses']) ?></div><div class="k-sub">tracked this month</div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-6"><?= icon('alert') ?></div>
        <div><div class="k-label">Needs Attention</div><div class="k-value"><?= $stats['overdue'] + $stats['low_stock'] ?></div><div class="k-sub"><?= $stats['overdue'] ?> overdue · <?= $stats['low_stock'] ?> low stock</div></div>
    </div>
</div>

<div class="grid-2 mt-3">
    <div class="card">
        <div class="card-head"><h2>Revenue vs Expenses</h2><span class="text-muted text-sm">last 6 months</span></div>
        <div class="card-body"><div class="chart-box"><canvas id="revChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Overdue Invoices</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>invoices/index.php">View all</a></div>
        <div class="table-wrap">
            <?php if (empty($overdues)): ?>
                <div class="empty"><div class="empty-icon"><?= icon('check') ?></div><h3>No overdue invoices</h3><p>Great — everything's on time.</p></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th class="num" style="text-align:right">Balance</th></tr></thead>
                <tbody>
                <?php foreach ($overdues as $o): ?>
                    <tr>
                        <td class="mono strong">#<?= e($o['invoice_number']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td class="muted"><?= format_date($o['due_date']) ?></td>
                        <td class="num" style="text-align:right;color:#fb7185"><?= format_peso((float)$o['balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-head">
        <h2>Recent Invoices</h2>
        <div class="actions">
            <?php if (has_role('Admin', 'Accountant', 'Staff')): ?>
            <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>invoices/create.php"><?= icon('plus', '16') ?> New Invoice</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($recent)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('invoice') ?></div><h3>No invoices yet</h3><p>Create your first invoice to get started.</p></div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th class="num" style="text-align:right">Total</th><th class="num" style="text-align:right">Paid</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="mono strong">#<?= e($r['invoice_number']) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td class="muted"><?= format_datetime($r['invoice_date']) ?></td>
                    <td class="num" style="text-align:right"><?= format_peso((float)$r['total_amount']) ?></td>
                    <td class="num" style="text-align:right"><?= format_peso((float)$r['amount_paid']) ?></td>
                    <td><?= status_badge($r['payment_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
const chartData = <?= json_encode($chart) ?>;
new Chart(document.getElementById('revChart'), {
    type: 'line',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [
            { label: 'Revenue', data: chartData.map(d => d.rev), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.12)', fill: true, tension: .4, borderWidth: 2, pointRadius: 3 },
            { label: 'Expenses', data: chartData.map(d => d.exp), borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,.10)', fill: true, tension: .4, borderWidth: 2, pointRadius: 3 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#8fa3c2', usePointStyle: true } } },
        scales: {
            x: { ticks: { color: '#8fa3c2' }, grid: { borderColor: '#223558', color: 'rgba(34,53,88,.35)' } },
            y: { ticks: { color: '#8fa3c2', callback: v => '₱' + (v/1000).toFixed(1) + 'k' }, grid: { borderColor: '#223558', color: 'rgba(34,53,88,.35)' } }
        }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>