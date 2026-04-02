<?php

namespace App\Console\Commands;

use App\Services\LoanRepaymentService;
use Illuminate\Console\Command;

class SendLoanRepaymentNotifications extends Command
{
    protected $signature = 'loans:notify {month?} {year?}';
    protected $description = 'Send loan repayment due notifications to all active borrowers for the given period.';

    public function handle(LoanRepaymentService $service): int
    {
        [$month, $year] = $this->resolvePeriod();
        $this->info("Sending loan repayment due notifications for " . $service->periodLabel($month, $year));
        $count = $service->sendDueNotifications($month, $year);
        $this->info("✓ Notified {$count} borrower(s).");
        return self::SUCCESS;
    }

    private function resolvePeriod(): array
    {
        $now   = now();
        $month = (int) ($this->argument('month') ?? $now->copy()->subMonthNoOverflow()->month);
        $year  = (int) ($this->argument('year')  ?? $now->year);
        return [$month, $year];
    }
}
