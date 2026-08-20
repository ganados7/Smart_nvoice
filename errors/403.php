<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$page_title = '403 — Access Denied';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:560px;margin:60px auto;">
    <div class="card-body" style="text-align:center;padding:48px;">
        <div class="empty-icon" style="width:72px;height:72px;margin:0 auto 18px;border-radius:20px;background:var(--surface-3);display:grid;place-items:center;color:#fb7185;">
            <?= icon('shield', '36') ?>
        </div>
        <h2 style="font-size:20px;margin-bottom:8px;">403 — Access Denied</h2>
        <p class="text-muted">You don't have permission to view this page. Contact the administrator if you think this is a mistake.</p>
        <a href="<?= BASE_URL ?>dashboard/index.php" class="btn btn-primary" style="margin-top:20px;"><?= icon('dashboard', '16') ?> Back to Dashboard</a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>