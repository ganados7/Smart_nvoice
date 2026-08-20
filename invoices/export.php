<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

$status = $_GET['status'] ?? '';
$sql = "SELECT i.invoice_number, c.customer_name, i.invoice_date, i.due_date,
               i.subtotal, i.tax_amount, i.discount, i.total_amount, i.amount_paid,
               (i.total_amount - i.amount_paid) AS balance, i.payment_status
        FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
        WHERE i.invoice_status = 'Issued'";
$params = [];
if ($status !== '') { $sql .= " AND i.payment_status = ?"; $params[] = $status; }

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'invoices_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Invoice #', 'Customer', 'Date', 'Due Date', 'Subtotal', 'Tax', 'Discount', 'Total', 'Paid', 'Balance', 'Status']);
foreach ($stmt->fetchAll() as $r) {
    fputcsv($out, $r);
}
fclose($out);
exit;