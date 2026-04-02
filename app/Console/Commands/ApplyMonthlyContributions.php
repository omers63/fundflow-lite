<?php

namespace App\Console\Commands;

use App\Services\ContributionCycleService;
use Illuminate\Console\Command;

class ApplyMonthlyContributions extends Command
{
    protected $signature = 'contributions:apply
                            {month? : Month number (1-12); defaults to previous month}
                            {year?  : 4-digit year; defaults to current year}';

    protected $description = 'Apply monthly contributions for all eligible active members.';

    public function handle(ContributionCycleService $service): int
    {
        [$month, $year] = $this->resolvePeriod();

        $periodLabel = $service->periodLabel($month, $year);
        $deadline    = $service->deadline($month, $year);
        $isLate      = $service->isLate($month, $year);

        $this->info("Applying contributions for {$periodLabel}");
        $this->info("Deadline: " . $deadline->format('d F Y') . ($isLate ? " ⚠️  [PAST DEADLINE – will be marked late]" : " ✓"));

        if ($isLate) {
            $this->warn("Contributions will be flagged as LATE.");
        }

        $results = $service->applyContributions($month, $year);

        $applied      = count($results['applied']);
        $insufficient = count($results['insufficient']);
        $skipped      = count($results['skipped']);

        $this->newLine();
        $this->info("✓ Applied:       {$applied}");
        $this->warn("⚠ Insufficient:  {$insufficient}");
        $this->line("  Skipped:       {$skipped} (already processed)");

        if ($insufficient > 0) {
            $this->newLine();
            $this->warn("Members with insufficient cash balance:");
            $headers = ['Member #', 'Name', 'Required (SAR)', 'Balance (SAR)', 'Shortfall (SAR)'];
            $rows    = collect($results['insufficient'])->map(fn ($row) => [
                $row['member']->member_number,
                $row['member']->user->name,
                number_format($row['required'], 2),
                number_format($row['balance'], 2),
                number_format($row['required'] - $row['balance'], 2),
            ])->toArray();

            $this->table($headers, $rows);
        }

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
