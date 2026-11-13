<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\Response;
use PDO;

/**
 * Backup & export (feature "backup", permission "backup").
 *  - Full database backup as a .sql file, written in pure PHP (no exec / mysqldump — works on shared hosting),
 *    streamed table by table in small batches so memory stays low.
 *  - Excel-friendly CSV exports of the main business tables (whitelist only).
 */
final class BackupController extends Controller
{
    /** Tables whose rows are never written to the backup (structure only). */
    private const SKIP_DATA = ['login_attempts'];

    private const ROWS_PER_SELECT = 500;
    private const ROWS_PER_INSERT = 100;
    private const MAX_INSERT_BYTES = 512 * 1024;

    /**
     * CSV exports: key => [label, description, SQL]. SQL is fixed code (never user input).
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const EXPORTS = [
        'customers' => ['Customers', 'Names, phone numbers, emails, cities, birthdays and tags.',
            'SELECT c.* FROM customers c ORDER BY c.id'],
        'enquiries' => ['Enquiries', 'Every website and manual enquiry with stage, budget and follow-up date.',
            'SELECT e.*, a.name AS assigned_to_name FROM enquiries e LEFT JOIN admins a ON a.id = e.assigned_to ORDER BY e.id'],
        'bookings' => ['Bookings', 'Trips with customer, dates, total, amount paid and balance.',
            'SELECT b.*, c.name AS customer_name, c.phone AS customer_phone,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id) AS paid,
                    b.total_amount - (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id) AS balance
             FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id ORDER BY b.id'],
        'payments' => ['Payments', 'Receipts with amount, method, reference and booking.',
            'SELECT p.*, b.number AS booking_number, c.name AS customer_name
             FROM payments p LEFT JOIN bookings b ON b.id = p.booking_id LEFT JOIN customers c ON c.id = b.customer_id ORDER BY p.id'],
        'invoices' => ['GST invoices', 'Invoice numbers, dates, taxable value, CGST/SGST/IGST and totals.',
            'SELECT i.*, c.name AS customer_name, b.number AS booking_number
             FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN bookings b ON b.id = i.booking_id ORDER BY i.id'],
        'vendors' => ['Hotels & vendors', 'Suppliers with contact person, phone, GSTIN and bank details.',
            'SELECT v.* FROM vendors v ORDER BY v.id'],
    ];

    public function index(): never
    {
        $exports = [];
        foreach (self::EXPORTS as $key => [$label, $desc]) {
            $exports[$key] = ['label' => $label, 'desc' => $desc, 'count' => $this->countRows($key)];
        }
        $this->view('admin/backup/index', [
            'title' => 'Backup & export',
            'pretitle' => 'Keep a copy of your data',
            'exports' => $exports,
            'tables' => $this->tableStats(),
            'lastBackup' => Database::fetch(
                "SELECT l.created_at, a.name AS admin_name FROM activity_log l LEFT JOIN admins a ON a.id = l.admin_id
                 WHERE l.entity = 'backup' AND l.action = 'backup' ORDER BY l.id DESC LIMIT 1",
            ),
        ]);
    }

    /** POST /backup/database — streams rth-backup-YYYY-MM-DD-HHmm.sql */
    public function database(): never
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $pdo = Database::pdo();
        $tables = array_map(static fn (array $r): string => (string) $r[0],
            $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM));
        sort($tables);

        Activity::log('backup', 'backup', null, sprintf('Downloaded a full database backup (%d tables)', count($tables)));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $filename = 'rth-backup-' . date('Y-m-d-Hi') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Accel-Buffering: no');

        $dbName = (string) Env::get('DB_NAME', '');
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "-- Reyaan Trip Holiday admin — full database backup\n";
        echo '-- Database: ' . str_replace(["\r", "\n"], ' ', $dbName) . "\n";
        echo '-- Created: ' . date('j M Y, g:i a') . ' (IST) · MySQL ' . str_replace(["\r", "\n"], ' ', $version) . "\n";
        echo "-- Contains admin accounts including password HASHES — store this file somewhere private.\n";
        echo "-- Restore: Hostinger hPanel → Databases → phpMyAdmin → select the database → Import → choose this file.\n";
        echo "-- WARNING: importing replaces the tables listed below.\n\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET time_zone = '+05:30';\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n";
        echo "SET UNIQUE_CHECKS = 0;\n";
        echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

        foreach ($tables as $table) {
            $this->dumpTable($pdo, $table);
            flush();
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        echo "SET UNIQUE_CHECKS = 1;\n";
        echo '-- Backup complete: ' . count($tables) . " tables.\n";
        exit;
    }

    /** GET /backup/export/{table} — CSV with UTF-8 BOM so Excel shows ₹ and Hindi names correctly. */
    public function export(string $table): never
    {
        if (!isset(self::EXPORTS[$table])) {
            Response::abort(404);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        [$label, , $sql] = self::EXPORTS[$table];
        $count = $this->countRows($table);
        Activity::log('exported', 'backup', null, sprintf('Downloaded %s as CSV (%d rows)', mb_strtolower($label), $count));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rth-' . $table . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $headerWritten = false;
        for ($offset = 0; ; $offset += self::ROWS_PER_SELECT) {
            $rows = Database::fetchAll($sql . ' LIMIT ' . self::ROWS_PER_SELECT . ' OFFSET ' . $offset);
            if (!$headerWritten) {
                $cols = $rows !== [] ? array_keys($rows[0]) : $this->columnsOf($table);
                self::csvRow($out, array_map([self::class, 'heading'], $cols));
                $headerWritten = true;
            }
            foreach ($rows as $r) {
                self::csvRow($out, array_values($r));
            }
            if (count($rows) < self::ROWS_PER_SELECT) {
                break;
            }
            flush();
        }
        fclose($out);
        exit;
    }

    /**
     * Write one CSV line. Cells that Excel would run as a formula get a leading apostrophe
     * (phone numbers like "+91 98…" and negative numbers are left alone).
     * @param resource $out
     * @param list<mixed> $cells
     */
    public static function csvRow($out, array $cells): void
    {
        $clean = array_map(static function (mixed $v): string {
            $s = (string) ($v ?? '');
            if ($s === '') {
                return $s;
            }
            $risky = in_array($s[0], ['=', '@', "\t", "\r"], true)
                || (in_array($s[0], ['+', '-'], true) && !preg_match('/^[+-][0-9 ().-]*$/', $s));
            return $risky ? "'" . $s : $s;
        }, $cells);
        fputcsv($out, $clean, ',', '"', '');
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function dumpTable(PDO $pdo, string $table): void
    {
        $q = '`' . str_replace('`', '``', $table) . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $q)->fetch(PDO::FETCH_NUM);
        echo "-- ----------------------------------------------------------------------------\n";
        echo '-- Table ' . $q . "\n";
        echo "-- ----------------------------------------------------------------------------\n";
        echo 'DROP TABLE IF EXISTS ' . $q . ";\n";
        echo $create[1] . ";\n\n";

        if (in_array($table, self::SKIP_DATA, true)) {
            echo "-- (rows not included — temporary sign-in throttling data)\n\n";
            return;
        }

        $columns = $pdo->query('SHOW COLUMNS FROM ' . $q)->fetchAll(PDO::FETCH_ASSOC);
        $insertable = [];
        $binary = [];
        $pk = [];
        foreach ($columns as $c) {
            $extra = strtoupper((string) $c['Extra']);
            if (str_contains($extra, 'VIRTUAL GENERATED') || str_contains($extra, 'STORED GENERATED')) {
                continue; // generated columns are recalculated by MySQL
            }
            $field = (string) $c['Field'];
            $insertable[] = $field;
            if (preg_match('/binary|blob|^bit/i', (string) $c['Type'])) {
                $binary[$field] = true;
            }
            if ($c['Key'] === 'PRI') {
                $pk[] = '`' . str_replace('`', '``', $field) . '`';
            }
        }
        if ($insertable === []) {
            return;
        }
        $colList = implode(', ', array_map(static fn ($f) => '`' . str_replace('`', '``', $f) . '`', $insertable));
        $order = $pk !== [] ? ' ORDER BY ' . implode(', ', $pk) : '';
        $prefix = 'INSERT INTO ' . $q . ' (' . $colList . ') VALUES' . "\n";

        $total = 0;
        for ($offset = 0; ; $offset += self::ROWS_PER_SELECT) {
            $rows = $pdo->query('SELECT ' . $colList . ' FROM ' . $q . $order . ' LIMIT ' . self::ROWS_PER_SELECT . ' OFFSET ' . $offset)
                ->fetchAll(PDO::FETCH_ASSOC);
            $buffer = [];
            $bytes = 0;
            foreach ($rows as $row) {
                $vals = [];
                foreach ($insertable as $f) {
                    $vals[] = $this->sqlValue($pdo, $row[$f], isset($binary[$f]));
                }
                $tuple = '(' . implode(',', $vals) . ')';
                $buffer[] = $tuple;
                $bytes += strlen($tuple);
                if (count($buffer) >= self::ROWS_PER_INSERT || $bytes >= self::MAX_INSERT_BYTES) {
                    echo $prefix, implode(",\n", $buffer), ";\n";
                    $buffer = [];
                    $bytes = 0;
                }
            }
            if ($buffer !== []) {
                echo $prefix, implode(",\n", $buffer), ";\n";
            }
            $total += count($rows);
            if (count($rows) < self::ROWS_PER_SELECT) {
                break;
            }
            flush();
        }
        echo '-- ' . $total . " row(s)\n\n";
    }

    private function sqlValue(PDO $pdo, mixed $v, bool $binary): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return is_finite($v) ? var_export($v, true) : 'NULL';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        $s = (string) $v;
        if ($binary) {
            return $s === '' ? "''" : '0x' . bin2hex($s);
        }
        return (string) $pdo->quote($s);
    }

    /** Row count for an export key (main table only). */
    private function countRows(string $key): int
    {
        try {
            return (int) Database::value('SELECT COUNT(*) FROM `' . $key . '`');
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<string> */
    private function columnsOf(string $table): array
    {
        return array_map(static fn (array $c): string => (string) $c['Field'], Database::fetchAll('SHOW COLUMNS FROM `' . $table . '`'));
    }

    /** All tables with approximate rows and size, for the "what's inside" list. @return list<array{name:string,rows:int,bytes:int}> */
    private function tableStats(): array
    {
        $out = [];
        foreach (Database::fetchAll('SHOW TABLE STATUS') as $t) {
            if (($t['Comment'] ?? '') === 'VIEW' || ($t['Engine'] ?? null) === null) {
                continue;
            }
            $name = (string) $t['Name'];
            $out[] = [
                'name' => $name,
                'rows' => (int) Database::value('SELECT COUNT(*) FROM `' . str_replace('`', '``', $name) . '`'),
                'bytes' => (int) $t['Data_length'] + (int) $t['Index_length'],
            ];
        }
        return $out;
    }

    /** "customer_phone" → "Customer phone" */
    private static function heading(string $col): string
    {
        return ucfirst(str_replace('_', ' ', $col));
    }
}
