<?php

namespace App\Console\Commands;

use App\Services\LoanDefaultService;
use Illuminate\Console\Command;

class CheckLoanDefaults extends Command
{
    protected $signature = 'loans:check-defaults';
    protected $description = 'Check for defaulted loan installments, warn borrowers, and debit guarantors.';

    public function handle(LoanDefaultService $service): int
    {
        $this->info('Checking loan defaults...');
        $results = $service->processDefaults();
        $this->info("Warned: {$results['warned']} | Debited from guarantors: {$results['debited_from_guarantor']}");
        return self::SUCCESS;
    }
}
