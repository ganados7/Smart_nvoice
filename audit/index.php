<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

$page_title = 'Audit Trail';
require_once __DIR__ . '/../includes/header.php';

$module = trim($_GET['module'] ?? '');
$sql = 'SELECT * FROM audit_logs WHERE 1=1';
$params = [];
if ($module !== '') { $sql .= ' AND module = ?'; $params[] = $module; }
$sql .= ' ORDER BY log_id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$modules = $pdo->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="page-head">
    <div>
        <h2 style="font-size:20px;">Audit Trail</h2>
        <p class="text-muted text-sm"><?= count($rows) ?> logged action(s)</p>
    </div>
    <div class="actions">
        <form method="GET" class="row" style="gap:8px;align-items:center;">
            <select class="select" name="module" style="width:180px;" onchange="this.form.submit()">
                <option value="">All modules</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= e($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= e(ucfirst($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty"><div class="empty-icon"><?= icon('search') ?></div><h3>No audit logs yet</h3><p>System actions will be recorded here.</p></div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted" style="white-space:nowrap;"><?= format_datetime($r['created_at']) ?></td>
                    <td class="mono strong"><?= e($r['username']) ?></td>
                    <td><span class="badge badge-<?= in_array($r['action'], ['CREATE', 'LOGIN']) ? 'emerald' : (in_array($r['action'], ['DELETE', 'CANCEL']) ? 'rose' : 'sky') ?>"><?= e($r['action']) ?></span></td>
                    <td class="muted"><?= e(ucfirst($r['module'])) ?></td>
                    <td><?= e($r['details'] ?? '') ?></td>
                    <td class="muted mono"><?= e($r['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>