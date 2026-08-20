<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = ['company_name', 'company_address', 'company_tin', 'company_phone', 'company_email', 'invoice_footer', 'default_tax_rate'];
    $stmtU = $pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
    $stmtI = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)');
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $stmtU->execute([$val, $f]);
        if ($stmtU->rowCount() === 0) $stmtI->execute([$f, $val]);
    }
    audit('UPDATE', 'settings', 'Company settings updated');
    set_flash('success', 'Settings saved successfully.');
    redirect('settings/index.php');
}

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
$s = settings();

/* check for backup capability */
$backupOk = function_exists('shell_exec');
?>
<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>Company Information</h2></div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field"><label for="company_name">Company Name</label><input class="input" id="company_name" name="company_name" value="<?= e($s['company_name']) ?>"></div>
                    <div class="field"><label for="company_tin">TIN</label><input class="input" id="company_tin" name="company_tin" value="<?= e($s['company_tin']) ?>"></div>
                    <div class="field" style="grid-column:1/-1;"><label for="company_address">Address</label><input class="input" id="company_address" name="company_address" value="<?= e($s['company_address']) ?>"></div>
                    <div class="field"><label for="company_phone">Phone</label><input class="input" id="company_phone" name="company_phone" value="<?= e($s['company_phone']) ?>"></div>
                    <div class="field"><label for="company_email">Email</label><input class="input" type="email" id="company_email" name="company_email" value="<?= e($s['company_email']) ?>"></div>
                    <div class="field"><label for="default_tax_rate">Default Tax Rate (%)</label><input class="input" type="number" step="0.01" id="default_tax_rate" name="default_tax_rate" value="<?= (float)$s['default_tax_rate'] ?>"></div>
                    <div class="field" style="grid-column:1/-1;"><label for="invoice_footer">Invoice / Receipt Footer</label><textarea class="textarea" id="invoice_footer" name="invoice_footer"><?= e($s['invoice_footer']) ?></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Save Settings</button>
            </form>
        </div>
    </div>

    <div class="row" style="flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-head"><h2>Backup &amp; Restore</h2></div>
            <div class="card-body">
                <p class="text-sm text-muted mb-2">Download a complete SQL dump of your database for safekeeping, or use phpMyAdmin for restore.</p>
                <div class="row" style="gap:10px;">
                    <a class="btn btn-surface" href="<?= BASE_URL ?>settings/backup.php"><?= icon('download', '16') ?> Download Database Backup</a>
                    <a class="btn btn-ghost" href="http://localhost/phpmyadmin" target="_blank"><?= icon('upload', '16') ?> Restore (phpMyAdmin)</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>System Info</h2></div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr><td class="muted">PHP Version</td><td class="num"><?= PHP_VERSION ?></td></tr>
                        <tr><td class="muted">Database</td><td class="num"><?= e(DB_NAME) ?> <span class="badge badge-emerald">Connected</span></td></tr>
                        <tr><td class="muted">App Base URL</td><td class="mono"><?= e(BASE_URL) ?></td></tr>
                        <tr><td class="muted">Server</td><td class="num"><?= e($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>