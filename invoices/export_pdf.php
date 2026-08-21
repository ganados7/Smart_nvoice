<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_helper.php';

require_role('Admin', 'Accountant', 'Staff');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) redirect('invoices/index.php');

$stmt = $pdo->prepare(
    'SELECT i.*, c.customer_name, c.contact_number, c.email AS customer_email, c.address AS customer_address, u.full_name AS created_by_name
     FROM invoices i
     JOIN customers c ON c.customer_id = i.customer_id
     LEFT JOIN users u ON u.user_id = i.created_by
     WHERE i.invoice_id = ?'
);
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { set_flash('error', 'Invoice not found.'); redirect('invoices/index.php'); }

$items = $pdo->prepare(
    'SELECT ii.*, p.product_name, p.is_service FROM invoice_items ii
     JOIN products p ON p.product_id = ii.product_id WHERE ii.invoice_id = ?'
);
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $pdo->prepare(
    'SELECT p.*, r.receipt_number FROM payments p LEFT JOIN receipts r ON r.payment_id = p.payment_id
     WHERE p.invoice_id = ? ORDER BY p.payment_id'
);
$payments->execute([$id]);
$payments = $payments->fetchAll();

$company = settings();
$balance = (float)$inv['total_amount'] - (float)$inv['amount_paid'];

$pdf = new PdfHelper();
$pdf->setPaper('A4', 'portrait');

$html = '<!DOCTYPE html><html><head>' . PdfHelper::getPdfStyles() . '</head><body>';

$html .= '<div class="header"><div><div style="font-size:24px;font-weight:800;color:#6366f1;margin-bottom:4px;">INVOICE</div>';
$html .= '<h1 style="font-size:18px;">#' . e($inv['invoice_number']) . '</h1>';
$html .= '<p>Date: ' . format_date($inv['invoice_date']) . '</p>';
$html .= '<p>Status: ' . PdfHelper::badge($inv['payment_status']) . '</p></div>';
$html .= '<div class="meta" style="text-align:right;"><div class="label">Bill To</div>';
$html .= '<div class="value">' . e($inv['customer_name']) . '</div>';
if ($inv['customer_address']) $html .= '<div>' . e($inv['customer_address']) . '</div>';
if ($inv['customer_email']) $html .= '<div>' . e($inv['customer_email']) . '</div>';
if ($inv['contact_number']) $html .= '<div>' . e($inv['contact_number']) . '</div>';
$html .= '</div></div>';

$html .= '<table><thead><tr><th>Item</th><th>Type</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr></thead><tbody>';
foreach ($items as $item) {
    $lineTotal = (float)$item['quantity'] * (float)$item['unit_price'];
    $html .= '<tr><td>' . e($item['product_name']) . '</td><td>' . ($item['is_service'] ? 'Service' : 'Product') . '</td>';
    $html .= '<td class="right">' . number_format((float)$item['quantity'], 2) . '</td>';
    $html .= '<td class="right">' . format_peso((float)$item['unit_price']) . '</td>';
    $html .= '<td class="right">' . format_peso($lineTotal) . '</td></tr>';
}
$html .= '</tbody></table>';

$html .= '<table style="width:40%;margin-left:auto;margin-top:12px;"><tbody>';
$html .= '<tr><td>Subtotal</td><td class="right">' . format_peso((float)$inv['subtotal']) . '</td></tr>';
$html .= '<tr><td>Tax</td><td class="right">' . format_peso((float)$inv['tax_amount']) . '</td></tr>';
if ((float)$inv['discount'] > 0) {
    $html .= '<tr><td>Discount</td><td class="right">− ' . format_peso((float)$inv['discount']) . '</td></tr>';
}
$html .= '<tr class="total-row"><td>Total Amount</td><td class="right">' . format_peso((float)$inv['total_amount']) . '</td></tr>';
$html .= '<tr><td>Amount Paid</td><td class="right">' . format_peso((float)$inv['amount_paid']) . '</td></tr>';
$balanceColor = $balance > 0 ? 'text-red' : 'text-green';
$html .= '<tr><td style="font-weight:bold;">Balance Due</td><td class="right ' . $balanceColor . '" style="font-weight:bold;">' . format_peso($balance) . '</td></tr>';
$html .= '</tbody></table>';

if (!empty($payments)) {
    $html .= '<h2 style="font-size:14px;margin-top:24px;margin-bottom:8px;color:#6366f1;">Payment History</h2>';
    $html .= '<table><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th class="right">Amount</th><th>Status</th></tr></thead><tbody>';
    foreach ($payments as $p) {
        $html .= '<tr><td>' . format_date($p['payment_date']) . '</td><td>' . e($p['receipt_number'] ?? '—') . '</td>';
        $html .= '<td>' . e($p['payment_method']) . '</td>';
        $html .= '<td class="right">' . format_peso((float)$p['amount']) . '</td>';
        $html .= '<td>' . PdfHelper::badge($p['payment_status']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '<div class="footer">Smart Invoice — E-Invoicing & Accounting System | Invoice #' . e($inv['invoice_number']) . '</div>';
$html .= '</body></html>';

$pdf->loadHtml($html);
$pdf->render();
$pdf->stream('Invoice_' . $inv['invoice_number'] . '.pdf');
exit;
