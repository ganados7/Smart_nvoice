<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_helper.php';

require_role('Admin', 'Accountant');

$tab = $_GET['tab'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

$qfrom = $pdo->quote($from);
$qto = $pdo->quote($to);
$company = settings();

$pdf = new PdfHelper();
$pdf->setPaper('A4', 'portrait');

$html = '<!DOCTYPE html><html><head>' . PdfHelper::getPdfStyles() . '</head><body>';
$html .= '<div class="header"><div><h1>' . htmlspecialchars($company['company_name']) . '</h1>';
$html .= '<p>Financial Report — ' . format_date($from) . ' to ' . format_date($to) . '</p></div>';
$html .= '<div class="meta"><div class="label">Generated</div><div class="value">' . date('M d, Y h:i A') . '</div></div></div>';

if ($tab === 'sales') {
    $sales = $pdo->query(
        "SELECT i.invoice_number, c.customer_name, i.invoice_date, i.subtotal, i.tax_amount, i.total_amount, i.amount_paid, (i.total_amount - i.amount_paid) AS balance, i.payment_status
         FROM invoices i JOIN customers c ON c.customer_id = i.customer_id
         WHERE i.invoice_status='Issued' AND DATE(i.invoice_date) BETWEEN $qfrom AND $qto
         ORDER BY i.invoice_id"
    )->fetchAll();
    $salesTot = array_sum(array_map(fn($r) => (float)$r['total_amount'], $sales));
    $salesPaid = array_sum(array_map(fn($r) => (float)$r['amount_paid'], $sales));

    $html .= '<h2 style="font-size:16px;margin-bottom:12px;color:#6366f1;">Sales Report</h2>';
    $html .= '<table><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th class="right">Total</th><th class="right">Paid</th><th class="right">Balance</th><th>Status</th></tr></thead><tbody>';
    foreach ($sales as $r) {
        $balance = (float)$r['total_amount'] - (float)$r['amount_paid'];
        $html .= '<tr><td>#' . e($r['invoice_number']) . '</td><td>' . e($r['customer_name']) . '</td><td>' . format_date($r['invoice_date']) . '</td>';
        $html .= '<td class="right">' . format_peso((float)$r['total_amount']) . '</td>';
        $html .= '<td class="right">' . format_peso((float)$r['amount_paid']) . '</td>';
        $html .= '<td class="right">' . format_peso($balance) . '</td>';
        $html .= '<td>' . PdfHelper::badge($r['payment_status']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div class="summary-box"><div class="label">Summary</div><div class="big" style="color:#6366f1;">' . format_peso($salesTot) . '</div>';
    $html .= '<div class="text-muted">' . count($sales) . ' invoices · ' . format_peso($salesPaid) . ' collected</div></div>';
    $filename = 'Sales_Report_' . $from . '_to_' . $to . '.pdf';

} elseif ($tab === 'expenses') {
    $expenses = $pdo->query("SELECT * FROM expenses WHERE expense_date BETWEEN $qfrom AND $qto ORDER BY expense_date")->fetchAll();
    $expTotal = array_sum(array_map(fn($r) => (float)$r['amount'], $expenses));

    $html .= '<h2 style="font-size:16px;margin-bottom:12px;color:#6366f1;">Expense Report</h2>';
    $html .= '<table><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Vendor</th><th class="right">Amount</th></tr></thead><tbody>';
    foreach ($expenses as $r) {
        $html .= '<tr><td>' . format_date($r['expense_date']) . '</td><td>' . e($r['category']) . '</td><td>' . e($r['description'] ?? '—') . '</td>';
        $html .= '<td>' . e($r['vendor'] ?? '—') . '</td>';
        $html .= '<td class="right">' . format_peso((float)$r['amount']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div class="summary-box"><div class="label">Total Expenses</div><div class="big text-red">' . format_peso($expTotal) . '</div></div>';
    $filename = 'Expense_Report_' . $from . '_to_' . $to . '.pdf';

} elseif ($tab === 'income') {
    $revenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) AS v FROM invoices WHERE invoice_status='Issued' AND DATE(invoice_date) BETWEEN $qfrom AND $qto")->fetch()['v'];
    $salesTax = (float)$pdo->query("SELECT COALESCE(SUM(tax_amount),0) AS v FROM invoices WHERE invoice_status='Issued' AND DATE(invoice_date) BETWEEN $qfrom AND $qto")->fetch()['v'];
    $expTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE expense_date BETWEEN $qfrom AND $qto")->fetch()['v'];
    $netIncome = $revenue - $expTotal;

    $html .= '<h2 style="font-size:16px;margin-bottom:4px;text-align:center;color:#6366f1;">Income Statement</h2>';
    $html .= '<p style="text-align:center;font-size:10px;color:#64748b;margin-bottom:16px;">For the period ' . format_date($from) . ' — ' . format_date($to) . '</p>';
    $html .= '<table><tbody>';
    $html .= '<tr><td style="font-weight:bold;font-size:13px;">REVENUE</td><td></td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Sales Revenue</td><td class="right">' . format_peso($revenue) . '</td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Less: Output VAT</td><td class="right">− ' . format_peso($salesTax) . '</td></tr>';
    $html .= '<tr class="total-row"><td>Total Revenue</td><td class="right">' . format_peso($revenue) . '</td></tr>';
    $html .= '<tr><td colspan="2" style="height:12px;"></td></tr>';
    $html .= '<tr><td style="font-weight:bold;font-size:13px;">EXPENSES</td><td></td></tr>';
    $html .= '<tr class="total-row"><td>Total Expenses</td><td class="right">' . format_peso($expTotal) . '</td></tr>';
    $html .= '</tbody></table>';
    $color = $netIncome >= 0 ? 'text-green' : 'text-red';
    $html .= '<div class="summary-box"><div class="label">Net Income</div><div class="big ' . $color . '">' . format_peso($netIncome) . '</div></div>';
    $filename = 'Income_Statement_' . $from . '_to_' . $to . '.pdf';

} elseif ($tab === 'balance') {
    $cash = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE payment_status='Verified'")->fetch()['v'];
    $ar = (float)$pdo->query("SELECT COALESCE(SUM(GREATEST(total_amount - amount_paid,0)),0) AS v FROM invoices WHERE invoice_status='Issued' AND payment_status IN ('Pending','Partial','Overdue')")->fetch()['v'];
    $inventory = (float)$pdo->query("SELECT COALESCE(SUM(quantity_in_stock * cost_price),0) AS v FROM products WHERE is_service=0")->fetch()['v'];
    $assets = $cash + $ar + $inventory;

    $html .= '<h2 style="font-size:16px;margin-bottom:4px;text-align:center;color:#6366f1;">Balance Sheet</h2>';
    $html .= '<p style="text-align:center;font-size:10px;color:#64748b;margin-bottom:16px;">As of ' . format_date($to) . '</p>';
    $html .= '<table><tbody>';
    $html .= '<tr><td style="font-weight:bold;font-size:13px;">ASSETS</td><td></td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Cash & Bank</td><td class="right">' . format_peso($cash) . '</td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Accounts Receivable</td><td class="right">' . format_peso($ar) . '</td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Inventory (at cost)</td><td class="right">' . format_peso($inventory) . '</td></tr>';
    $html .= '<tr class="total-row"><td>Total Assets</td><td class="right">' . format_peso($assets) . '</td></tr>';
    $html .= '<tr><td colspan="2" style="height:12px;"></td></tr>';
    $html .= '<tr><td style="font-weight:bold;font-size:13px;">LIABILITIES & EQUITY</td><td></td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Accounts Payable</td><td class="right">₱0.00</td></tr>';
    $html .= '<tr><td style="padding-left:20px;" class="text-muted">Owner\'s Equity</td><td class="right">' . format_peso($assets) . '</td></tr>';
    $html .= '<tr class="total-row"><td>Total Liabilities & Equity</td><td class="right">' . format_peso($assets) . '</td></tr>';
    $html .= '</tbody></table>';
    $filename = 'Balance_Sheet_' . $to . '.pdf';
}

$html .= '<div class="footer">Smart Invoice — E-Invoicing & Accounting System</div>';
$html .= '</body></html>';

$pdf->loadHtml($html);
$pdf->render();
$pdf->stream($filename);
exit;
