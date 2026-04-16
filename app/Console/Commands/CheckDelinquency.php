<?php

namespace App\Console\Commands;

use App\Services\DelinquencyService;
use Illuminate\Console\Command;

class CheckDelinquency extends Command
{
    protected $signature = 'fund:check-delinquency';
    protected $description = 'Mark overdue installments, evaluate contribution/repayment delinquency, suspend or restore members, transfer guarantor liability.';

    public function handle(DelinquencyService $service): int
    {
        $this->info('Checking for overdue installments...');
        $overdueUpdated = $service->markOverdueInstallments();
        $this->line("  → {$overdueUpdated} installment(s) marked as overdue.");

        $this->info('Evaluating delinquency policy (consecutive + rolling total misses)...');
        $result = $service->flagDelinquentMembers();
        $this->line("  → Suspended (policy): {$result['suspended']}");
        $this->line("  → Restored: {$result['restored']}");

        $this->info('Delinquency check complete.');
        return self::SUCCESS;
    }
}
