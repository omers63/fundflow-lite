<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Services\FiscalYearClosingService;
use Illuminate\Console\Command;

class FiscalYearRestoreCommand extends Command
{
    protected $signature = 'fiscal:restore
        {year : Fiscal year, e.g. 2025}
        {--archive= : Legacy archive connection — omit to use archive_database_path on the fiscal year}
        {--user-id=1 : User id recorded as actor}';

    protected $description = 'Restore archived fiscal-year rows back into primary database.';

    public function handle(FiscalYearClosingService $service): int
    {
        $year = (int) $this->argument('year');
        $archiveRaw = $this->option('archive');
        $archiveOverride = $archiveRaw !== null && trim((string) $archiveRaw) !== '' ? (string) $archiveRaw : null;
        $userId = (int) $this->option('user-id');
        $code = "FY{$year}";

        $fiscalYear = FiscalYear::where('code', $code)->first();
        if (! $fiscalYear) {
            $this->error("Fiscal year {$code} does not exist.");

            return self::FAILURE;
        }

        $closure = $service->restore($fiscalYear, $userId, $archiveOverride);
        $this->info("Restore completed for {$fiscalYear->code}. Closure #{$closure->id}");

        return self::SUCCESS;
    }
}
