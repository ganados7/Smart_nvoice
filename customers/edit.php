<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('customers/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $addr  = trim($_POST['address'] ?? '');
    if ($name === '') {
        set_flash('error', 'Customer name is required.');
        redirect("customers/edit.php?id=$id");
    }
    $stmt = $pdo->prepare('UPDATE customers SET customer_name=?, contact_number=?, email=?, address=? WHERE customer_id=?');
    $stmt->execute([$name, $phone, $email, $addr, $id]);
    audit('UPDATE', 'customers', "Updated customer #$id");
    set_flash('success', 'Customer updated successfully.');
    redirect('customers/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id=?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { set_flash('error', 'Customer not found.'); redirect('customers/index.php'); }

$page_title = 'Edit Customer';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <div class="card-head"><h2>Edit Customer</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>customers/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label for="customer_name">Customer Name <span class="req">*</span></label>
                    <input class="input" id="customer_name" name="customer_name" value="<?= e($r['customer_name']) ?>" required>
                </div>
                <div class="field">
                    <label for="contact_number">Contact Number</label>
                    <input class="input" id="contact_number" name="contact_number" value="<?= e($r['contact_number'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="<?= e($r['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="address">Address</label>
                    <input class="input" id="address" name="address" value="<?= e($r['address'] ?? '') ?>">
                </div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Changes</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>customers/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>