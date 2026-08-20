<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

$page_title = 'Expenses';
require_once __DIR__ . '/../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT e.*, u.full_name AS entered_by FROM expenses e
     LEFT JOIN users u ON u.user_id = e.created_by
     WHERE expense_date BETWEEN :from AND :to ORDER BY e.expense_id DESC'
);
$stmt->execute([':from' => $from, ':to' => $to]);
$rows = $stmt->fetchAll();

$byCat = $pdo->prepare(
    'SELECT category, SUM(amount) AS total FROM expenses WHERE expense_date BETWEEN :from AND :to GROUP BY category ORDER BY total DESC'
);
$byCat->execute([':from' => $from, ':to' => $to]);
$catRows = $byCat->fetchAll();

$totalExp = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Expenses</h2>
        <p class="text-muted text-sm">Total: <b><?= format_peso($totalExp) ?></b> · <?= count($rows) ?> record(s)</p>
    </div>
    <div class="actions">
        <form method="GET" class="row" style="gap:8px;align-items:center;">
            <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:auto;">
            <span class="text-muted">to</span>
            <input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:auto;">
            <button class="btn btn-surface btn-sm" type="submit"><?= icon('filter', '16') ?> Filter</button>
        </form>
        <a class="btn btn-primary" href="<?= BASE_URL ?>expenses/create.php"><?= icon('plus', '16') ?> New Expense</a>
    </div>
</div>

<div class="grid-3 mb-2">
    <div class="card" style="grid-column:span 1;">
        <div class="card-head"><h2>By Category</h2></div>
        <div class="table-wrap">
            <?php if (empty($catRows)): ?>
                <div class="empty"><h3>No expenses yet</h3></div>
            <?php else: ?>
            <table class="table">
                <tbody>
                <?php foreach ($catRows as $c): ?>
                    <tr>
                        <td class="strong"><?= e($c['category']) ?></td>
                        <td class="num" style="text-align:right"><?= format_peso((float)$c['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="grid-column:span 2;">
        <div class="card-head"><h2>Expense Records</h2></div>
        <div class="table-wrap">
            <?php if (empty($rows)): ?>
                <div class="empty"><div class="empty-icon"><?= icon('banknotes') ?></div><h3>No expenses in range</h3><p>Record expenses to track spending and post journal entries automatically.</p></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Vendor</th><th>Method</th><th>Entered by</th><th class="num" style="text-align:right">Amount</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="muted"><?= format_date($r['expense_date']) ?></td>
                        <td><span class="badge badge-sky"><?= e($r['category']) ?></span></td>
                        <td><?= e($r['description'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['vendor'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['payment_method'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['entered_by'] ?? '—') ?></td>
                        <td class="num" style="text-align:right;color:#fb7185;"><?= format_peso((float)$r['amount']) ?></td>
                        <td style="text-align:right;white-space:nowrap;">
                            <form method="POST" action="<?= BASE_URL ?>expenses/delete.php" id="delExp<?= $r['expense_id'] ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $r['expense_id'] ?>">
                                <button type="button" class="btn btn-danger btn-icon" data-confirm="delExp<?= $r['expense_id'] ?>" data-msg="Delete expense '<?= e($r['description'] ?? $r['category']) ?>' (<?= format_peso((float)$r['amount']) ?>)?" title="Delete"><?= icon('trash', '16') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>