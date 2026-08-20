<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

$page_title = 'General Ledger';
require_once __DIR__ . '/../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$acc = trim($_GET['acc'] ?? '');

$sql = "SELECT * FROM general_ledger WHERE DATE(transaction_date) BETWEEN :from AND :to";
$params = [':from' => $from, ':to' => $to];
if ($acc !== '') { $sql .= " AND account_title LIKE :acc"; $params[':acc'] = "%$acc%"; }
$sql .= " ORDER BY transaction_date DESC, ledger_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* account balances */
$qfrom = $pdo->quote($from);
$qto = $pdo->quote($to);
$balances = $pdo->query(
    "SELECT account_title,
            SUM(debit) AS d, SUM(credit) AS c, (SUM(debit) - SUM(credit)) AS bal
     FROM general_ledger
     WHERE DATE(transaction_date) BETWEEN $qfrom AND $qto
     GROUP BY account_title ORDER BY account_title"
)->fetchAll();

$totDeb = array_sum(array_map(fn($r) => (float)$r['debit'], $rows));
$totCre = array_sum(array_map(fn($r) => (float)$r['credit'], $rows));

$ref = function ($row): string {
    if ($row['invoice_id'])  return '<a class="mono" href="' . BASE_URL . 'invoices/view.php?id=' . $row['invoice_id'] . '">INV #' . str_pad((string)$row['invoice_id'], 4, '0', STR_PAD_LEFT) . '</a>';
    if ($row['payment_id'])  return '<span class="mono">PAY #' . $row['payment_id'] . '</span>';
    if ($row['expense_id'])  return '<span class="mono">EXP #' . $row['expense_id'] . '</span>';
    return '—';
};
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">General Ledger</h2>
        <p class="text-muted text-sm">Double-entry journal · debits must equal credits</p>
    </div>
    <div class="actions">
        <form method="GET" class="row" style="gap:8px;align-items:center;">
            <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:auto;">
            <span class="text-muted">to</span>
            <input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:auto;">
            <input class="input" type="text" name="acc" placeholder="Filter account..." value="<?= e($acc) ?>" style="width:180px;">
            <button class="btn btn-surface btn-sm" type="submit"><?= icon('filter', '16') ?> Filter</button>
        </form>
    </div>
</div>

<div class="kpi-grid mb-2">
    <div class="kpi">
        <div class="kpi-icon grad-1"><?= icon('banknotes') ?></div>
        <div><div class="k-label">Total Debits</div><div class="k-value"><?= format_peso($totDeb) ?></div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-2"><?= icon('banknotes') ?></div>
        <div><div class="k-label">Total Credits</div><div class="k-value"><?= format_peso($totCre) ?></div><div class="k-sub"><?= abs($totDeb - $totCre) < 0.01 ? 'Balanced ✓' : 'UNBALANCED!' ?></div></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon grad-3"><?= icon('receipt') ?></div>
        <div><div class="k-label">Journal Entries</div><div class="k-value"><?= count($rows) ?></div><div class="k-sub"><?= count($balances) ?> accounts</div></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>Account Balances</h2></div>
        <div class="table-wrap">
            <?php if (empty($balances)): ?>
                <div class="empty"><h3>No activity in range</h3></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Account</th><th class="num" style="text-align:right">Debit</th><th class="num" style="text-align:right">Credit</th><th class="num" style="text-align:right">Net</th></tr></thead>
                <tbody>
                <?php foreach ($balances as $b): ?>
                    <tr>
                        <td class="strong"><?= e($b['account_title']) ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$b['d']) ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$b['c']) ?></td>
                        <td class="num" style="text-align:right;color:<?= (float)$b['bal'] < 0 ? '#fb7185' : '#34d399' ?>"><?= format_peso(abs((float)$b['bal'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Journal Entries</h2></div>
        <div class="table-wrap">
            <?php if (empty($rows)): ?>
                <div class="empty"><div class="empty-icon"><?= icon('receipt') ?></div><h3>No entries in range</h3><p>Post an invoice, payment, or expense to see journal entries.</p></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Date</th><th>Account</th><th>Ref</th><th>Description</th><th class="num" style="text-align:right">Debit</th><th class="num" style="text-align:right">Credit</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="muted"><?= format_datetime($r['transaction_date']) ?></td>
                        <td class="strong"><?= e($r['account_title']) ?></td>
                        <td><?= $ref($r) ?></td>
                        <td class="muted text-sm"><?= e($r['description'] ?? '') ?></td>
                        <td class="num" style="text-align:right"><?= (float)$r['debit'] ? format_peso((float)$r['debit']) : '—' ?></td>
                        <td class="num" style="text-align:right"><?= (float)$r['credit'] ? format_peso((float)$r['credit']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>