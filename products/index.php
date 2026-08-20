<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$page_title = 'Products & Services';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$stmt = !$q ? $pdo->query(
    "SELECT p.*, (SELECT COALESCE(SUM(i.quantity),0) FROM invoice_items i WHERE i.product_id = p.product_id) AS sold
     FROM products p ORDER BY p.product_id DESC"
) : null;
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*, (SELECT COALESCE(SUM(i.quantity),0) FROM invoice_items i WHERE i.product_id = p.product_id) AS sold
         FROM products p WHERE p.product_name LIKE :q OR p.category LIKE :q ORDER BY p.product_id DESC"
    );
    $stmt->execute([':q' => "%$q%"]);
}
$rows = $stmt->fetchAll();
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Products &amp; Services</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> item(s)</p>
    </div>
    <div class="actions">
        <div class="search-box"><?= icon('search', '16') ?>
            <input class="input" type="text" data-table-search="#productsTable" placeholder="Search products...">
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>products/create.php"><?= icon('plus', '16') ?> New Item</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('box') ?></div><h3>No products yet</h3><p>Add products or services to sell.</p></div>
        <?php else: ?>
        <table class="table" id="productsTable">
            <thead><tr><th>Item</th><th>Type</th><th>Category</th><th class="num" style="text-align:right">Price</th><th class="num" style="text-align:right">Stock</th><th class="num" style="text-align:right">Sold</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $low = !$r['is_service'] && $r['quantity_in_stock'] <= $r['reorder_level'] && $r['quantity_in_stock'] > 0;
                $out = $r['is_service'] == 0 && $r['quantity_in_stock'] <= 0;
            ?>
                <tr>
                    <td class="strong"><?= e($r['product_name']) ?>
                        <?php if ($r['is_service']): ?><span class="badge badge-sky">Service</span><?php endif; ?>
                    </td>
                    <td class="muted"><?= $r['is_service'] ? 'Service' : 'Product' ?></td>
                    <td class="muted"><?= e($r['category'] ?? '—') ?></td>
                    <td class="num" style="text-align:right"><?= format_peso((float)$r['price']) ?></td>
                    <td class="num" style="text-align:right">
                        <?php if ($r['is_service']): ?>—<?php elseif ($out): ?><span style="color:#fb7185">Out of stock</span><?php elseif ($low): ?><span style="color:#fbbf24"><b><?= (int)$r['quantity_in_stock'] ?></b> low</span><?php else: ?><?= (int)$r['quantity_in_stock'] ?><?php endif; ?>
                    </td>
                    <td class="num muted" style="text-align:right"><?= (int)$r['sold'] ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>products/edit.php?id=<?= $r['product_id'] ?>" title="Edit"><?= icon('pencil', '16') ?></a>
                        <form method="POST" action="<?= BASE_URL ?>products/delete.php" id="deleteForm<?= $r['product_id'] ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['product_id'] ?>">
                            <button type="button" class="btn btn-danger btn-icon" data-confirm="deleteForm<?= $r['product_id'] ?>" data-msg="Delete '<?= e($r['product_name']) ?>'?" title="Delete"><?= icon('trash', '16') ?></button>
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