<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$page_title = 'Invoices';
require_once __DIR__ . '/../includes/header.php';

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT i.*, c.customer_name, c.customer_id
        FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
        WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND i.payment_status = :st"; $params[':st'] = $status; }
if ($q !== '') { $sql .= " AND (i.invoice_number LIKE :q OR c.customer_name LIKE :q)"; $params[':q'] = "%$q%"; }
$sql .= " ORDER BY i.invoice_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = ['Pending' => 0, 'Partial' => 0, 'Paid' => 0, 'Overdue' => 0];
foreach ($pdo->query("SELECT payment_status, COUNT(*) c FROM invoices GROUP BY payment_status")->fetchAll() as $r) {
    $counts[$r['payment_status']] = (int)$r['c'];
}

function inv_filter_btn(string $label, string $value): string
{
    $active = (($_GET['status'] ?? '') === $value);
    $url = BASE_URL . 'invoices/index.php' . ($value === '' ? '' : '?status=' . urlencode($value));
    $cls = $active ? 'btn-primary' : 'btn-ghost';
    return '<a class="btn btn-sm ' . $cls . '" href="' . $url . '">' . e($label) . '</a>';
}
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Invoices</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> invoice(s) · <?= $counts['Pending'] ?> pending · <?= $counts['Partial'] ?> partial · <?= $counts['Paid'] ?> paid · <?= $counts['Overdue'] ?> overdue</p>
    </div>
    <div class="actions">
        <div class="search-box"><?= icon('search', '16') ?>
            <input class="input" type="text" data-table-search="#invoicesTable" placeholder="Search invoices...">
        </div>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>invoices/export.php<?= $status !== '' ? '?status=' . urlencode($status) : '' ?>"><?= icon('download', '16') ?> CSV</a>
        <a class="btn btn-primary" href="<?= BASE_URL ?>invoices/create.php"><?= icon('plus', '16') ?> New Invoice</a>
    </div>
</div>

<div class="row mb-2" style="gap:8px;">
    <?= inv_filter_btn('All', '') ?>
    <?= inv_filter_btn('Pending', 'Pending') ?>
    <?= inv_filter_btn('Partial', 'Partial') ?>
    <?= inv_filter_btn('Paid', 'Paid') ?>
    <?= inv_filter_btn('Overdue', 'Overdue') ?>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('invoice') ?></div><h3>No invoices found</h3><p>Create your first invoice to get started.</p></div>
        <?php else: ?>
        <table class="table" id="invoicesTable">
            <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Due</th><th class="num" style="text-align:right">Total</th><th class="num" style="text-align:right">Balance</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $balance = (float)$r['total_amount'] - (float)$r['amount_paid']; ?>
                <tr>
                    <td class="mono strong">#<?= e($r['invoice_number']) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td class="muted"><?= format_date($r['invoice_date'], 'M d, Y') ?></td>
                    <td class="muted"><?= format_date($r['due_date']) ?></td>
                    <td class="num" style="text-align:right"><?= format_peso((float)$r['total_amount']) ?></td>
                    <td class="num" style="text-align:right"><?= $balance > 0 ? format_peso($balance) : '—' ?></td>
                    <td><?= status_badge($r['payment_status']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>invoices/view.php?id=<?= $r['invoice_id'] ?>" title="View"><?= icon('eye', '16') ?></a>
                        <?php if ($r['invoice_status'] === 'Issued' && $r['payment_status'] !== 'Paid'): ?>
                        <a class="btn btn-success btn-icon" href="<?= BASE_URL ?>payments/create.php?invoice_id=<?= $r['invoice_id'] ?>&amount=<?= $balance ?>" title="Record Payment"><?= icon('wallet', '16') ?></a>
                        <?php endif; ?>
                        <?php if ($r['invoice_status'] === 'Issued'): ?>
                        <form method="POST" action="<?= BASE_URL ?>invoices/cancel.php" id="cancelForm<?= $r['invoice_id'] ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['invoice_id'] ?>">
                            <button type="button" class="btn btn-danger btn-icon" data-confirm="cancelForm<?= $r['invoice_id'] ?>" data-msg="Cancel invoice #<?= e($r['invoice_number']) ?>?" title="Cancel Invoice"><?= icon('x', '16') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>