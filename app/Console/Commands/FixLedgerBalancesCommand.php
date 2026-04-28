<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLedgerBalancesCommand extends Command
{
    protected $signature = 'fund:fix-ledger-balances
        {--dry-run : Show mismatches but do not change any balances}';

    protected $description = 'Recompute account balances from ledger lines and fix any drifts.';

    public function handle(): int
    {
        $this->info('Recomputing balances from ledger...');

        $ledgerRows = AccountTransaction::query()
            ->selectRaw(
                'account_id, SUM(CASE WHEN entry_type = ? THEN amount ELSE -amount END) as computed',
                ['credit']
            )
            ->whereNull('deleted_at')
            ->groupBy('account_id');

        $computedByAccount = DB::query()
            ->fromSub($ledgerRows, 't')
            ->pluck('computed', 'account_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $tolerance = 0.03;
        $dryRun = (bool) $this->option('dry-run');

        $accounts = Account::query()->whereNull('deleted_at')->get();
        $fixes = [];

        foreach ($accounts as $account) {
            $computed = (float) ($computedByAccount[$account->id] ?? 0.0);
            $stored = (float) $account->balance;
            $delta = $computed - $stored;

            if (abs($delta) <= $tolerance) {
                continue;
            }

            $fixes[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'stored' => $stored,
                'computed' => $computed,
                'delta' => $delta,
            ];
        }

        if ($fixes === []) {
            $this->info('No ledger/balance drifts detected.');

            return self::SUCCESS;
        }

        $this->warn(count($fixes) . ' account(s) have balance drift:');
        foreach ($fixes as $fix) {
            $this->line(sprintf(
                '- #%d "%s" (%s): stored=%0.2f, computed=%0.2f, delta=%0.2f',
                $fix['id'],
                $fix['name'],
                $fix['type'],
                $fix['stored'],
                $fix['computed'],
                $fix['delta'],
            ));
        }

        if ($dryRun) {
            $this->comment('Dry-run only. No changes were applied.');

            return self::SUCCESS;
        }

        if (!$this->confirm('Apply fixes and align stored balances to computed ledger values?', true)) {
            $this->comment('Aborted. No changes were applied.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($fixes): void {
            foreach ($fixes as $fix) {
                Account::query()
                    ->whereKey($fix['id'])
                    ->update(['balance' => $fix['computed']]);
            }
        });

        $this->info('Balances updated from ledger for ' . count($fixes) . ' account(s).');

        return self::SUCCESS;
    }
}

