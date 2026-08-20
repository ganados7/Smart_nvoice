<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('expenses/index.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM expenses WHERE expense_id=?');
$stmt->execute([$id]);
$exp = $stmt->fetch();
if (!$exp) { set_flash('error', 'Expense not found.'); redirect('expenses/index.php'); }

try {
    $pdo->beginTransaction();
    /* reverse the ledger entries for this expense */
    $entries = $pdo->prepare('SELECT * FROM general_ledger WHERE expense_id=?');
    $entries->execute([$id]);
    foreach ($entries->fetchAll() as $e) {
        $pdo->prepare('INSERT INTO general_ledger (expense_id, account_title, debit, credit, description) VALUES (?,?,?,?,?)')
            ->execute([$id, $e['account_title'], $e['credit'], $e['debit'], 'Reversal: deleted expense #' . $id]);
    }
    $pdo->prepare('DELETE FROM expenses WHERE expense_id=?')->execute([$id]);
    $pdo->commit();
    audit('DELETE', 'expenses', 'Deleted expense #' . $id);
    set_flash('success', 'Expense deleted and ledger reversed.');
} catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('error', 'Failed to delete: ' . $e->getMessage());
}
redirect('expenses/index.php');