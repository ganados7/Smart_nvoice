<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

/* ---------- POST: stock adjustment ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $productId = (int)($_POST['product_id'] ?? 0);
    $type = $_POST['transaction_type'] ?? 'IN';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    $prod = $pdo->prepare('SELECT * FROM products WHERE product_id=? AND is_service=0');
    $prod->execute([$productId]);
    $p = $prod->fetch();

    if (!$p || $quantity <= 0) {
        set_flash('error', 'Invalid product or quantity.');
        redirect('inventory/index.php');
    }

    try {
        $pdo->beginTransaction();
        $delta = ($type === 'OUT') ? -$quantity : $quantity;
        $newQty = max(0, (int)$p['quantity_in_stock'] + $delta);
        $pdo->prepare('UPDATE products SET quantity_in_stock=? WHERE product_id=?')->execute([$newQty, $productId]);
        $pdo->prepare('INSERT INTO inventory_transactions (product_id, transaction_type, quantity, note) VALUES (?,?,?,?)')
            ->execute([$productId, $type, $quantity, $note ?: ($type === 'IN' ? 'Stock received' : 'Stock adjusted')]);
        $pdo->commit();
        audit('ADJUST', 'inventory', "{$p['product_name']}: $type $quantity (now $newQty)");
        set_flash('success', 'Stock updated successfully.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        set_flash('error', 'Failed to update stock: ' . $e->getMessage());
    }
    redirect('inventory/index.php');
}

$page_title = 'Inventory';
require_once __DIR__ . '/../includes/header.php';

$products = $pdo->query(
    "SELECT * FROM products WHERE is_service = 0 ORDER BY product_name"
)->fetchAll();
$txns = $pdo->query(
    "SELECT t.*, p.product_name FROM inventory_transactions t
     JOIN products p ON p.product_id = t.product_id
     ORDER BY t.inventory_transaction_id DESC LIMIT 30"
)->fetchAll();
$lowStock = array_filter($products, fn($p) => (int)$p['quantity_in_stock'] <= (int)$p['reorder_level']);
$stockValue = array_sum(array_map(fn($p) => (float)$p['quantity_in_stock'] * (float)$p['cost_price'], $products));
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Inventory Management</h2>
        <p class="text-muted text-sm">Stock value: <b><?= format_peso($stockValue) ?></b> · <?= count($lowStock) ?> item(s) low on stock</p>
    </div>
</div>

<div class="kpi-grid mb-2">
    <div class="kpi"><div class="kpi-icon grad-1"><?= icon('layers') ?></div><div><div class="k-label">Total Items</div><div class="k-value"><?= count($products) ?></div></div></div>
    <div class="kpi"><div class="kpi-icon grad-2"><?= icon('box') ?></div><div><div class="k-label">Units on Hand</div><div class="k-value"><?= array_sum(array_map(fn($p) => (int)$p['quantity_in_stock'], $products)) ?></div></div></div>
    <div class="kpi"><div class="kpi-icon grad-5"><?= icon('alert') ?></div><div><div class="k-label">Low Stock Alerts</div><div class="k-value"><?= count($lowStock) ?></div><div class="k-sub">at or below reorder level</div></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h2>Stock Levels</h2>
            <div class="actions">
                <div class="dropdown">
                    <button class="btn btn-primary btn-sm" data-dropdown="#adjustMenu"><?= icon('plus', '16') ?> Adjust Stock</button>
                    <div class="dropdown-menu" id="adjustMenu" style="min-width:340px;">
                        <form method="POST" style="padding:14px;">
                            <?= csrf_field() ?>
                            <div class="field"><label>Product</label>
                                <select class="select" name="product_id" required>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['product_id'] ?>"><?= e($p['product_name']) ?> (<?= (int)$p['quantity_in_stock'] ?> on hand)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label>Transaction</label>
                                <select class="select" name="transaction_type">
                                    <option value="IN">IN (Received)</option>
                                    <option value="OUT">OUT (Issued / Adjustment)</option>
                                    <option value="ADJUSTMENT">ADJUSTMENT</option>
                                </select>
                            </div>
                            <div class="field"><label>Quantity</label><input class="input" type="number" min="1" name="quantity" required></div>
                            <div class="field"><label>Note</label><input class="input" type="text" name="note" placeholder="e.g. Purchase order #123"></div>
                            <button class="btn btn-primary" style="width:100%;" type="submit"><?= icon('check', '16') ?> Apply</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Product</th><th>Category</th><th class="num" style="text-align:right">On Hand</th><th class="num" style="text-align:right">Reorder</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p):
                    $st = (int)$p['quantity_in_stock'];
                    $reorder = (int)$p['reorder_level'];
                    $badge = $st <= 0 ? '<span class="badge badge-rose">Out of stock</span>'
                              : ($st <= $reorder ? '<span class="badge badge-amber">Low</span>'
                              : '<span class="badge badge-emerald">OK</span>'); ?>
                    <tr>
                        <td class="strong"><?= e($p['product_name']) ?></td>
                        <td class="muted"><?= e($p['category'] ?? '—') ?></td>
                        <td class="num" style="text-align:right"><b><?= $st ?></b></td>
                        <td class="num muted" style="text-align:right"><?= $reorder ?></td>
                        <td><?= $badge ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Recent Movements</h2></div>
        <div class="table-wrap">
            <?php if (empty($txns)): ?>
                <div class="empty"><div class="empty-icon"><?= icon('layers') ?></div><h3>No inventory movements</h3><p>Stock changes appear here automatically when invoices are created.</p></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Date</th><th>Item</th><th>Type</th><th class="num" style="text-align:right">Qty</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($txns as $t): ?>
                    <tr>
                        <td class="muted"><?= format_datetime($t['transaction_date']) ?></td>
                        <td class="strong"><?= e($t['product_name']) ?></td>
                        <td><?= $t['transaction_type'] === 'IN' ? '<span class="badge badge-emerald">IN</span>' : ($t['transaction_type'] === 'OUT' ? '<span class="badge badge-rose">OUT</span>' : '<span class="badge badge-amber">ADJ</span>') ?></td>
                        <td class="num" style="text-align:right"><?= (int)$t['quantity'] ?></td>
                        <td class="muted text-sm"><?= e($t['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>