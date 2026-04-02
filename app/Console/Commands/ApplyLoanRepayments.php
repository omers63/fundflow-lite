<?php

namespace App\Console\Commands;

use App\Services\LoanRepaymentService;
use Illuminate\Console\Command;

class ApplyLoanRepayments extends Command
{
    protected $signature = 'loans:apply {month?} {year?}';
    protected $description = 'Apply loan repayment installments for all eligible active borrowers.';

    public function handle(LoanRepaymentService $service): int
    {
        [$month, $year] = $this->resolvePeriod();
        $isLate         = $service->isLate($month, $year);
        $this->info("Applying loan repayments for " . $service->periodLabel($month, $year));
        if ($isLate) { $this->warn("Past deadline — repayments will be flagged as LATE."); }

        $results = $service->applyRepayments($month, $year);

        $applied      = count($results['applied']);
        $insufficient = count($results['insufficient']);
        $skipped      = count($results['skipped']);
        $this->info("✓ Applied: {$applied} | Insufficient: {$insufficient} | Skipped: {$skipped}");

        if ($insufficient > 0) {
            $this->warn("Borrowers with insufficient balance:");
            $this->table(['Loan #', 'Member', 'Required', 'Balance'], collect($results['insufficient'])->map(fn ($r) => [
                $r['loan']->id, $r['loan']->member->user->name,
                'SAR ' . number_format($r['required'], 2),
                'SAR ' . number_format($r['balance'], 2),
            ])->toArray());
        }

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
