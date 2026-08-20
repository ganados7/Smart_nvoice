<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $addr  = trim($_POST['address'] ?? '');

    if ($name === '') {
        set_flash('error', 'Supplier name is required.');
        redirect('suppliers/create.php');
    }
    $pdo->prepare('INSERT INTO suppliers (supplier_name, contact_number, email, address) VALUES (?,?,?,?)')
        ->execute([$name, $phone, $email, $addr]);
    audit('CREATE', 'suppliers', "Added supplier: $name");
    set_flash('success', "Supplier '$name' added successfully.");
    redirect('suppliers/index.php');
}

$page_title = 'New Supplier';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <div class="card-head"><h2>New Supplier</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>suppliers/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="supplier_name">Supplier Name <span class="req">*</span></label><input class="input" id="supplier_name" name="supplier_name" required></div>
                <div class="field"><label for="contact_number">Contact Number</label><input class="input" id="contact_number" name="contact_number"></div>
                <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" name="email"></div>
                <div class="field"><label for="address">Address</label><input class="input" id="address" name="address"></div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Supplier</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>suppliers/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>