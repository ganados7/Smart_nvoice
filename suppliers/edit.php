<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('suppliers/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $addr  = trim($_POST['address'] ?? '');
    if ($name === '') {
        set_flash('error', 'Supplier name is required.');
        redirect("suppliers/edit.php?id=$id");
    }
    $pdo->prepare('UPDATE suppliers SET supplier_name=?, contact_number=?, email=?, address=? WHERE supplier_id=?')
        ->execute([$name, $phone, $email, $addr, $id]);
    audit('UPDATE', 'suppliers', "Updated supplier #$id");
    set_flash('success', 'Supplier updated successfully.');
    redirect('suppliers/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id=?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { set_flash('error', 'Supplier not found.'); redirect('suppliers/index.php'); }

$page_title = 'Edit Supplier';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <div class="card-head"><h2>Edit Supplier</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>suppliers/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="supplier_name">Supplier Name <span class="req">*</span></label><input class="input" id="supplier_name" name="supplier_name" value="<?= e($r['supplier_name']) ?>" required></div>
                <div class="field"><label for="contact_number">Contact Number</label><input class="input" id="contact_number" name="contact_number" value="<?= e($r['contact_number'] ?? '') ?>"></div>
                <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e($r['email'] ?? '') ?>"></div>
                <div class="field"><label for="address">Address</label><input class="input" id="address" name="address" value="<?= e($r['address'] ?? '') ?>"></div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Changes</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>suppliers/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>