<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gmail = trim($_POST['gmail'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $status = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';

    if ($username === '' || $fullName === '' || $gmail === '' || strlen($password) < 6) {
        set_flash('error', 'Username, full name, Gmail, and a password of at least 6 characters are required.');
        redirect('users/create.php');
    }
    if (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Please enter a valid Gmail address.');
        redirect('users/create.php');
    }
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (username, password, full_name, gmail, role, status) VALUES (?,?,?,?,?,?)')
            ->execute([$username, $hash, $fullName, $gmail, $role, $status]);
        audit('CREATE', 'users', "Created user '$username' ($role)");

        set_flash('success', "User '$username' created successfully.");
        redirect('users/index.php');
    } catch (PDOException $e) {
        set_flash('error', 'Username already taken.');
        redirect('users/create.php');
    }
}

$page_title = 'New User';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:600px;margin:0 auto;">
    <div class="card-head"><h2>New User</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>users/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="username">Username <span class="req">*</span></label><input class="input" id="username" name="username" required></div>
                <div class="field"><label for="full_name">Full Name <span class="req">*</span></label><input class="input" id="full_name" name="full_name" required></div>
                <div class="field"><label for="gmail">Gmail <span class="req">*</span></label><input class="input" type="email" id="gmail" name="gmail" placeholder="user@gmail.com" required></div>
                <div class="field"><label for="password">Password <span class="req">*</span></label><input class="input" type="password" id="password" name="password" minlength="6" required></div>
                <div class="field"><label for="role">Role</label>
                    <select class="select" id="role" name="role">
                        <option value="Staff">Staff</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1;"><label for="status">Status</label>
                    <select class="select" id="status" name="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Create User</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>users/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>