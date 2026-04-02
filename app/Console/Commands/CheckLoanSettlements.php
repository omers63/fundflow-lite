<?php

namespace App\Console\Commands;

use App\Services\LoanDefaultService;
use Illuminate\Console\Command;

class CheckLoanSettlements extends Command
{
    protected $signature = 'loans:check-settlements';
    protected $description = 'Auto-settle loans where repayment conditions are fully met.';

    public function handle(LoanDefaultService $service): int
    {
        $this->info('Checking loan settlements...');
        $count = $service->checkSettlements();
        $this->info("✓ {$count} loan(s) settled.");
        return self::SUCCESS;
    }
}
