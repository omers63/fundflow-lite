<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Services\FiscalYearClosingService;
use Illuminate\Console\Command;

class FiscalYearPurgeCommand extends Command
{
    protected $signature = 'fiscal:purge {year : Calendar year or FY code without prefix, e.g. 2025}
        {--force : Required to confirm deletion of primary fiscal-year facts}';

    protected $description = 'Purge archived fact rows from the primary database for a closed fiscal year (after close + snapshot).';

    public function handle(FiscalYearClosingService $service): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to purge without --force. This permanently deletes primary fact rows for that fiscal window.');

            return self::INVALID;
        }

        $arg = (string) $this->argument('year');
        $code = str_starts_with(strtoupper($arg), 'FY')
            ? strtoupper($arg)
            : 'FY'.$arg;

        $fiscalYear = FiscalYear::query()->where('code', $code)->first();
        if ($fiscalYear === null) {
            $this->error("Fiscal year {$code} not found.");

            return self::FAILURE;
        }

        try {
            $service->purgePrimaryForClosedFiscalYear($fiscalYear);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Primary facts purged for {$fiscalYear->code}. Ledger balances recalculated.");

        return self::SUCCESS;
    }
}
