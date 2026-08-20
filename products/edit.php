<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('products/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name      = trim($_POST['product_name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $is_service= isset($_POST['is_service']) ? 1 : 0;
    $price     = (float)($_POST['price'] ?? 0);
    $cost      = (float)($_POST['cost_price'] ?? 0);
    $stock     = (int)($_POST['quantity_in_stock'] ?? 0);
    $reorder   = (int)($_POST['reorder_level'] ?? 5);
    $status    = $_POST['status'] ?? 'Available';
    if ($name === '') { set_flash('error', 'Item name is required.'); redirect("products/edit.php?id=$id"); }

    $pdo->prepare(
        'UPDATE products SET product_name=?, description=?, category=?, is_service=?, price=?, cost_price=?, quantity_in_stock=?, reorder_level=?, status=? WHERE product_id=?'
    )->execute([$name, $desc, $category, $is_service, $price, $is_service ? 0 : $cost, $is_service ? 0 : $stock, $is_service ? 0 : $reorder, $status, $id]);
    audit('UPDATE', 'products', "Updated item #$id: $name");
    set_flash('success', 'Item updated successfully.');
    redirect('products/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE product_id=?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { set_flash('error', 'Item not found.'); redirect('products/index.php'); }

$page_title = 'Edit Product / Service';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:680px;margin:0 auto;">
    <div class="card-head"><h2>Edit Product / Service</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>products/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="product_name">Item Name <span class="req">*</span></label><input class="input" id="product_name" name="product_name" value="<?= e($r['product_name']) ?>" required></div>
                <div class="field"><label for="category">Category</label><input class="input" id="category" name="category" value="<?= e($r['category'] ?? '') ?>"></div>
                <div class="field"><label for="price">Selling Price (₱)</label><input class="input" type="number" step="0.01" min="0" id="price" name="price" value="<?= (float)$r['price'] ?>" required></div>
                <div class="field"><label for="cost_price">Cost Price (₱)</label><input class="input" type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="<?= (float)$r['cost_price'] ?>"></div>
                <div class="field"><label for="quantity_in_stock">Stock</label><input class="input" type="number" min="0" id="quantity_in_stock" name="quantity_in_stock" value="<?= (int)$r['quantity_in_stock'] ?>"></div>
                <div class="field"><label for="reorder_level">Reorder Level</label><input class="input" type="number" min="0" id="reorder_level" name="reorder_level" value="<?= (int)$r['reorder_level'] ?>"></div>
                <div class="field"><label for="status">Status</label>
                    <select class="select" id="status" name="status">
                        <option value="Available" <?= $r['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Unavailable" <?= $r['status'] === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                    </select>
                </div>
                <div class="field">
                    <label>Is this a service?</label>
                    <label style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                        <span class="switch"><input type="checkbox" id="is_service" name="is_service" <?= $r['is_service'] ? 'checked' : '' ?>><span class="track"></span></span>
                        <span class="text-sm text-muted">Service — no stock tracking</span>
                    </label>
                </div>
                <div class="field" style="grid-column:1/-1;"><label for="description">Description</label><textarea class="textarea" id="description" name="description"><?= e($r['description'] ?? '') ?></textarea></div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Changes</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>products/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>