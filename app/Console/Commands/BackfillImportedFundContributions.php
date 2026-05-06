<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Contribution;
use Illuminate\Console\Command;

class BackfillImportedFundContributions extends Command
{
    protected $signature = 'fundflow:backfill-imported-fund-contributions {--dry-run : Show what would be created without writing}';

    protected $description = 'Create missing Contribution rows from member-import fund balance ledger credits.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = AccountTransaction::query()
            ->with('account')
            ->whereNull('deleted_at')
            ->where('entry_type', 'credit')
            ->whereNotNull('member_id')
            ->where('description', 'like', 'Fund balance adjustment — credit (member import)%')
            ->orderBy('transacted_at')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $tx) {
            if ($tx->account?->type !== Account::TYPE_MEMBER_FUND) {
                continue;
            }

            $memberId = (int) $tx->member_id;
            $month = (int) $tx->transacted_at?->month;
            $year = (int) $tx->transacted_at?->year;

            if ($month < 1 || $month > 12 || $year < 2000) {
                $skipped++;

                continue;
            }

            if (Contribution::activePeriodExists($memberId, $month, $year)) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                Contribution::create([
                    'member_id' => $memberId,
                    'month' => $month,
                    'year' => $year,
                    'amount' => (float) $tx->amount,
                    'paid_at' => $tx->transacted_at ?? now(),
                    'payment_method' => Contribution::PAYMENT_METHOD_ADMIN,
                    'reference_number' => null,
                    'notes' => "Backfilled from imported fund ledger entry #{$tx->id}",
                    'is_late' => false,
                    'late_fee_amount' => null,
                ]);
            }

            $created++;
        }

        $this->info(($dryRun ? 'Dry run: ' : '')."created={$created}, skipped={$skipped}, scanned={$rows->count()}");

        return self::SUCCESS;
    }
}
