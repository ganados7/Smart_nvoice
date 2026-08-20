<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('users/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $status = $_POST['status'] ?? 'Active';
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $fullName === '') {
        set_flash('error', 'Username and full name are required.');
        redirect("users/edit.php?id=$id");
    }
    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET username=?, password=?, full_name=?, role=?, status=? WHERE user_id=?')
                ->execute([$username, $hash, $fullName, $role, $status, $id]);
        } else {
            $pdo->prepare('UPDATE users SET username=?, full_name=?, role=?, status=? WHERE user_id=?')
                ->execute([$username, $fullName, $role, $status, $id]);
        }
        audit('UPDATE', 'users', "Updated user '$username'");
        set_flash('success', 'User updated successfully.');
        redirect('users/index.php');
    } catch (PDOException $e) {
        set_flash('error', 'Username already taken.');
        redirect("users/edit.php?id=$id");
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { set_flash('error', 'User not found.'); redirect('users/index.php'); }

$page_title = 'Edit User';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:600px;margin:0 auto;">
    <div class="card-head"><h2>Edit User — <?= e($r['username']) ?></h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>users/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="username">Username <span class="req">*</span></label><input class="input" id="username" name="username" value="<?= e($r['username']) ?>" required></div>
                <div class="field"><label for="full_name">Full Name <span class="req">*</span></label><input class="input" id="full_name" name="full_name" value="<?= e($r['full_name']) ?>" required></div>
                <div class="field"><label for="password">New Password <span class="text-sm text-muted">(leave blank to keep)</span></label><input class="input" type="password" id="password" name="password" autocomplete="new-password"></div>
                <div class="field"><label for="role">Role</label>
                    <select class="select" id="role" name="role">
                        <?php foreach (['Staff', 'Accountant', 'Admin'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $r['role'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1;"><label for="status">Status</label>
                    <select class="select" id="status" name="status">
                        <option value="Active" <?= $r['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $r['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Changes</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>users/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>