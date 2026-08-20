<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$page_title = 'Customers';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.customer_id) AS total_invoices
         FROM customers c
         WHERE c.customer_name LIKE :q OR c.email LIKE :q OR c.contact_number LIKE :q
         ORDER BY c.customer_id DESC"
    );
    $stmt->execute([':q' => "%$q%"]);
} else {
    $stmt = $pdo->query(
        "SELECT c.*, (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.customer_id) AS total_invoices
         FROM customers c ORDER BY c.customer_id DESC"
    );
}
$rows = $stmt->fetchAll();
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Customer Directory</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> customer record(s)</p>
    </div>
    <div class="actions">
        <div class="search-box"><?= icon('search', '16') ?>
            <input class="input" type="text" data-table-search="#customersTable" placeholder="Search customers..." value="<?= e($q) ?>">
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>customers/create.php"><?= icon('plus', '16') ?> New Customer</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('users') ?></div><h3>No customers found</h3><p>Add your first customer to start invoicing.</p></div>
        <?php else: ?>
        <table class="table" id="customersTable">
            <thead><tr><th>Customer</th><th>Contact</th><th>Email</th><th>Address</th><th class="num" style="text-align:right">Invoices</th><th>Added</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="strong"><?= e($r['customer_name']) ?></td>
                    <td><?= e($r['contact_number'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['email'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['address'] ?? '—') ?></td>
                    <td class="num" style="text-align:right"><?= (int)$r['total_invoices'] ?></td>
                    <td class="muted"><?= format_date($r['created_at']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>customers/edit.php?id=<?= $r['customer_id'] ?>" title="Edit"><?= icon('pencil', '16') ?></a>
                        <form method="POST" action="<?= BASE_URL ?>customers/delete.php" id="deleteForm<?= $r['customer_id'] ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['customer_id'] ?>">
                            <button type="button" class="btn btn-danger btn-icon" data-confirm="deleteForm<?= $r['customer_id'] ?>" data-msg="Delete customer '<?= e($r['customer_name']) ?>'? This cannot be undone." title="Delete"><?= icon('trash', '16') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>