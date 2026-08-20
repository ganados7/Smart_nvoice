<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? 'Dashboard';
$company = settings();

/* --- notifications data (overdue invoices + low stock) --- */
$notif_count = 0;
$notifications = [];
try {
    $overdue = $pdo->query(
        "SELECT i.invoice_number AS title, c.customer_name AS sub, i.balance
         FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
         WHERE i.invoice_status = 'Issued' AND i.payment_status IN ('Pending','Partial','Overdue')
           AND i.due_date IS NOT NULL AND i.due_date < CURDATE()
         ORDER BY i.due_date LIMIT 8"
    )->fetchAll();
    $lowstock = $pdo->query(
        "SELECT product_name AS title, CONCAT('Only ', quantity_in_stock, ' left') AS sub, quantity_in_stock
         FROM products WHERE is_service = 0 AND status = 'Available' AND quantity_in_stock <= reorder_level
         LIMIT 8"
    )->fetchAll();
    $notifications = array_merge($overdue, $lowstock);
    $notif_count = count($notifications);
} catch (Throwable $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — <?= e($company['company_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/app-extra.css">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" aria-label="Menu"><?= icon('menu') ?></button>
            <div>
                <h1><?= e($page_title) ?></h1>
                <div class="subtitle"><?= e($company['company_name']) ?> — Smart E-Invoicing &amp; Accounting</div>
            </div>
            <div class="topbar-right">
                <div class="dropdown">
                    <button class="icon-btn" data-dropdown="#notifMenu" title="Notifications">
                        <?= icon('bell') ?>
                        <?php if ($notif_count > 0): ?><span class="dot"></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="notifMenu">
                        <div class="dropdown-head">Notifications (<?= $notif_count ?>)</div>
                        <?php if (empty($notifications)): ?>
                            <div class="dropdown-item"><span class="d-sub">All caught up.</span></div>
                        <?php else: foreach ($notifications as $n): ?>
                            <div class="dropdown-item">
                                <span>
                                    <span class="d-title"><?= e($n['title']) ?></span><br>
                                    <span class="d-sub"><?= e($n['sub']) ?></span>
                                </span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="dropdown">
                    <?php $u = current_user(); ?>
                    <button class="user-chip" data-dropdown="#userMenu">
                        <span class="user-avatar"><?= e(strtoupper(substr($u['username'] ?? 'U', 0, 1))) ?></span>
                    </button>
                    <div class="dropdown-menu" id="userMenu" style="min-width:200px;">
                        <a class="dropdown-item" href="<?= BASE_URL ?>auth/logout.php">
                            <?= icon('logout', '16') ?> <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="content"><?php /* content injected here */ ?>