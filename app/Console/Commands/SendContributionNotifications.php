<?php

namespace App\Console\Commands;

use App\Services\ContributionCycleService;
use Illuminate\Console\Command;

class SendContributionNotifications extends Command
{
    protected $signature = 'contributions:notify
                            {month? : Month number (1-12); defaults to previous month}
                            {year?  : 4-digit year; defaults to current year}';

    protected $description = 'Send contribution due notifications to all active members for the given period.';

    public function handle(ContributionCycleService $service): int
    {
        [$month, $year] = $this->resolvePeriod();

        $deadline    = $service->deadline($month, $year);
        $periodLabel = $service->periodLabel($month, $year);

        $this->info("Sending contribution due notifications for {$periodLabel}");
        $this->info("Deadline: " . $deadline->format('d F Y'));

        $count = $service->sendDueNotifications($month, $year);

        $this->info("✓ Notified {$count} member(s).");

        return self::SUCCESS;
    }

    private function resolvePeriod(): array
    {
        $now   = now();
        $month = (int) ($this->argument('month') ?? $now->copy()->subMonthNoOverflow()->month);
        $year  = (int) ($this->argument('year')  ?? $now->year);

        if ($month < 1 || $month > 12) {
            $this->error("Invalid month: {$month}. Must be between 1 and 12.");
            exit(self::FAILURE);
        }

        return [$month, $year];
    }
}
