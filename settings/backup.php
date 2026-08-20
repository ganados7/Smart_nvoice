<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('Admin');

/* Simple SQL dump generated with PHP (no shell / mysqldump required) */

$tables = ['users', 'customers', 'suppliers', 'products', 'invoices', 'invoice_items', 'payments', 'receipts', 'general_ledger', 'inventory_transactions', 'expenses', 'audit_logs', 'settings'];

$lines = [];
$lines[] = '-- Smart Invoice Database Backup';
$lines[] = '-- Generated: ' . date('Y-m-d H:i:s');
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = '';

foreach ($tables as $table) {
    /* structure */
    $show = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
    $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
    $lines[] = $show['Create Table'] . ';';
    $lines[] = '';

    /* data */
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $first = $stmt->fetch();
    if (!$first) continue; /* empty table — nothing to export */
    $cols = array_keys($first);
    $rows = array_merge([$first], $stmt->fetchAll());

    $colStr = '`' . implode('`, `', $cols) . '`';
    foreach (array_chunk($rows, 200) as $chunk) {
        $vals = [];
        foreach ($chunk as $row) {
            $escaped = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            $vals[] = '(' . implode(', ', $escaped) . ')';
        }
        $lines[] = "INSERT INTO `$table` ($colStr) VALUES";
        $lines[] = implode(",\n", $vals) . ';';
    }
    $lines[] = '';
}

$filename = 'smartinvoice_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo implode("\n", $lines);
exit;