<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Services\FiscalYearClosingService;
use Illuminate\Console\Command;

class FiscalYearCloseCommand extends Command
{
    protected $signature = 'fiscal:close
        {year : Fiscal year, e.g. 2025}
        {--execute : Execute close (default is dry-run)}
        {--archive= : Legacy: single Laravel DB connection (omit to use one SQLite file per FY under database/archives/)}
        {--purge : After a successful archive, delete that fiscal slice from primary & recount balances}
        {--user-id=1 : User id recorded as actor}';

    protected $description = 'Dry-run or execute fiscal-year close to archive database.';

    public function handle(FiscalYearClosingService $service): int
    {
        $year = (int) $this->argument('year');
        $archiveRaw = $this->option('archive');
        $archiveOverride = $archiveRaw !== null && trim((string) $archiveRaw) !== '' ? (string) $archiveRaw : null;
        $purge = (bool) $this->option('purge');
        $userId = (int) $this->option('user-id');
        $code = "FY{$year}";

        $fiscalYear = FiscalYear::firstOrCreate(
            ['code' => $code],
            [
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
            ]
        );

        if (! $this->option('execute')) {
            $result = $service->dryRun($fiscalYear, $archiveOverride);
            $start = (string) date('Y-m-d', strtotime((string) $fiscalYear->start_date));
            $end = (string) date('Y-m-d', strtotime((string) $fiscalYear->end_date));
            $this->info("Dry-run for {$fiscalYear->code} ({$start} to {$end})");
            foreach ($result['tables'] as $table => $stats) {
                $this->line(
                    sprintf(
                        '- %s: source=%d archive=%d',
                        $table,
                        $stats['source_count'],
                        $stats['archive_count'],
                    )
                );
            }

            return self::SUCCESS;
        }

        if ($purge && $archiveOverride !== null) {
            $this->error('Use --purge without --archive (per–fiscal-year SQLite archives only).');

            return self::INVALID;
        }

        $closure = $service->close($fiscalYear, $userId, $archiveOverride, $purge);
        $this->info("Closed {$fiscalYear->code} successfully. Closure #{$closure->id}");

        return self::SUCCESS;
    }
}
