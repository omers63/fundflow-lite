<?php

namespace App\Console\Commands;

use App\Services\MonthlyStatementService;
use Illuminate\Console\Command;

class GenerateMonthlyStatements extends Command
{
    protected $signature = 'statements:generate
                            {--period=         : Period in YYYY-MM format (defaults to previous month)}
                            {--current-month   : Generate for the current calendar month instead of previous}
                            {--notify          : Email and notify each member after generation}
                            {--member=         : Generate only for a specific member ID}';

    protected $description = 'Generate monthly account statements for all active members.';

    public function handle(MonthlyStatementService $service): int
    {
        $period = $this->option('period');

        if (!$period) {
            $period = $this->option('current-month')
                ? now()->format('Y-m')
                : now()->subMonth()->format('Y-m');
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM (e.g. 2025-06).");
            return self::FAILURE;
        }

        $notify    = (bool) $this->option('notify');
        $memberId  = $this->option('member');

        if ($memberId) {
            $member = \App\Models\Member::find((int) $memberId);
            if (!$member) {
                $this->error("Member #{$memberId} not found.");
                return self::FAILURE;
            }
            $statement = $service->generateForMember($member, $period, $notify);
            $num = $member->member_number;
            $this->info("Generated statement #{$statement->id} for member #{$member->id} ({$num}) — {$period}");
            return self::SUCCESS;
        }

        $notifyLabel = $notify ? 'yes' : 'no';
        $this->info("Generating statements for period: {$period} (notify={$notifyLabel})...");
        $count = $service->generateForAllMembers($period, $notify);
        $this->info("Done. {$count} statement(s) generated.");

        return self::SUCCESS;
    }
}
