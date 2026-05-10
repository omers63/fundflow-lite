<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\FiscalYearAccountSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Deletes fiscal-year fact slices from the primary DB after close, persists per-account balances
 * (closing snapshot), then recounts ledger balances so `accounts.balance` matches remaining postings.
 */
final class FiscalYearPrimaryPurgeService
{
    public function __construct(
        private readonly FiscalYearArchiveConnectionService $archivePaths,
        private readonly LedgerAccountBalanceRecalculationService $recalculateBalances,
    ) {}

    public function purgePrimaryFactsForArchivedYear(FiscalYear $fiscalYear, FiscalYearClosingService $sliceProvider): void
    {
        if ($fiscalYear->status !== 'closed') {
            throw new RuntimeException('Only a closed fiscal year can be purged from primary.');
        }
        if ($fiscalYear->purged_primary_at !== null) {
            throw new RuntimeException('Primary data for '.$fiscalYear->code.' was already purged.');
        }

        $absoluteArchive = $this->archivePaths->resolveAbsoluteArchivePath($fiscalYear);
        if (! File::isFile($absoluteArchive)) {
            throw new RuntimeException(
                'Archive SQLite file missing at '.$absoluteArchive.'. Close and verify archive copy before purge.'
            );
        }

        $lock = Cache::lock('fiscal-year-purge-'.$fiscalYear->id, 900);

        try {
            $lock->block(10);

            DB::transaction(function () use ($fiscalYear, $sliceProvider): void {
                FiscalYearAccountSnapshot::query()->where('fiscal_year_id', $fiscalYear->id)->delete();

                foreach (
                    DB::table('accounts')
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->cursor() as $account
                ) {
                    FiscalYearAccountSnapshot::query()->create([
                        'fiscal_year_id' => $fiscalYear->id,
                        'account_id' => (int) $account->id,
                        'closing_balance' => $account->balance,
                    ]);
                }

                $ctx = $sliceProvider->archiveContextFor($fiscalYear);

                $factRowsPurged = [];
                foreach ($sliceProvider->archivedFactTables() as $item) {
                    $factRowsPurged[$item['table']] = (int) $sliceProvider->factSourceQuery(
                        $item['table'],
                        $item['date_column'],
                        $fiscalYear,
                        $ctx
                    )->count();
                }

                foreach (array_reverse($sliceProvider->archivedFactTables()) as $item) {
                    $table = $item['table'];
                    $dateColumn = $item['date_column'];

                    $ids = $sliceProvider->factSourceQuery($table, $dateColumn, $fiscalYear, $ctx)
                        ->clone()
                        ->orderBy($table.'.id')
                        ->pluck($table.'.id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach (array_chunk($ids, 400) as $chunk) {
                        DB::table($table)->whereIn('id', $chunk)->delete();
                    }
                }

                $snapshotCount = FiscalYearAccountSnapshot::query()
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->count();

                $fiscalYear->update([
                    'purged_primary_at' => now(),
                    'purge_metadata' => [
                        'fact_rows_archived_then_purged' => $factRowsPurged,
                        'account_snapshots' => $snapshotCount,
                    ],
                ]);

                $this->recalculateBalances->recalculateAllAccountsOnPrimaryConnection();
            });

            $sliceProvider->forgetArchiveContextCache();
        } finally {
            optional($lock)->release();
        }
    }
}
