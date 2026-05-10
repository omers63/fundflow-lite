<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile `accounts.balance` with SUM(credits − debits) on the primary connection.
 */
final class LedgerAccountBalanceRecalculationService
{
    public function recalculateAllAccountsOnPrimaryConnection(): void
    {
        $connection = Schema::getConnection()->getName();
        $driver = Schema::getConnection()->getDriverName();

        $caseSum = "COALESCE(SUM(CASE WHEN t.entry_type = 'credit' THEN t.amount ELSE -t.amount END), 0)";

        $inner = match ($driver) {
            'mysql', 'mariadb' => "CAST({$caseSum} AS DECIMAL(18,2))",
            default => "CAST({$caseSum} AS REAL)",
        };

        $sql = "UPDATE accounts SET balance = (
            SELECT {$inner}
            FROM account_transactions t
            WHERE t.account_id = accounts.id AND t.deleted_at IS NULL
        ) WHERE deleted_at IS NULL";

        DB::connection($connection)->update($sql);
    }
}
