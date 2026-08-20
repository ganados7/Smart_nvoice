<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin', 'Accountant', 'Staff');

/* ---------- POST: create invoice ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d H:i');
    $dueDate = trim($_POST['due_date'] ?? '') ?: null;
    $taxRate = (float)($_POST['tax_rate'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    /* normalize items */
    $clean = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['quantity'] ?? 0);
        $price = (float)($it['unit_price'] ?? 0);
        if ($pid > 0 && $qty > 0 && $price > 0) {
            $clean[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_price' => $price, 'subtotal' => $qty * $price];
        }
    }

    if ($customerId === 0 || empty($clean)) {
        set_flash('error', 'Select a customer and add at least one item with quantity and price.');
        redirect('invoices/create.php');
    }

    $subtotal = array_sum(array_column($clean, 'subtotal'));
    $taxAmount = round($subtotal * ($taxRate / 100), 2);
    $total = round($subtotal - $discount + $taxAmount, 2);

    try {
        $pdo->beginTransaction();

        $invNo = next_invoice_number();
        $stmt = $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date, subtotal, tax_amount, discount, total_amount, payment_status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,\'Pending\',?,?)'
        );
        $stmt->execute([$invNo, $customerId, $invoiceDate, $dueDate, $subtotal, $taxAmount, $discount, $total, $notes, current_user()['user_id']]);
        $invoiceId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)');
        $stmtInv = $pdo->prepare('INSERT INTO inventory_transactions (product_id, invoice_id, transaction_type, quantity, note) VALUES (?,?,?,?,?)');
        foreach ($clean as $it) {
            $stmtItem->execute([$invoiceId, $it['product_id'], $it['quantity'], $it['unit_price'], $it['subtotal']]);
            /* deduct stock for physical products */
            $stmtItemInv = $pdo->prepare('SELECT is_service, quantity_in_stock FROM products WHERE product_id = ? FOR UPDATE');
            $stmtItemInv->execute([$it['product_id']]);
            $prod = $stmtItemInv->fetch();
            if ($prod && !$prod['is_service']) {
                $newQty = max(0, (int)$prod['quantity_in_stock'] - $it['quantity']);
                $pdo->prepare('UPDATE products SET quantity_in_stock = ? WHERE product_id = ?')->execute([$newQty, $it['product_id']]);
                $stmtInv->execute([$it['product_id'], $invoiceId, 'OUT', $it['quantity'], 'Invoice ' . $invNo]);
            }
        }

        /* double-entry: DR AR, CR Sales Revenue, CR Output VAT */
        $entries = [
            ['account_title' => 'Accounts Receivable', 'debit' => $total, 'credit' => 0],
            ['account_title' => 'Sales Revenue', 'debit' => 0, 'credit' => round($subtotal - $discount, 2)],
        ];
        if ($taxAmount > 0) {
            $entries[] = ['account_title' => 'Output VAT', 'debit' => 0, 'credit' => $taxAmount];
        }
        post_ledger($entries, $invoiceId, null, null, 'Invoice ' . $invNo);

        $pdo->commit();
        audit('CREATE', 'invoices', "Issued invoice $invNo to customer #$customerId — " . number_format($total, 2));
        set_flash('success', "Invoice #$invNo issued successfully.");
        redirect("invoices/view.php?id=$invoiceId");
    } catch (Throwable $e) {
        $pdo->rollBack();
        set_flash('error', 'Failed to create invoice: ' . $e->getMessage());
        redirect('invoices/create.php');
    }
}

$page_title = 'New Invoice';
require_once __DIR__ . '/../includes/header.php';

$customers = $pdo->query('SELECT customer_id, customer_name FROM customers ORDER BY customer_name')->fetchAll();
$products = $pdo->query(
    "SELECT product_id, product_name, price, quantity_in_stock, is_service FROM products WHERE status = 'Available' ORDER BY product_name"
)->fetchAll();
$settings = settings();
?>
<form method="POST" id="invoiceForm">
<?= csrf_field() ?>
<div class="card">
    <div class="card-head">
        <h2>New Invoice — #<?= e(next_invoice_number()) ?></h2>
        <div class="actions">
            <a class="btn btn-ghost" href="<?= BASE_URL ?>invoices/index.php">Back to Invoices</a>
        </div>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="customer_id">Customer <span class="req">*</span></label>
                <select class="select" id="customer_id" name="customer_id" required>
                    <option value="">— Select customer —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>"><?= e($c['customer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label for="invoice_date">Invoice Date</label><input class="input" type="datetime-local" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div class="field"><label for="due_date">Due Date</label><input class="input" type="date" id="due_date" name="due_date"></div>
            <div class="field">
                <label for="tax_rate">Tax Rate (%)</label>
                <input class="input" type="number" step="0.01" min="0" id="tax_rate" name="tax_rate" value="<?= (float)$settings['default_tax_rate'] ?>">
            </div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-head">
        <h2>Items</h2>
        <button type="button" class="btn btn-surface btn-sm" id="addItemBtn"><?= icon('plus', '16') ?> Add Item</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table" id="itemsTable">
                <thead><tr><th>Product / Service</th><th class="num" style="text-align:right">Qty</th><th class="num" style="text-align:right">Unit Price</th><th class="num" style="text-align:right">Subtotal</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div style="margin-top:16px;max-width:340px;margin-left:auto;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="field"><label>Subtotal</label><div class="strong" id="calcSubtotal">₱0.00</div></div>
            <div class="field"><label>Discount (₱)</label><input class="input" type="number" min="0" step="0.01" id="discount" name="discount" value="0"></div>
            <div class="field"><label>Tax</label><div class="strong" id="calcTax">₱0.00</div></div>
            <div class="field"><label>Total</label><div class="strong" style="color:var(--accent-2);font-size:17px;" id="calcTotal">₱0.00</div></div>
        </div>
        <div class="field mt-2"><label for="notes">Notes / Terms</label><textarea class="textarea" id="notes" name="notes" placeholder="Payment terms, thank you note, etc."></textarea></div>
        <div class="row" style="gap:10px;justify-content:flex-end;">
            <a class="btn btn-ghost" href="<?= BASE_URL ?>invoices/index.php">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg"><?= icon('check', '16') ?> Issue Invoice</button>
        </div>
    </div>
</div>
</form>

<script>
// CREATE TABLE alias query result
const products = <?= json_encode($products) ?>;
const itemsBody = document.querySelector('#itemsTable tbody');
const totEls = { sub: document.getElementById('calcSubtotal'), tax: document.getElementById('calcTax'), total: document.getElementById('calcTotal') };
let rowIdx = 0;

function fmt(n) { return '₱' + n.toLocaleString('en-US', {minimumFractionDigits: 2}); }
function recalc() {
    const rows = itemsBody.querySelectorAll('tr.item-row');
    let sub = 0, tax;
    rows.forEach(r => { sub += parseFloat(r.dataset.subtotal) || 0; });
    const rate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const disc = parseFloat(document.getElementById('discount').value) || 0;
    tax = sub * rate / 100;
    totEls.sub.textContent = fmt(sub);
    totEls.tax.textContent = fmt(tax);
    totEls.total.textContent = fmt(sub - disc + tax);
}
function addRow(productId) {
    const idx = rowIdx++;
    productId = productId || (products.length ? products[0].product_id : '');
    const p = products.find(x => String(x.product_id) === String(productId));
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = `
        <td>
            <select class="select" name="items[${idx}][product_id]" required>
                ${products.map(x => `<option value="${x.product_id}" ${String(x.product_id)===String(productId)?'selected':''}>${x.product_name} ${x.is_service ? '' : '(stock: ' + x.quantity_in_stock + ')'}</option>`).join('')}
            </select>
        </td>
        <td><input class="input num-i" type="number" min="1" required name="items[${idx}][quantity]" value="1" style="text-align:right;width:90px"></td>
        <td><input class="input price-i" type="number" min="0" step="0.01" required name="items[${idx}][unit_price]" value="${p ? p.price : 0}" style="text-align:right;width:130px"></td>
        <td class="num strong sub" style="text-align:right"></td>
        <td><button type="button" class="btn btn-danger btn-icon rm">${'<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>'}</button></td>`;
    tr.dataset.productId = productId;
    itemsBody.appendChild(tr);
    tr.querySelector('select').addEventListener('change', e => {
        const p2 = products.find(x => String(x.product_id) === e.target.value);
        tr.querySelector('.price-i').value = p2 ? p2.price : 0;
        calcRow(tr);
    });
    tr.querySelectorAll('input').forEach(i => i.addEventListener('input', () => calcRow(tr)));
    tr.querySelector('.rm').addEventListener('click', () => { tr.remove(); recalc(); });
    calcRow(tr);
}
function calcRow(tr) {
    const qty = parseFloat(tr.querySelector('.num-i').value) || 0;
    const price = parseFloat(tr.querySelector('.price-i').value) || 0;
    const sub = qty * price;
    tr.dataset.subtotal = sub;
    tr.querySelector('.sub').textContent = fmt(sub);
    recalc();
}
document.getElementById('addItemBtn').addEventListener('click', () => addRow());
document.getElementById('tax_rate').addEventListener('input', recalc);
document.getElementById('discount').addEventListener('input', recalc);
if (products.length) addRow(); /* prefill one row */
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>