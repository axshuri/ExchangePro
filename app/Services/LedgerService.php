<?php
declare(strict_types=1);

/**
 * Double-entry ledger service.
 *
 * Every financial event posts a balanced journal entry. Each line references
 * either a location account (cash desk/vault/bank — asset position per currency)
 * or a GL account (P&L). Debits must equal credits in base currency.
 *
 * account_currencies.balance is a cached projection derived from journal lines,
 * updated atomically inside the same DB transaction.
 */
final class LedgerService
{
    /** Post a balanced journal entry. Lines: [account_id|gl_account_id, currency_id, debit|credit, rate, note] */
    public static function post(
        array $lines,
        string $description,
        ?int $transactionId = null,
        ?int $userId = null,
        ?string $entryNo = null
    ): int {
        if (count($lines) < 2) {
            throw new DomainException('Journal entry must have at least 2 lines.');
        }

        $baseCurrency = SettingService::baseCurrency();
        if (!$baseCurrency) {
            throw new DomainException('Base currency is not configured.');
        }

        // ---- normalize lines & compute base equivalents ----
        $normalized = [];
        $totalDebit = Money::zero();
        $totalCredit = Money::zero();

        foreach ($lines as $line) {
            if (!isset($line['currency_id'])) {
                throw new DomainException('Journal line missing currency.');
            }
            $isDebit = !empty($line['debit']) && Money::isZero($line['credit'] ?? '0');
            $isCredit = !empty($line['credit']) && Money::isZero($line['debit'] ?? '0');
            if ($isDebit === $isCredit) {
                throw new DomainException('Each journal line must be a debit OR credit, not both/neither.');
            }

            $amount = $isDebit ? (string)$line['debit'] : (string)$line['credit'];
            if (Money::isNegative($amount)) {
                throw new DomainException('Journal line amount cannot be negative.');
            }
            $amount = Money::round($amount, 10);

            $currencyId = (int)$line['currency_id'];
            if ($currencyId === (int)$baseCurrency['id']) {
                $baseAmount = $amount;
            } else {
                $rate = $line['rate'] ?? self::currentRate($currencyId);
                if (!$rate || Money::isZero($rate)) {
                    throw new DomainException('No exchange rate available for currency #' . $currencyId . ' in ledger.');
                }
                $baseAmount = Money::round(Money::mul($amount, $rate), 10);
            }

            $hasAccount = !empty($line['account_id']);
            $hasGl = !empty($line['gl_account_id']);
            if ($hasAccount === $hasGl) {
                throw new DomainException('Journal line must reference exactly one account (location or GL).');
            }

            $normalized[] = [
                'account_id' => $hasAccount ? (int)$line['account_id'] : null,
                'gl_account_id' => $hasGl ? (int)$line['gl_account_id'] : null,
                'currency_id' => $currencyId,
                'debit' => $isDebit ? $amount : '0',
                'credit' => $isCredit ? $amount : '0',
                'base_debit' => $isDebit ? $baseAmount : '0',
                'base_credit' => $isCredit ? $baseAmount : '0',
                'rate' => $line['rate'] ?? null,
                'note' => $line['note'] ?? null,
            ];

            if ($isDebit) $totalDebit = Money::add($totalDebit, $baseAmount);
            else $totalCredit = Money::add($totalCredit, $baseAmount);
        }

        // ---- must balance ----
        if (Money::compare($totalDebit, $totalCredit) !== 0) {
            throw new DomainException(
                sprintf('Unbalanced journal entry: debits %s vs credits %s.', $totalDebit, $totalCredit)
            );
        }

        // ---- persist atomically ----
        return Database::transaction(function () use ($normalized, $description, $transactionId, $userId, $entryNo) {
            $entryId = Database::insert('journal_entries', [
                'entry_no' => $entryNo ?? self::nextEntryNo(),
                'transaction_id' => $transactionId,
                'description' => $description,
                'created_by' => $userId ?? Auth::id(),
            ]);

            foreach ($normalized as $line) {
                Database::insert('journal_lines', array_merge($line, ['entry_id' => $entryId]));
                if ($line['account_id']) {
                    // Cache tracks the currency amount itself (base value is derived)
                    self::applyToAccountBalance(
                        (int)$line['account_id'], $line['currency_id'],
                        Money::sub($line['debit'], $line['credit'])
                    );
                }
            }
            return $entryId;
        });
    }

    /** Reversal: invert every line of an entry (used by transaction reversals). */
    public static function reverseEntry(int $entryId, string $description, ?int $txId = null): int
    {
        $entry = Database::fetch("SELECT * FROM journal_entries WHERE id = ?", [$entryId]);
        if (!$entry) throw new DomainException('Journal entry not found.');
        $lines = Database::query("SELECT * FROM journal_lines WHERE entry_id = ?", [$entryId]);

        $inverted = [];
        foreach ($lines as $l) {
            $line = [
                'currency_id' => (int)$l['currency_id'],
                'rate' => $l['rate'] ? (string)$l['rate'] : null,
                'note' => ($l['note'] ? $l['note'] . ' ' : '') . '(reversal)',
            ];
            if ($l['account_id']) $line['account_id'] = (int)$l['account_id'];
            if ($l['gl_account_id']) $line['gl_account_id'] = (int)$l['gl_account_id'];
            if (Money::compare((string)$l['debit'], '0', 10) > 0) $line['credit'] = (string)$l['debit'];
            else $line['debit'] = (string)$l['credit'];
            $inverted[] = $line;
        }
        return self::post($inverted, $description, $txId, null, self::nextEntryNo());
    }

    /** Balance of an account+currency (cached projection from ledger). */
    public static function accountBalance(int $accountId, int $currencyId): string
    {
        $row = Database::fetch("SELECT balance FROM account_currencies WHERE account_id = ? AND currency_id = ?",
            [$accountId, $currencyId]);
        return $row ? (string)$row['balance'] : Money::zero();
    }

    /** Total company position per currency (sum over all accounts) — pure ledger sum. */
    public static function totalPosition(int $currencyId): string
    {
        $v = Database::value(
            "SELECT COALESCE(SUM(CASE WHEN l.account_id IS NOT NULL THEN l.base_debit - l.base_credit END),0)
             FROM journal_lines l WHERE l.currency_id = ? AND l.account_id IS NOT NULL", [$currencyId]);
        return (string)$v;
    }

    /** Positions per account+currency from the ledger directly. */
    public static function positions(?int $accountId = null, ?int $currencyId = null): array
    {
        $sql = "SELECT l.account_id, l.currency_id,
                       SUM(l.debit - l.credit) AS amount,
                       SUM(l.base_debit - l.base_credit) AS base_amount
                FROM journal_lines l
                WHERE l.account_id IS NOT NULL";
        $params = [];
        if ($accountId) { $sql .= " AND l.account_id = ?"; $params[] = $accountId; }
        if ($currencyId) { $sql .= " AND l.currency_id = ?"; $params[] = $currencyId; }
        $sql .= " GROUP BY l.account_id, l.currency_id";
        return Database::query($sql, $params);
    }

    /** GL account totals in base currency (debits-credits for expenses; credits-debits for income). */
    public static function glTotal(int $glAccountId, string $from = '2000-01-01', string $to = '2099-12-31'): string
    {
        $v = Database::value(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             WHERE l.gl_account_id = ? AND je.created_at BETWEEN ? AND ?",
            [$glAccountId, $from . ' 00:00:00', $to . ' 23:59:59']);
        return (string)$v;
    }

    private static function applyToAccountBalance(int $accountId, int $currencyId, string $delta): void
    {
        $row = Database::lockRow(
            "SELECT * FROM account_currencies WHERE account_id = ? AND currency_id = ?",
            [$accountId, $currencyId]);
        if ($row) {
            $newBalance = Money::add((string)$row['balance'], $delta);
            Database::update('account_currencies',
                ['balance' => Money::round($newBalance, 10)],
                'account_id = ? AND currency_id = ?', [$accountId, $currencyId]);
        } else {
            Database::insert('account_currencies', [
                'account_id' => $accountId,
                'currency_id' => $currencyId,
                'balance' => Money::round($delta, 10),
            ]);
        }
    }

    private static function currentRate(int $currencyId): string
    {
        $row = Database::fetch("SELECT mid_rate FROM exchange_rates WHERE currency_id = ?", [$currencyId]);
        if (!$row || Money::isZero((string)$row['mid_rate'])) {
            $row2 = Database::fetch("SELECT buy_rate FROM exchange_rates WHERE currency_id = ?", [$currencyId]);
            return $row2 ? (string)$row2['buy_rate'] : '0';
        }
        return (string)$row['mid_rate'];
    }

    public static function nextEntryNo(): string
    {
        $max = (int)(Database::value("SELECT MAX(CAST(SUBSTRING(entry_no, 4) AS UNSIGNED)) FROM journal_entries") ?: 0);
        return 'JE-' . str_pad((string)($max + 1), 8, '0', STR_PAD_LEFT);
    }

    /** Public accessor for the current mid rate used in conversions. */
    public static function currentRatePublic(int $currencyId): string
    {
        return self::currentRate($currencyId);
    }
}
