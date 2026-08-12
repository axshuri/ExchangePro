<?php
declare(strict_types=1);

/**
 * Transaction engine.
 *
 * Every financial operation:
 *   1. Runs inside one DB transaction (atomic — all or nothing).
 *   2. Posts a balanced double-entry journal.
 *   3. Records inventory movements with balance_after.
 *   4. Recalculates weighted-average costing.
 *   5. Updates customer balances when paid from internal balance.
 *   6. Writes an audit log.
 *
 * Historical transaction rates are NEVER overwritten — each transaction stores
 * its own rate permanently.
 */
final class TransactionService
{
    private const TYPES = ['buy', 'sell', 'exchange', 'reversal', 'adjustment'];

    /** Generate next human-readable transaction number: EX-20260811-000001 */
    public static function nextNumber(): string
    {
        return self::nextRefNumber(SettingService::txPrefix(), 6);
    }

    /**
     * Collision-free sequential reference number: {PREFIX}-{YYYYMMDD}-{SEQ}
     * Uses a row-locked counter, so it never collides even under concurrency.
     */
    public static function nextRefNumber(string $prefix, int $pad = 5): string
    {
        return Database::transaction(function () use ($prefix, $pad) {
            $date = date('Ymd');
            $counterKey = 'seq_' . $prefix . '_' . $date;
            $row = Database::lockRow("SELECT * FROM settings WHERE setting_key = ?", [$counterKey]);
            $seq = $row ? ((int)$row['setting_value'] + 1) : 1;
            if ($row) {
                Database::update('settings', ['setting_value' => (string)$seq], 'setting_key = ?', [$counterKey]);
            } else {
                Database::insert('settings', ['setting_key' => $counterKey, 'setting_value' => (string)$seq]);
            }
            return $prefix . '-' . $date . '-' . str_pad((string)$seq, $pad, '0', STR_PAD_LEFT);
        });
    }

    /**
     * BUY foreign currency.
     * Customer gives foreign_amount of $currencyId; exchange pays base (or chosen) currency.
     *
     * @param array{
     *   customer_id?:int, currency_id:int, foreign_amount:string, rate:string,
     *   account_id:int (where foreign currency goes), source_account_id:int (where base comes from),
     *   fee_amount?:string, fee_currency_id?:int, discount_amount?:string,
     *   payment_method?:string, notes?:string, tx_date?:string, large_confirmed?:bool
     * }
     */
    public static function buy(array $d): array
    {
        return self::run(function () use ($d) {
            self::validateAmount($d['foreign_amount'] ?? '', 'foreign_amount');
            self::validateAmount($d['rate'] ?? '', 'rate');
            $base = SettingService::baseCurrency();
            $currency = self::requireCurrency((int)($d['currency_id'] ?? 0));
            $foreignAmount = Money::round((string)$d['foreign_amount'], 10);
            $rate = Money::round((string)$d['rate'], 10);

            $baseAmount = Money::round(Money::mul($foreignAmount, $rate), 10);
            $fee = self::feeOf($d, $baseAmount, $base, $currency, $rate);
            $discount = self::discountOf($d, $baseAmount);

            // Payout to customer = base amount − fee + discount
            $payout = Money::add(Money::sub($baseAmount, $fee['base_amount']), $discount);
            if (Money::isNegative($payout)) {
                throw new DomainException(t('tx.fee_exceeds'));
            }

            $destAccount = (int)($d['account_id'] ?? 0);
            $srcAccount = (int)($d['source_account_id'] ?? 0);
            self::requireAccount($destAccount);
            self::requireAccount($srcAccount);

            $txId = self::createTx([
                'type' => 'buy', 'customer_id' => $d['customer_id'] ?? null,
                'currency_id' => $currency['id'], 'rate' => $rate,
                'foreign_amount' => $foreignAmount, 'base_amount' => $baseAmount,
                'fee_amount' => $fee['amount'], 'fee_currency_id' => $fee['currency_id'],
                'discount_amount' => $discount, 'total_amount' => $payout,
                'payment_method' => $d['payment_method'] ?? 'cash',
                'source_account_id' => $srcAccount, 'destination_account_id' => $destAccount,
                'notes' => $d['notes'] ?? null, 'tx_date' => $d['tx_date'] ?? null,
            ], $d['large_confirmed'] ?? false);

            // Journal: Debit Foreign Inventory / Credit Base Cash (+Fee Income if fee)
            $lines = [[
                'account_id' => $destAccount, 'currency_id' => $currency['id'],
                'debit' => $foreignAmount, 'rate' => $rate, 'note' => 'BUY ' . $currency['code'],
            ]];
            if (Money::isPositive($payout)) {
                $lines[] = [
                    'account_id' => $srcAccount, 'currency_id' => $base['id'],
                    'credit' => $payout, 'rate' => '1', 'note' => 'BUY payout ' . $currency['code'],
                ];
            }
            if (Money::isPositive($fee['base_amount'])) {
                $feeGl = self::gl('FEE_INCOME', 'income');
                $lines[] = [
                    'gl_account_id' => $feeGl['id'], 'currency_id' => $base['id'],
                    'credit' => $fee['base_amount'], 'rate' => '1', 'note' => 'Fee',
                ];
            }
            if (Money::isPositive($discount)) {
                $discGl = self::gl('DISCOUNT_GIVEN', 'expense');
                $lines[] = [
                    'gl_account_id' => $discGl['id'], 'currency_id' => $base['id'],
                    'debit' => $discount, 'rate' => '1', 'note' => 'Discount',
                ];
            }
            $entryId = LedgerService::post($lines, 'BUY ' . $currency['code'] . ' ' . $foreignAmount,
                $txId, null, self::nextEntryNo());

            self::recordMovements($txId, $destAccount, $currency['id'], 'in', $foreignAmount, $rate, $baseAmount);
            self::finish($txId, 'buy', $d, $currency, $rate, $foreignAmount, $baseAmount, $entryId);
            return self::tx($txId);
        });
    }

    /**
     * SELL foreign currency.
     * Customer receives foreign_amount; pays base. Realized P/L = (rate − avg_cost) × amount.
     */
    public static function sell(array $d): array
    {
        return self::run(function () use ($d) {
            self::validateAmount($d['foreign_amount'] ?? '', 'foreign_amount');
            self::validateAmount($d['rate'] ?? '', 'rate');
            $base = SettingService::baseCurrency();
            $currency = self::requireCurrency((int)($d['currency_id'] ?? 0));
            $foreignAmount = Money::round((string)$d['foreign_amount'], 10);
            $rate = Money::round((string)$d['rate'], 10);

            $baseAmount = Money::round(Money::mul($foreignAmount, $rate), 10);
            $fee = self::feeOf($d, $baseAmount, $base, $currency, $rate);
            $discount = self::discountOf($d, $baseAmount);
            // Customer pays: base value + fee − discount
            $received = Money::sub(Money::add($baseAmount, $fee['base_amount']), $discount);
            if (Money::isNegative($received)) {
                throw new DomainException(t('tx.discount_exceeds'));
            }

            $srcAccount = (int)($d['account_id'] ?? 0);
            $destAccount = (int)($d['destination_account_id'] ?? 0);
            self::requireAccount($srcAccount);
            self::requireAccount($destAccount);

            $costing = InventoryService::costing($currency['id']);
            // allow_short is used by bulk XLSX imports of historical registers where
            // a sell may legitimately precede any recorded buy of that currency.
            if (empty($d['allow_short']) && Money::compare((string)$costing['qty'], $foreignAmount) < 0) {
                throw new DomainException(t('tx.insufficient') . ' ' . $currency['code']
                    . ' (' . t('tx.available') . ': ' . Money::format((string)$costing['qty'], 2) . ')');
            }

            // Cost basis + realized P/L are computed BEFORE the row is stored so they
            // are persisted on the transaction itself (per-currency profit analytics).
            $costBase = Money::round(Money::mul($foreignAmount, (string)$costing['avg_cost']), 10);
            // A negative cost basis (inventory already oversold via allow_short) has
            // no meaningful cost — treat it as zero so the journal stays balanced and
            // the sale books its full proceeds as realized profit.
            if (Money::isNegative($costBase)) $costBase = Money::zero();
            $realized = Money::round(Money::sub($baseAmount, $costBase), 10);

            $txId = self::createTx([
                'type' => 'sell', 'customer_id' => $d['customer_id'] ?? null,
                'currency_id' => $currency['id'], 'rate' => $rate,
                'foreign_amount' => $foreignAmount, 'base_amount' => $baseAmount,
                'fee_amount' => $fee['amount'], 'fee_currency_id' => $fee['currency_id'],
                'discount_amount' => $discount, 'total_amount' => $received,
                'realized_pl' => $realized, 'pl_currency_id' => $currency['id'],
                'payment_method' => $d['payment_method'] ?? 'cash',
                'source_account_id' => $srcAccount, 'destination_account_id' => $destAccount,
                'notes' => $d['notes'] ?? null, 'tx_date' => $d['tx_date'] ?? null,
            ], $d['large_confirmed'] ?? false);

            // Journal: Debit Base Cash / Credit Foreign Inventory @avg cost / Credit Realized P/L
            $lines = [];
            if (Money::isPositive($received)) {
                $lines[] = [
                    'account_id' => $destAccount, 'currency_id' => $base['id'],
                    'debit' => $received, 'rate' => '1', 'note' => 'SELL ' . $currency['code'],
                ];
            }
            if (Money::isPositive($costBase)) {
                $lines[] = [
                    'account_id' => $srcAccount, 'currency_id' => $currency['id'],
                    'credit' => $foreignAmount, 'rate' => (string)$costing['avg_cost'],
                    'note' => 'SELL inventory ' . $currency['code'],
                ];
            }
            $plGl = self::gl('REALIZED_PL', 'income');
            $pl = self::plLine($plGl, $realized, 'Realized P/L');
            if ($pl !== null) $lines[] = $pl;
            if (Money::isPositive($fee['base_amount'])) {
                $feeGl = self::gl('FEE_INCOME', 'income');
                $lines[] = [
                    'gl_account_id' => $feeGl['id'], 'currency_id' => $base['id'],
                    'credit' => $fee['base_amount'], 'rate' => '1', 'note' => 'Fee',
                ];
            }
            if (Money::isPositive($discount)) {
                $discGl = self::gl('DISCOUNT_GIVEN', 'expense');
                $lines[] = [
                    'gl_account_id' => $discGl['id'], 'currency_id' => $base['id'],
                    'debit' => $discount, 'rate' => '1', 'note' => 'Discount',
                ];
            }
            $entryId = LedgerService::post($lines, 'SELL ' . $currency['code'] . ' ' . $foreignAmount,
                $txId, null, self::nextEntryNo());

            self::recordMovements($txId, $srcAccount, $currency['id'], 'out', $foreignAmount, $rate, $costBase);
            self::finish($txId, 'sell', $d, $currency, $rate, $foreignAmount, $baseAmount, $entryId);
            return self::tx($txId);
        });
    }

    /**
     * EXCHANGE (multi-currency): customer gives $sourceCurrency, receives $targetCurrency.
     * Cross-rate computed so that base value of source leg (at its buy rate) ≈ base value
     * of target leg (at its sell rate); difference booked as realized P/L.
     */
    public static function exchange(array $d): array
    {
        return self::run(function () use ($d) {
            self::validateAmount($d['source_amount'] ?? '', 'source_amount');
            $base = SettingService::baseCurrency();
            $source = self::requireCurrency((int)($d['source_currency_id'] ?? 0));
            $target = self::requireCurrency((int)($d['target_currency_id'] ?? 0));
            if ($source['id'] === $target['id']) {
                throw new DomainException(t('tx.same_currency'));
            }
            $sourceAmount = Money::round((string)$d['source_amount'], 10);

            $srcRate = self::rateOr((int)$source['id'], 'buy_rate');   // we buy source currency
            $tgtRate = self::rateOr((int)$target['id'], 'sell_rate');  // we sell target currency
            self::validateAmount($srcRate, 'source rate');
            self::validateAmount($tgtRate, 'target rate');

            $srcBase = Money::round(Money::mul($sourceAmount, $srcRate), 10);
            // Target amount such that its sell-rate value equals source's buy-rate value
            $targetAmount = Money::round(Money::div($srcBase, $tgtRate, 10), 10);
            $crossRate = Money::round(Money::div($targetAmount, $sourceAmount, 10), 10);

            $fee = self::feeOf($d, $srcBase, $base, $target, $crossRate);
            $discount = self::discountOf($d, $srcBase);

            // Fee/discount are settled in source-currency terms: the customer actually
            // hands over sourceAmount + feeInSource − discountInSource.
            $feeSource = Money::isZero($fee['base_amount'])
                ? Money::zero()
                : Money::round(Money::div($fee['base_amount'], $srcRate, 10), 10);
            $discountSource = Money::isZero($discount)
                ? Money::zero()
                : Money::round(Money::div($discount, $srcRate, 10), 10);
            $totalSource = Money::round(Money::add(Money::sub($sourceAmount, $discountSource), $feeSource), 10);
            if (Money::compare($totalSource, '0') <= 0) {
                throw new DomainException(t('tx.discount_exceeds'));
            }
            $srcBaseTotal = Money::round(Money::mul($totalSource, $srcRate), 10);

            $srcAccount = (int)($d['source_account_id'] ?? 0);
            $tgtAccount = (int)($d['destination_account_id'] ?? 0);
            self::requireAccount($srcAccount);
            self::requireAccount($tgtAccount);

            $targetCosting = InventoryService::costing($target['id']);
            if (Money::compare((string)$targetCosting['qty'], $targetAmount) < 0) {
                throw new DomainException(t('tx.insufficient') . ' ' . $target['code']);
            }

            $targetCostBase = Money::round(Money::mul($targetAmount, (string)$targetCosting['avg_cost']), 10);
            // Margin absorbs the exact source leg (incl. fee/discount settled in source
            // currency) so the entry balances to the last decimal:
            // realized = srcBaseTotal − targetCostBase − fee + discount
            $realized = Money::round(
                Money::add(Money::sub(Money::sub($srcBaseTotal, $targetCostBase), $fee['base_amount']), $discount),
                10
            );

            $txId = self::createTx([
                'type' => 'exchange', 'customer_id' => $d['customer_id'] ?? null,
                'currency_id' => $target['id'], 'rate' => $crossRate,
                'foreign_amount' => $targetAmount, 'base_amount' => $srcBase,
                'fee_amount' => $fee['amount'], 'fee_currency_id' => $fee['currency_id'],
                'discount_amount' => $discount, 'total_amount' => $targetAmount,
                'realized_pl' => $realized, 'pl_currency_id' => $target['id'],
                'payment_method' => $d['payment_method'] ?? 'cash',
                'source_account_id' => $srcAccount, 'destination_account_id' => $tgtAccount,
                'notes' => $d['notes'] ?? null, 'tx_date' => $d['tx_date'] ?? null,
            ], $d['large_confirmed'] ?? false);

            Database::insert('transaction_items', [
                'transaction_id' => $txId, 'line_no' => 1,
                'source_currency_id' => $source['id'], 'target_currency_id' => $target['id'],
                'source_amount' => $sourceAmount, 'target_amount' => $targetAmount,
                'rate' => $crossRate, 'base_amount' => $srcBase,
            ]);

            $lines = [[
                'account_id' => $srcAccount, 'currency_id' => $source['id'],
                'debit' => $totalSource, 'rate' => $srcRate, 'note' => 'EXCHANGE in ' . $source['code'],
            ]];
            if (Money::isPositive($targetCostBase)) {
                $lines[] = [
                    'account_id' => $tgtAccount, 'currency_id' => $target['id'],
                    'credit' => $targetAmount, 'rate' => (string)$targetCosting['avg_cost'],
                    'note' => 'EXCHANGE out ' . $target['code'],
                ];
            }
            $plGl = self::gl('REALIZED_PL', 'income');
            $pl = self::plLine($plGl, $realized, 'Exchange margin');
            if ($pl !== null) $lines[] = $pl;
            if (Money::isPositive($fee['base_amount'])) {
                $feeGl = self::gl('FEE_INCOME', 'income');
                $lines[] = [
                    'gl_account_id' => $feeGl['id'], 'currency_id' => $base['id'],
                    'credit' => $fee['base_amount'], 'rate' => '1', 'note' => 'Fee',
                ];
            }
            if (Money::isPositive($discount)) {
                $discGl = self::gl('DISCOUNT_GIVEN', 'expense');
                $lines[] = [
                    'gl_account_id' => $discGl['id'], 'currency_id' => $base['id'],
                    'debit' => $discount, 'rate' => '1', 'note' => 'Discount',
                ];
            }
            $entryId = LedgerService::post($lines, 'EXCHANGE ' . $source['code'] . '→' . $target['code'],
                $txId, null, self::nextEntryNo());

            self::recordMovements($txId, $srcAccount, $source['id'], 'in', $totalSource, $srcRate, $srcBaseTotal);
            self::recordMovements($txId, $tgtAccount, $target['id'], 'out', $targetAmount, $tgtRate, $targetCostBase);
            // Both legs affect inventory — recalculate both costings
            InventoryService::recalculate((int)$source['id']);
            InventoryService::recalculate((int)$target['id']);
            if (!empty($d['customer_id'])) CustomerService::rebuildBalance((int)$d['customer_id']);
            AuditService::log('EXCHANGE_transaction', 'transaction', $txId, null, [
                'source' => $source['code'], 'target' => $target['code'],
                'source_amount' => $sourceAmount, 'target_amount' => $targetAmount,
                'cross_rate' => $crossRate, 'entry_id' => $entryId,
            ]);
            return self::tx($txId);
        });
    }

    /**
     * REVERSAL: create a reversal transaction linked to the original.
     * Original is never deleted; status becomes 'reversed'; ledger is reversed.
     */
    public static function reverse(int $txId, ?string $reason = null): array
    {
        return self::run(function () use ($txId, $reason) {
            $tx = self::tx($txId);
            if (!$tx) throw new DomainException('Transaction not found.');
            if ($tx['status'] === 'reversed') throw new DomainException(t('tx.already_reversed'));
            if ($tx['status'] !== 'completed') throw new DomainException(t('tx.cannot_reverse'));
            if ($tx['original_transaction_id']) throw new DomainException(t('tx.cannot_reverse'));

            $entries = Database::query("SELECT id FROM journal_entries WHERE transaction_id = ?", [$txId]);
            if (!$entries) throw new DomainException(t('tx.cannot_reverse'));

            $revNumber = self::nextNumber();
            $revId = Database::insert('transactions', [
                'tx_number' => $revNumber, 'type' => 'reversal', 'status' => 'completed',
                'customer_id' => $tx['customer_id'], 'employee_id' => Auth::id(),
                'currency_id' => $tx['currency_id'], 'rate' => $tx['rate'],
                'foreign_amount' => $tx['foreign_amount'], 'base_amount' => $tx['base_amount'],
                'fee_amount' => $tx['fee_amount'], 'fee_currency_id' => $tx['fee_currency_id'],
                'discount_amount' => $tx['discount_amount'], 'total_amount' => $tx['total_amount'],
                'payment_method' => $tx['payment_method'],
                'source_account_id' => $tx['source_account_id'],
                'destination_account_id' => $tx['destination_account_id'],
                'notes' => ($tx['notes'] ? $tx['notes'] . ' ' : '') . '[REVERSAL] ' . ($reason ?? ''),
                'original_transaction_id' => $txId, 'tx_date' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($entries as $en) {
                LedgerService::reverseEntry((int)$en['id'], 'REVERSAL of ' . $tx['tx_number'], $revId);
            }

            Database::update('transactions', ['status' => 'reversed', 'reversal_transaction_id' => $revId],
                'id = ?', [$txId]);
            // An exchange touches two currencies — recalculate costing for every currency
            // this transaction affected so reversals restore both costings.
            $touched = Database::query(
                "SELECT DISTINCT l.currency_id FROM journal_lines l
                 JOIN journal_entries je ON je.id = l.entry_id
                 WHERE je.transaction_id = ?", [$txId]);
            foreach ($touched as $tc) {
                InventoryService::recalculate((int)$tc['currency_id']);
            }
            if ($tx['customer_id']) CustomerService::rebuildBalance((int)$tx['customer_id']);

            AuditService::log('cancel_transaction', 'transaction', $txId,
                ['status' => $tx['status']], ['status' => 'reversed', 'reversal_id' => $revId], $reason);
            return self::tx($revId);
        });
    }

    /** Reconcile: approved adjustment posts an inventory adjustment journal + movement. */
    public static function adjustInventory(
        int $accountId, int $currencyId, string $difference, string $reason, int $reconciliationId
    ): void {
        $base = SettingService::baseCurrency();
        $rate = self::rateOr($currencyId, 'mid_rate');
        if (Money::isZero($rate)) $rate = '1';
        $baseAmount = Money::round(Money::mul($difference, $rate), 10);
        $adjGl = self::gl('INV_ADJUSTMENT', 'expense');

        $lines = [];
        $adjLine = ['gl_account_id' => $adjGl['id'], 'currency_id' => $base['id'],
            'rate' => '1', 'note' => 'Inventory adjustment'];
        if (Money::isPositive($difference)) {
            // physical > system: add inventory (debit asset), credit the adjustment GL
            $lines[] = ['account_id' => $accountId, 'currency_id' => $currencyId,
                'debit' => $difference, 'rate' => $rate, 'note' => 'Reconciliation ' . $reason];
            $adjLine['credit'] = Money::abs($baseAmount);
        } else {
            // physical < system: remove inventory (credit asset), debit the adjustment GL
            $lines[] = ['account_id' => $accountId, 'currency_id' => $currencyId,
                'credit' => Money::abs($difference), 'rate' => $rate, 'note' => 'Reconciliation ' . $reason];
            $adjLine['debit'] = Money::abs($baseAmount);
        }
        $lines[] = $adjLine;

        LedgerService::post($lines, 'INVENTORY ADJUSTMENT #' . $reconciliationId, null, null, self::nextEntryNo());
        InventoryService::recalculate($currencyId);
    }

    // ------------------------------------------------------------------ helpers

    private static function run(callable $fn): array
    {
        try {
            return Database::transaction($fn);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Financial operation failed: ' . $e->getMessage());
        }
    }

    private static function createTx(array $data, $largeConfirmed): int
    {
        $largeConfirmed = (bool)$largeConfirmed;
        $number = self::nextNumber();
        $base = SettingService::baseCurrency();
        $threshold = SettingService::largeTxThreshold();
        $large = Money::compare((string)($data['base_amount'] ?? '0'), $threshold) >= 0;
        if ($large && !$largeConfirmed) {
            throw new LargeTransactionException();
        }

        // Closed-day protection: no new transactions on a closed day unless the
        // operator holds closing_approve (reversals bypass — they are corrections).
        if (($data['type'] ?? '') !== 'reversal') {
            $txDay = !empty($data['tx_date']) ? substr((string)$data['tx_date'], 0, 10) : date('Y-m-d');
            ClosingService::guardWrite($txDay);
        }

        $now = date('Y-m-d H:i:s');
        return Database::insert('transactions', array_merge([
            'tx_number' => $number,
            'status' => 'completed',
            'employee_id' => Auth::id(),
            'tx_date' => $now,
            'created_at' => $now,
            'completed_at' => $now,
            'is_large' => $large ? 1 : 0,
        ], array_filter($data, fn($v) => $v !== null && $v !== '')));
    }

    private static function finish(int $txId, string $type, array $d, array $currency, string $rate,
                                  string $foreign, string $baseAmount, int $entryId): void
    {
        InventoryService::recalculate((int)$currency['id']);
        if (!empty($d['customer_id'])) CustomerService::rebuildBalance((int)$d['customer_id']);
        AuditService::log(strtoupper($type) . '_transaction', 'transaction', $txId,
            null, [
                'currency' => $currency['code'], 'amount' => $foreign, 'rate' => $rate,
                'base_amount' => $baseAmount, 'entry_id' => $entryId,
            ]);
    }

    /** P&L line: credit when profit, debit when loss (journal lines stay non-negative). */
    private static function plLine(array $glAccount, string $amount, string $note): ?array
    {
        if (Money::isZero($amount)) return null; // break-even: no P&L line needed
        $base = SettingService::baseCurrency();
        $line = ['gl_account_id' => (int)$glAccount['id'], 'currency_id' => (int)$base['id'],
            'rate' => '1', 'note' => $note];
        if (Money::isNegative($amount)) {
            $line['debit'] = Money::abs($amount);
        } else {
            $line['credit'] = $amount;
        }
        return $line;
    }

    private static function recordMovements(int $txId, int $accountId, int $currencyId, string $dir,
                                            string $amount, string $rate, string $baseAmount): void
    {
        $balanceAfter = LedgerService::accountBalance($accountId, $currencyId);
        InventoryService::movement($txId, $accountId, $currencyId, $dir, $amount, $rate, $baseAmount,
            $balanceAfter);
    }

    private static function feeOf(array $d, string $baseAmount, array $base, array $currency, string $rate): array
    {
        $feeAmount = $d['fee_amount'] ?? '0';
        if ($feeAmount === '' || $feeAmount === null) $feeAmount = '0';
        if (Money::isZero($feeAmount)) {
            return ['amount' => '0', 'currency_id' => $base['id'], 'base_amount' => '0'];
        }
        // Percent fee: computed against the base (subtotal) amount.
        if (($d['fee_type'] ?? 'fixed') === 'percent') {
            $baseFee = Money::round(Money::div(Money::mul($baseAmount, $feeAmount), '100', 10), 10);
            return ['amount' => $baseFee, 'currency_id' => $base['id'], 'base_amount' => $baseFee];
        }
        $feeCurId = (int)($d['fee_currency_id'] ?? $base['id']);
        if ($feeCurId === (int)$base['id']) {
            return ['amount' => Money::round($feeAmount, 10), 'currency_id' => $feeCurId,
                'base_amount' => Money::round($feeAmount, 10)];
        }
        return ['amount' => Money::round($feeAmount, 10), 'currency_id' => $feeCurId,
            'base_amount' => Money::round(Money::mul($feeAmount, $rate), 10)];
    }

    /** Discount resolved to base currency: fixed amount or percent of the subtotal. */
    private static function discountOf(array $d, string $baseAmount): string
    {
        $discount = $d['discount_amount'] ?? '0';
        if ($discount === '' || $discount === null) $discount = '0';
        if (Money::isZero($discount)) return Money::zero();
        if (($d['discount_type'] ?? 'fixed') === 'percent') {
            return Money::round(Money::div(Money::mul($baseAmount, $discount), '100', 10), 10);
        }
        return Money::round($discount, 10);
    }

    private static function validateAmount(string $value, string $field): void
    {
        if (!is_numeric($value)) throw new DomainException(t('validate.number'));
        if (Money::compare($value, '0') <= 0) throw new DomainException(t('validate.positive'));
    }

    private static function requireCurrency(int $id): array
    {
        $c = Database::fetch("SELECT * FROM currencies WHERE id = ? AND is_active = 1", [$id]);
        if (!$c) throw new DomainException(t('tx.inactive_currency'));
        return $c;
    }

    private static function requireAccount(int $id): void
    {
        $a = Database::fetch("SELECT id FROM accounts WHERE id = ? AND is_active = 1", [$id]);
        if (!$a) throw new DomainException(t('tx.invalid_account'));
    }

    private static function rateOr(int $currencyId, string $col): string
    {
        $row = Database::fetch("SELECT $col FROM exchange_rates WHERE currency_id = ?", [$currencyId]);
        if (!$row || Money::isZero((string)$row[$col])) {
            throw new DomainException(t('tx.rate_missing'));
        }
        return (string)$row[$col];
    }

    private static function gl(string $code, string $type): array
    {
        $g = Database::fetch("SELECT * FROM gl_accounts WHERE code = ?", [$code]);
        if ($g) return $g;
        $id = Database::insert('gl_accounts', ['code' => $code, 'name' => $code, 'type' => $type, 'is_system' => 1]);
        return Database::fetch("SELECT * FROM gl_accounts WHERE id = ?", [$id]);
    }

    public static function tx(int $id): ?array
    {
        return Database::fetch("SELECT t.*, c.code AS currency_code, c.symbol AS currency_symbol,
            cu.full_name AS customer_name,
            src.name AS source_account_name, dst.name AS destination_account_name
            FROM transactions t
            LEFT JOIN currencies c ON c.id = t.currency_id
            LEFT JOIN customers cu ON cu.id = t.customer_id
            LEFT JOIN accounts src ON src.id = t.source_account_id
            LEFT JOIN accounts dst ON dst.id = t.destination_account_id
            WHERE t.id = ?", [$id]);
    }

    public static function nextEntryNo(): string
    {
        return LedgerService::nextEntryNo();
    }
}

/** Thrown when a transaction exceeds the configured large-transaction threshold. */
final class LargeTransactionException extends DomainException
{
    public function __construct()
    {
        parent::__construct('large_tx_confirm_required');
    }
}
