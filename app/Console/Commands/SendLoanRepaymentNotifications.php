<?php

namespace App\Console\Commands;

use App\Services\ContributionCycleService;
use App\Services\LoanRepaymentService;
use Illuminate\Console\Command;

class SendLoanRepaymentNotifications extends Command
{
    protected $signature = 'loans:notify {month?} {year?}';

    protected $description = 'Send loan repayment due notifications to all active borrowers for the given period.';

    public function handle(LoanRepaymentService $service): int
    {
        [$month, $year] = $this->resolvePeriod();
        $this->info('Sending loan repayment due notifications for '.$service->periodLabel($month, $year));
        $count = $service->sendDueNotifications($month, $year);
        $this->info("✓ Notified {$count} borrower(s).");

        return self::SUCCESS;
    }

    private function resolvePeriod(): array
    {
        if ($this->argument('month') !== null || $this->argument('year') !== null) {
            if ($this->argument('month') === null || $this->argument('year') === null) {
                throw new \InvalidArgumentException('Provide both month and year (1–12 and four-digit year), or omit both to use the current open contribution/repayment cycle.');
            }

            return [(int) $this->argument('month'), (int) $this->argument('year')];
        }

        return app(ContributionCycleService::class)->currentOpenPeriod();
    }
}
