<?php

namespace App\Console\Commands;

use App\Services\DelinquencyService;
use Illuminate\Console\Command;

class CheckDelinquency extends Command
{
    protected $signature = 'fund:check-delinquency';
    protected $description = 'Mark overdue installments and flag delinquent members, then send alerts.';

    public function handle(DelinquencyService $service): int
    {
        $this->info('Checking for overdue installments...');
        $overdueUpdated = $service->markOverdueInstallments();
        $this->line("  → {$overdueUpdated} installment(s) marked as overdue.");

        $this->info('Flagging delinquent members...');
        $flagged = $service->flagDelinquentMembers();
        $this->line("  → {$flagged} member(s) flagged as delinquent and notified.");

        $this->info('Delinquency check complete.');
        return self::SUCCESS;
    }
}
