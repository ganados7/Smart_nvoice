<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

$page_title = 'Suppliers';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM suppliers WHERE supplier_name LIKE :q OR email LIKE :q OR contact_number LIKE :q ORDER BY supplier_id DESC"
    );
    $stmt->execute([':q' => "%$q%"]);
} else {
    $stmt = $pdo->query('SELECT * FROM suppliers ORDER BY supplier_id DESC');
}
$rows = $stmt->fetchAll();
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Suppliers</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> supplier record(s)</p>
    </div>
    <div class="actions">
        <div class="search-box"><?= icon('search', '16') ?>
            <input class="input" type="text" data-table-search="#suppliersTable" placeholder="Search suppliers...">
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>suppliers/create.php"><?= icon('plus', '16') ?> New Supplier</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('truck') ?></div><h3>No suppliers yet</h3><p>Add suppliers for your purchases.</p></div>
        <?php else: ?>
        <table class="table" id="suppliersTable">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Email</th><th>Address</th><th>Added</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="strong"><?= e($r['supplier_name']) ?></td>
                    <td><?= e($r['contact_number'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['email'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['address'] ?? '—') ?></td>
                    <td class="muted"><?= format_date($r['created_at']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>suppliers/edit.php?id=<?= $r['supplier_id'] ?>" title="Edit"><?= icon('pencil', '16') ?></a>
                        <form method="POST" action="<?= BASE_URL ?>suppliers/delete.php" id="deleteForm<?= $r['supplier_id'] ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['supplier_id'] ?>">
                            <button type="button" class="btn btn-danger btn-icon" data-confirm="deleteForm<?= $r['supplier_id'] ?>" data-msg="Delete supplier '<?= e($r['supplier_name']) ?>'?" title="Delete"><?= icon('trash', '16') ?></button>
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