<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/* ================= HELPERS ================= */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function format_peso(float $amount): string
{
    return '&#8369;' . number_format($amount, 2);
}

function format_date(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function format_datetime(?string $date): string
{
    return format_date($date, 'M d, Y h:i A');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* ================= FLASH MESSAGES ================= */

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ================= CSRF ================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (($_POST['_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(419);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

/* ================= SETTINGS ================= */

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [
            'company_name'    => 'SmartInvoice Co.',
            'company_address' => '',
            'company_tin'     => '',
            'company_phone'   => '',
            'company_email'   => '',
            'invoice_footer'  => 'Thank you for your business!',
            'default_tax_rate'=> '0.00',
        ];
        global $pdo;
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

/* ================= AUDIT TRAIL ================= */

function audit(string $action, string $module, string $details = ''): void
{
    try {
        global $pdo;
        $u = current_user();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, username, action, module, details, ip_address) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $u['user_id'] ?? null,
            $u['username'] ?? 'guest',
            $action,
            $module,
            mb_substr($details, 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        // audit should never crash the app
    }
}

/* ================= NUMBER GENERATORS ================= */

function next_invoice_number(): string
{
    global $pdo;
    $row = $pdo->query('SELECT COALESCE(MAX(invoice_id),0)+1 AS next FROM invoices')->fetch();
    return sprintf('INV-%04d', (int)$row['next']);
}

function next_receipt_number(): string
{
    global $pdo;
    $row = $pdo->query('SELECT COALESCE(MAX(receipt_id),0)+1 AS next FROM receipts')->fetch();
    return sprintf('RCT-%04d', (int)$row['next']);
}

/* ================= LEDGER POSTING ================= */

/**
 * Post a double-entry journal entry to the general ledger.
 * $entries = [ ['account_title' => ..., 'debit' => x, 'credit' => y], ... ]
 */
function post_ledger(array $entries, ?int $invoiceId, ?int $paymentId, ?int $expenseId, ?string $description = null): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO general_ledger (invoice_id, payment_id, expense_id, account_title, debit, credit, description) VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($entries as $entry) {
        $stmt->execute([
            $invoiceId,
            $paymentId,
            $expenseId,
            $entry['account_title'],
            $entry['debit'] ?? 0,
            $entry['credit'] ?? 0,
            $description,
        ]);
    }
}

/* ================= UI BADGES ================= */

function status_badge(string $status): string
{
    $colors = [
        'Paid'        => 'emerald',
        'Verified'    => 'emerald',
        'Active'      => 'emerald',
        'Available'   => 'emerald',
        'Issued'      => 'sky',
        'Partial'     => 'amber',
        'Pending'     => 'amber',
        'Overdue'     => 'rose',
        'Cancelled'   => 'slate',
        'Inactive'    => 'slate',
        'Unavailable' => 'slate',
        'Failed'      => 'rose',
    ];
    $color = $colors[$status] ?? 'slate';
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}

/* ================= ICONS (inline SVG) ================= */

function icon(string $name, string $size = '18'): string
{
    $icons = [
        'dashboard'  => '<path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/>',
        'invoice'    => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        'wallet'     => '<path d="M21 12V7H5a2 2 0 01-2-2M21 12v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5"/><path d="M21 12h-3a2 2 0 000 4h3"/>',
        'users'      => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'box'        => '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>',
        'truck'      => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'layers'     => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
        'banknotes'  => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'chart'      => '<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>',
        'receipt'    => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'cog'        => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33h.01a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51h.01a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v.01a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        'bell'       => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'plus'       => '<path d="M12 5v14M5 12h14"/>',
        'pencil'     => '<path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>',
        'eye'        => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'printer'    => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'download'   => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'login'      => '<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'zap'        => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
        'alert'      => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'check'      => '<path d="M20 6L9 17l-5-5"/>',
        'x'          => '<path d="M18 6L6 18M6 6l12 12"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'trending'   => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'menu'       => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'upload'     => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'building'   => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
        'filter'     => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'refresh'    => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>',
    ];
    $body = $icons[$name] ?? $icons['zap'];
    return '<svg width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">' . $body . '</svg>';
}