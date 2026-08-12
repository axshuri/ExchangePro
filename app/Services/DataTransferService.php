<?php
declare(strict_types=1);

/**
 * XLSX data transfer.
 *
 * Export: transactions (buy / sell / exchange) as an "AEX ticket" register —
 * the exact column layout of the legacy ticket workbook.
 *
 * Import: reads such a register and replays every row through the real
 * financial engine (TransactionService::buy / sell), so the double-entry
 * journal, weighted-average inventory and customer balances stay consistent.
 * Optionally erases all existing financial data first (fresh start).
 */
final class DataTransferService
{
    // ------------------------------------------------------------------ export

    /** Rows (template columns) for all completed buy/sell/exchange transactions. */
    public static function exportRows(): array
    {
        // One export row per transaction — the exchange leg is fetched with a
        // correlated subquery (LIMIT 1) so multi-leg transactions never duplicate.
        $rows = Database::query(
            "SELECT t.id, t.tx_date, t.type, t.rate, t.foreign_amount, t.base_amount,
                    t.payment_method, cu.full_name AS customer_name, cu.address, cu.id_type, cu.id_number,
                    c.code AS currency_code,
                    (SELECT ti.target_amount FROM transaction_items ti
                      WHERE ti.transaction_id = t.id ORDER BY ti.id LIMIT 1) AS target_amount,
                    (SELECT tc.code FROM transaction_items ti2
                      JOIN currencies tc ON tc.id = ti2.target_currency_id
                      WHERE ti2.transaction_id = t.id ORDER BY ti2.id LIMIT 1) AS target_code
             FROM transactions t
             LEFT JOIN currencies c ON c.id = t.currency_id
             LEFT JOIN customers cu ON cu.id = t.customer_id
             WHERE t.type IN ('buy','sell','exchange') AND t.status = 'completed'
             ORDER BY t.tx_date, t.id");

        $out = [];
        foreach ($rows as $t) {
            $type = (string)$t['type'];
            if ($type === 'exchange') {
                // One exchange row = purchase of the target currency at its
                // effective CAD rate, so amount × rate = |Amount (CAD)| always holds.
                $amount = (string)$t['target_amount'];
                $base = (string)$t['base_amount'];
                $buy = true;
                $code = (string)$t['target_code'];
                $rate = Money::isZero($amount) ? '0' : Money::round(Money::div($base, $amount, 10), 10);
            } else {
                $amount = (string)$t['foreign_amount'];
                $base = (string)$t['base_amount'];
                $buy = $type === 'buy';
                $code = (string)$t['currency_code'];
                $rate = (string)$t['rate'];
            }
            $out[] = [
                'date' => substr((string)$t['tx_date'], 0, 10),
                'amount' => ($buy ? '' : '-') . self::num($amount),
                'currency' => $code,
                'rate' => self::num($rate),
                'method' => self::methodLabel((string)$t['payment_method']),
                'cad' => ($buy ? '-' : '') . self::num($base),
                'name' => (string)$t['customer_name'],
                'address' => (string)$t['address'],
                'dob' => '',
                'id_type' => (string)$t['id_type'],
                'id_number' => (string)$t['id_number'],
                'place' => '',
            ];
        }
        return $out;
    }

    /** Build the downloadable .xlsx byte string (template column layout). */
    public static function exportFile(): string
    {
        $grid = [[
            'Date', 'Amount', 'Currency Purchase/Sale', 'Rate', 'Method',
            'Amount (CAD) Paid/Received', 'Name', 'Address', 'DOB', 'ID Type',
            'ID Number', 'Place of Issue',
        ]];
        foreach (self::exportRows() as $r) {
            $grid[] = [
                $r['date'], $r['amount'], $r['currency'], $r['rate'], $r['method'],
                $r['cad'], $r['name'], $r['address'], $r['dob'], $r['id_type'],
                $r['id_number'], $r['place'],
            ];
        }
        return Xlsx::bytes($grid, ['date_cols' => [0], 'header_rows' => 1]);
    }

    // ------------------------------------------------------------------ import

    /**
     * Import a register from an .xlsx file through the transaction engine.
     *
     * @param array $opts { account_id:int, erase:bool, allow_short:bool }
     * @return array{erased:bool, imported:int, failed:array, skipped:array,
     *               created_customers:int, created_currencies:array}
     * @throws DomainException on unrecoverable errors (nothing is changed)
     */
    public static function import(string $path, array $opts): array
    {
        $grid = Xlsx::read($path);
        if (!$grid) throw new DomainException('The file contains no rows.');

        $parsed = self::parseGrid($grid);
        if (!empty($opts['erase']) && count($parsed['valid']) === 0) {
            throw new DomainException('The file contains no importable rows — nothing was changed.');
        }

        $accountId = (int)($opts['account_id'] ?? 0);
        $account = Database::fetch('SELECT id FROM accounts WHERE id = ? AND is_active = 1', [$accountId]);
        if (!$account) throw new DomainException('Choose a valid inventory/cash account for the import.');

        // Process chronologically so sells see the buys that came before them.
        usort($parsed['valid'], static fn(array $a, array $b) => [$a['date'], $a['row']] <=> [$b['date'], $b['row']]);

        $currenciesBefore = array_column(Database::query('SELECT code FROM currencies'), 'code');
        $customersBefore = (int)Database::value('SELECT COUNT(*) FROM customers');

        $summary = [
            'erased' => !empty($opts['erase']),
            'imported' => 0,
            'failed' => [],
            'skipped' => $parsed['skipped'],
            'created_customers' => 0,
            'created_currencies' => [],
        ];

        Database::transaction(function () use ($grid, $parsed, $opts, $accountId, &$summary) {
            if (!empty($opts['erase'])) {
                self::erase();
            }
            foreach ($parsed['valid'] as $r) {
                try {
                    // Nested transaction = savepoint: a failing row rolls back to
                    // its own savepoint without disturbing the other rows.
                    Database::transaction(function () use ($r, $opts, $accountId) {
                        $currency = self::ensureCurrency($r['currency']);
                        $customerId = self::ensureCustomer($r);
                        $foreign = self::num((string)abs($r['amount']));
                        $d = [
                            'customer_id' => $customerId,
                            'currency_id' => (int)$currency['id'],
                            'foreign_amount' => $foreign,
                            'rate' => self::num((string)$r['rate']),
                            'payment_method' => self::paymentMethod($r['method']),
                            'tx_date' => $r['date'] . ' 00:00:00',
                            'large_confirmed' => true,
                        ];
                        if ($r['amount'] > 0) {
                            // Customer buys foreign currency: it enters the account,
                            // base cash leaves the same account.
                            $d['account_id'] = $accountId;
                            $d['source_account_id'] = $accountId;
                            TransactionService::buy($d);
                        } else {
                            // Customer sells foreign currency: it leaves the account,
                            // base cash enters the same account.
                            $d['account_id'] = $accountId;
                            $d['destination_account_id'] = $accountId;
                            $d['allow_short'] = !empty($opts['allow_short']);
                            TransactionService::sell($d);
                        }
                    });
                    $summary['imported']++;
                } catch (Throwable $e) {
                    $summary['failed'][] = ['row' => $r['row'], 'reason' => $e->getMessage()];
                }
            }

            AuditService::log('import_transactions', 'transaction', null, null, [
                'file_rows' => count($grid),
                'valid_rows' => count($parsed['valid']),
                'imported' => $summary['imported'],
                'failed' => count($summary['failed']),
                'skipped' => count($summary['skipped']),
                'erased' => $summary['erased'],
                'account_id' => $accountId,
            ], 'XLSX import');
        });

        $summary['created_customers'] = (int)Database::value('SELECT COUNT(*) FROM customers') - $customersBefore;
        $summary['created_currencies'] = array_values(array_diff(
            array_column(Database::query('SELECT code FROM currencies'), 'code'),
            $currenciesBefore
        ));

        return $summary;
    }

    /**
     * Delete all financial data (fresh start). Reference data — users, roles,
     * permissions, currencies, rates, accounts, GL accounts, settings, audit
     * log, backups — is kept.
     */
    public static function erase(): void
    {
        $tables = [
            'cash_count_items', 'cash_counts', 'reconciliations', 'daily_closings',
            'journal_lines', 'journal_entries', 'transaction_fees', 'transaction_items',
            'inventory_movements', 'transactions', 'expenses', 'income', 'transfers',
            'inventory_costings', 'customer_accounts', 'customers',
        ];
        Database::execute('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $t) {
                Database::execute("DELETE FROM `$t`");
            }
            // Reset per-day transaction sequence counters.
            Database::execute("DELETE FROM settings WHERE setting_key LIKE 'seq\\_%'");
        } finally {
            Database::execute('SET FOREIGN_KEY_CHECKS = 1');
        }
        AuditService::log('erase_financial_data', 'system', null, null,
            ['tables' => count($tables)], 'XLSX import: erase old data');
    }

    // --------------------------------------------------------------- parsing

    /** Normalize a raw sheet grid into validated rows (+ skips with reasons). */
    private static function parseGrid(array $grid): array
    {
        $valid = [];
        $skipped = [];

        $map = null;
        $firstData = 0;
        if ($grid && self::looksLikeHeader($grid[0])) {
            $map = self::columnMap($grid[0]);
            $firstData = 1;
        }

        foreach ($grid as $idx => $row) {
            if ($idx < $firstData) continue;
            $rowNo = $idx + 1;
            $cells = array_values($row);

            $allEmpty = true;
            foreach ($cells as $v) {
                if ($v !== null && $v !== '') { $allEmpty = false; break; }
            }
            if ($allEmpty) continue; // blank row — ignore silently

            $date = self::cell($cells, $map, 'date', 0);
            $dateStr = self::parseDate($date);
            // Summary/footer rows (e.g. "Total") carry no date — drop them silently.
            $joined = strtolower(implode(' ', array_filter(array_map(
                static fn($v) => trim((string)$v), $cells))));
            if (str_contains($joined, 'total') && $dateStr === null) continue;

            $amountNum = self::parseNumber(self::cell($cells, $map, 'amount', 1));
            $currencyCode = self::parseCurrency(self::cell($cells, $map, 'currency', 2));
            $rateNum = self::parseNumber(self::cell($cells, $map, 'rate', 3));

            if ($dateStr === null) {
                $skipped[] = ['row' => $rowNo, 'reason' => 'invalid date: ' . self::describe($date)];
                continue;
            }
            if ($amountNum === null) {
                $skipped[] = ['row' => $rowNo, 'reason' => 'invalid amount: ' . self::describe(self::cell($cells, $map, 'amount', 1))];
                continue;
            }
            if (abs($amountNum) < 1e-10) {
                $skipped[] = ['row' => $rowNo, 'reason' => 'amount is zero'];
                continue;
            }
            if ($currencyCode === '') {
                $skipped[] = ['row' => $rowNo, 'reason' => 'missing currency'];
                continue;
            }
            if ($rateNum === null || $rateNum <= 0) {
                $skipped[] = ['row' => $rowNo, 'reason' => 'invalid rate: ' . self::describe(self::cell($cells, $map, 'rate', 3))];
                continue;
            }

            $valid[] = [
                'row' => $rowNo,
                'date' => $dateStr,
                'amount' => $amountNum,
                'currency' => $currencyCode,
                'rate' => $rateNum,
                'method' => trim((string)self::cell($cells, $map, 'method', 4)),
                'name' => trim((string)self::cell($cells, $map, 'name', 6)),
                'address' => trim((string)self::cell($cells, $map, 'address', 7)),
                'dob' => trim((string)self::cell($cells, $map, 'dob', 8)),
                'id_type' => trim((string)self::cell($cells, $map, 'id_type', 9)),
                'id_number' => trim((string)self::cell($cells, $map, 'id_number', 10)),
                'place' => trim((string)self::cell($cells, $map, 'place', 11)),
            ];
        }
        return ['valid' => $valid, 'skipped' => $skipped];
    }

    private static function cell(array $cells, ?array $map, string $key, int $fallback)
    {
        $i = $map[$key] ?? $fallback;
        return $cells[$i] ?? null;
    }

    private static function looksLikeHeader(array $row): bool
    {
        $joined = strtolower(implode(' ', array_map(static fn($v) => (string)$v, $row)));
        return str_contains($joined, 'date') && str_contains($joined, 'amount')
            && str_contains($joined, 'curr');
    }

    /** Map template header names to column indexes (positional fallback later). */
    private static function columnMap(array $header): array
    {
        $rules = [
            'date' => ['date'],
            'amount' => ['amount'],
            'currency' => ['curr'],
            'rate' => ['rate'],
            'method' => ['method'],
            'cad' => ['cad', 'paid', 'received'],
            'name' => ['name'],
            'address' => ['address'],
            'dob' => ['dob', 'birth'],
            'id_number' => ['id num', 'id no', 'number'],
            'id_type' => ['id type', 'type of id'],
            'place' => ['place'],
        ];
        $map = [];
        $used = [];
        foreach ($rules as $key => $needles) {
            foreach ($header as $i => $h) {
                if (isset($used[$i])) continue;
                $h = strtolower(trim((string)$h));
                foreach ($needles as $n) {
                    if (str_contains($h, $n)) {
                        $map[$key] = $i;
                        $used[$i] = true;
                        break 2;
                    }
                }
            }
        }
        return $map;
    }

    private static function parseDate($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return Xlsx::serialToDate($v);
        $s = trim((string)$v);
        if ($s === '') return null;
        if (preg_match('/^\d+(\.\d+)?$/', $s)) return Xlsx::serialToDate((float)$s);

        $formats = ['Y-m-d', 'Y/m/d', 'Y.m.d', 'm/d/Y', 'm-d-Y', 'd/m/Y', 'd-m-Y',
            'M j, Y', 'j M Y', 'd M Y', 'F j, Y', 'j F Y'];
        foreach ($formats as $f) {
            $dt = DateTime::createFromFormat('!' . $f, $s);
            if ($dt && $dt->format($f) === $s) {
                $y = (int)$dt->format('Y');
                if ($y >= 1900 && $y <= 2100) return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            $y = (int)date('Y', $ts);
            if ($y >= 1900 && $y <= 2100) return date('Y-m-d', $ts);
        }
        return null;
    }

    private static function parseNumber($v): ?float
    {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float)$v;
        $s = trim((string)$v);
        if ($s === '') return null;
        $s = str_replace([' ', ',', '$', '€', '£', '¥', '₩', '₹'], '', $s);
        return is_numeric($s) ? (float)$s : null;
    }

    private static function parseCurrency($v): string
    {
        $s = strtoupper((string)preg_replace('/[^A-Za-z]/', '', (string)$v));
        return strlen($s) <= 8 ? $s : '';
    }

    // ------------------------------------------------------------- persistence

    private static function ensureCurrency(string $code): array
    {
        $c = Database::fetch('SELECT * FROM currencies WHERE code = ?', [$code]);
        if ($c) return $c;
        $id = Database::insert('currencies', [
            'code' => $code, 'name' => $code, 'symbol' => null,
            'amount_precision' => 2, 'rate_precision' => 4,
            'is_base' => 0, 'is_active' => 1,
            'notes' => 'Auto-created during XLSX import',
        ]);
        return Database::fetch('SELECT * FROM currencies WHERE id = ?', [$id]);
    }

    private static function ensureCustomer(array $r): ?int
    {
        $name = $r['name'];
        if ($name === '') return null;
        $c = Database::fetch('SELECT id FROM customers WHERE full_name = ?', [$name]);
        if ($c) return (int)$c['id'];

        $extra = [];
        if ($r['dob'] !== '') $extra[] = 'DOB: ' . $r['dob'];
        if ($r['place'] !== '') $extra[] = 'Place of Issue: ' . $r['place'];
        $notes = $extra ? implode(' | ', $extra) : null;

        return CustomerService::create([
            'full_name' => $name,
            'address' => $r['address'] !== '' ? $r['address'] : null,
            'id_type' => $r['id_type'] !== '' ? $r['id_type'] : null,
            'id_number' => $r['id_number'] !== '' ? $r['id_number'] : null,
            'notes' => $notes,
        ]);
    }

    private static function paymentMethod(string $m): string
    {
        $m = strtolower(trim($m));
        if (str_contains($m, 'bank') || str_contains($m, 'transfer') || str_contains($m, 'etransfer')) {
            return 'bank_transfer';
        }
        if (str_contains($m, 'card') || str_contains($m, 'debit') || str_contains($m, 'credit')) {
            return 'card';
        }
        if (str_contains($m, 'cash')) return 'cash';
        return $m === '' ? 'cash' : 'other';
    }

    private static function methodLabel(string $m): string
    {
        return match ($m) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
            'internal_balance' => 'Internal',
            default => 'Other',
        };
    }

    /** Trim a decimal string for clean display ("1.2800000000" → "1.28"). */
    private static function num(string $v): string
    {
        $v = Money::norm($v);
        $v = rtrim(rtrim($v, '0'), '.');
        return ($v === '' || $v === '-0') ? '0' : $v;
    }

    private static function describe($v): string
    {
        if ($v === null || $v === '') return '(empty)';
        return mb_substr((string)$v, 0, 40);
    }
}
