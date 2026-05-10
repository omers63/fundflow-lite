<?php

namespace App\Console\Commands;

use App\Services\ContributionCycleService;
use App\Services\LoanRepaymentService;
use Illuminate\Console\Command;

class ApplyLoanRepayments extends Command
{
    protected $signature = 'loans:apply {month?} {year?}';

    protected $description = 'Apply loan repayment installments for all eligible active borrowers.';

    public function handle(LoanRepaymentService $service): int
    {
        [$month, $year] = $this->resolvePeriod();
        $isLate = $service->isLate($month, $year);
        $this->info('Applying loan repayments for '.$service->periodLabel($month, $year));
        if ($isLate) {
            $this->warn('Past deadline — repayments will be flagged as LATE.');
        }

        $results = $service->applyRepayments($month, $year);

        $applied = count($results['applied']);
        $insufficient = count($results['insufficient']);
        $skipped = count($results['skipped']);
        $this->info("✓ Applied: {$applied} | Insufficient: {$insufficient} | Skipped: {$skipped}");

        if ($insufficient > 0) {
            $this->warn('Borrowers with insufficient balance:');
            $this->table(['Loan #', 'Member', 'Required', 'Balance'], collect($results['insufficient'])->map(fn ($r) => [
                $r['loan']->id,
                $r['loan']->member->user->name,
                'SAR '.number_format($r['required'], 2),
                'SAR '.number_format($r['balance'], 2),
            ])->toArray());
        }

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
