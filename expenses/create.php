<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $date = $_POST['expense_date'] ?? date('Y-m-d');
    $category = trim($_POST['category'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $vendor = trim($_POST['vendor'] ?? '');

    if ($category === '' || $amount <= 0) {
        set_flash('error', 'Category and a valid amount are required.');
        redirect('expenses/create.php');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO expenses (expense_date, category, description, amount, payment_method, vendor, created_by) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$date, $category, $desc, $amount, $method, $vendor, current_user()['user_id']]);
        $expenseId = (int)$pdo->lastInsertId();

        /* DR Expense account, CR Cash */
        post_ledger([
            ['account_title' => $category . ' Expense', 'debit' => $amount, 'credit' => 0],
            ['account_title' => 'Cash', 'debit' => 0, 'credit' => $amount],
        ], null, null, $expenseId, 'Expense: ' . ($desc ?: $category));

        $pdo->commit();
        audit('CREATE', 'expenses', "Recorded expense #$expenseId ($category) " . number_format($amount, 2));
        set_flash('success', "Expense of " . number_format($amount, 2) . " recorded and posted to the ledger.");
        redirect('expenses/index.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        set_flash('error', 'Failed to record expense: ' . $e->getMessage());
    }
}

$page_title = 'Record Expense';
require_once __DIR__ . '/../includes/header.php';
$cats = ['Rent', 'Utilities', 'Office Supplies', 'Salaries', 'Transportation', 'Marketing', 'Maintenance', 'Software', 'Professional Fees', 'Other'];
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <div class="card-head"><h2>Record Expense</h2><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>expenses/index.php">Back</a></div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="expense_date">Date</label><input class="input" type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
                <div class="field"><label for="category">Category <span class="req">*</span></label>
                    <select class="select" id="category" name="category" required>
                        <?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="amount">Amount (₱) <span class="req">*</span></label><input class="input" type="number" step="0.01" min="0.01" id="amount" name="amount" required></div>
                <div class="field"><label for="payment_method">Payment Method</label>
                    <select class="select" id="payment_method" name="payment_method">
                        <?php foreach (['Cash', 'Bank Transfer', 'GCash', 'QR Payment', 'Check', 'Credit Card'] as $m): ?><option><?= $m ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="vendor">Vendor / Payee</label><input class="input" id="vendor" name="vendor"></div>
                <div class="field" style="grid-column:1/-1;"><label for="description">Description</label><textarea class="textarea" id="description" name="description"></textarea></div>
            </div>
            <div class="row" style="gap:10px;">
                <button type="submit" class="btn btn-primary"><?= icon('check', '16') ?> Record Expense</button>
                <a class="btn btn-ghost" href="<?= BASE_URL ?>expenses/index.php">Cancel</a>
            </div>
            <p class="text-sm text-muted mt-2">This will post a journal entry: DR "[Category] Expense" / CR "Cash".</p>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>