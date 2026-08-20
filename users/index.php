<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

$page_title = 'Users';
require_once __DIR__ . '/../includes/header.php';

$rows = $pdo->query('SELECT * FROM users ORDER BY user_id')->fetchAll();
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">User Management</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> user account(s)</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="<?= BASE_URL ?>users/create.php"><?= icon('plus', '16') ?> New User</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table" id="usersTable">
            <thead><tr><th>Username</th><th>Full Name</th><th>Gmail</th><th>Role</th><th>Status</th><th>Created</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono strong"><?= e($r['username']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td class="muted"><?= e($r['gmail'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $r['role'] === 'Admin' ? 'rose' : ($r['role'] === 'Accountant' ? 'sky' : 'amber') ?>"><?= e($r['role']) ?></span></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="muted"><?= format_date($r['created_at']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-surface btn-icon" href="<?= BASE_URL ?>users/edit.php?id=<?= $r['user_id'] ?>" title="Edit"><?= icon('pencil', '16') ?></a>
                        <?php if ($r['user_id'] !== (current_user())['user_id']): ?>
                        <form method="POST" action="<?= BASE_URL ?>users/delete.php" id="delUser<?= $r['user_id'] ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['user_id'] ?>">
                            <button type="button" class="btn btn-danger btn-icon" data-confirm="delUser<?= $r['user_id'] ?>" data-msg="Delete user '<?= e($r['username']) ?>'?" title="Delete"><?= icon('trash', '16') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>