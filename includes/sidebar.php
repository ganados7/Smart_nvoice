<?php
declare(strict_types=1);

$nav = [];
$active = basename(dirname($_SERVER['PHP_SELF'])); /* folder = module */

/* role-based nav */
if (has_role('Admin', 'Accountant', 'Staff')) $nav[] = ['MAIN', [
    ['Dashboard', 'dashboard', 'dashboard', 'dashboard/index.php'],
]];

$sales = [
    ['Invoices', 'invoice', 'invoices', 'invoices/index.php'],
    ['Payments', 'wallet', 'payments', 'payments/index.php'],
    ['Customers', 'users', 'customers', 'customers/index.php'],
];
$catalog = [
    ['Products & Services', 'box', 'products', 'products/index.php'],
    ['Suppliers', 'truck', 'suppliers', 'suppliers/index.php'],
    ['Expenses', 'banknotes', 'expenses', 'expenses/index.php'],
    ['Inventory', 'layers', 'inventory', 'inventory/index.php'],
];
$accounting = [
    ['General Ledger', 'receipt', 'ledger', 'ledger/index.php'],
    ['Reports', 'chart', 'reports', 'reports/index.php'],
];
$system = [
    ['Users', 'shield', 'users', 'users/index.php'],
    ['Audit Trail', 'search', 'audit', 'audit/index.php'],
    ['Settings', 'cog', 'settings', 'settings/index.php'],
];

$user = current_user();
$role = $user['role'] ?? '';

if ($role === 'Admin') {
    $nav[] = ['SALES', $sales];
    $nav[] = ['CATALOG', $catalog];
    $nav[] = ['ACCOUNTING', $accounting];
    $nav[] = ['SYSTEM', $system];
} elseif ($role === 'Accountant') {
    $nav[] = ['SALES', $sales];
    $nav[] = ['CATALOG', [['Expenses', 'banknotes', 'expenses', 'expenses/index.php'], ['Inventory', 'layers', 'inventory', 'inventory/index.php']]];
    $nav[] = ['ACCOUNTING', $accounting];
} else { /* Staff */
    $nav[] = ['SALES', [['Invoices', 'invoice', 'invoices', 'invoices/index.php'], ['Payments', 'wallet', 'payments', 'payments/index.php']]];
    $nav[] = ['CATALOG', [['Products & Services', 'box', 'products', 'products/index.php'], ['Customers', 'users', 'customers', 'customers/index.php'], ['Inventory', 'layers', 'inventory', 'inventory/index.php']]];
}
?>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo"><?= icon('zap', '22') ?></div>
        <div class="brand-name">SmartInvoice<br><span>e-Invoicing System</span></div>
    </div>
    <?php foreach ($nav as $section):
        [$group, $items] = $section; ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($items as [$label, $ic, $mod, $href]): ?>
            <a class="nav-link <?= $active === $mod ? 'active' : '' ?>" href="<?= BASE_URL . $href ?>">
                <?= icon($ic) ?> <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div style="margin-top:24px;padding:16px 12px;border-top:1px solid var(--border);font-size:11px;color:var(--muted-2);">
        v1.0 · Smart E-Invoicing &amp; Accounting<br>for Medium-Sized Enterprises
    </div>
</aside>