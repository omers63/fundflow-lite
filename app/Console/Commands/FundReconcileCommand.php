<?php

namespace App\Console\Commands;

use App\Models\ReconciliationSnapshot;
use App\Services\FinanceReconciliationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FundReconcileCommand extends Command
{
    protected $signature = 'fund:reconcile
        {--realtime : Point-in-time reconciliation as of now}
        {--daily : Calendar-day window (yesterday) plus full ledger checks}
        {--monthly : Previous calendar month window plus full ledger checks}
        {--no-store : Print summary only; do not write reconciliation_snapshots}';

    protected $description = 'Run financial reconciliation and optionally store a snapshot for audit.';

    public function handle(FinanceReconciliationService $service): int
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        if ($this->option('realtime')) {
            $mode = ReconciliationSnapshot::MODE_REALTIME;
            $asOf = $now;
            $periodStart = null;
            $periodEnd = null;
        } elseif ($this->option('monthly')) {
            $mode = ReconciliationSnapshot::MODE_MONTHLY;
            $asOf = $now;
            $anchor = $now->copy()->subMonthNoOverflow();
            $periodStart = $anchor->copy()->startOfMonth();
            $periodEnd = $anchor->copy()->endOfMonth();
        } else {
            $mode = ReconciliationSnapshot::MODE_DAILY;
            $asOf = $now;
            $periodStart = $now->copy()->subDay()->startOfDay();
            $periodEnd = $now->copy()->subDay()->endOfDay();
        }

        $options = FinanceReconciliationService::bankOptionsFromSettings();
        $report = $service->buildReport($mode, $asOf, $periodStart, $periodEnd, $options);

        $v = $report['verdict'];
        $this->line('Mode: ' . $mode);
        $this->line('As of: ' . $report['meta']['as_of']);
        if ($periodStart && $periodEnd) {
            $this->line('Period: ' . $periodStart->toIso8601String() . ' → ' . $periodEnd->toIso8601String());
        }
        $this->line('Pass: ' . ($v['pass'] ? 'yes' : 'no'));
        $this->line('Critical: ' . $v['critical_issues'] . ' | Warnings: ' . $v['warnings']);
        $this->line('Ledger mismatches: ' . $report['checks']['ledger_balances']['mismatch_count']);
        $this->line('Unposted bank rows: ' . $report['pipeline']['bank_unposted_count']);

        foreach ($report['coverage_matrix'] ?? [] as $row) {
            $pairs = [];
            foreach ($row['checks'] ?? [] as $c) {
                $pairs[] = (($c['key'] ?? '?') . '=' . ($c['severity'] ?? '?'));
            }
            $this->line('Coverage: ' . ($row['flow'] ?? '?') . ' → ' . implode(', ', $pairs));
        }

        if (!$this->option('no-store')) {
            $snap = $service->persistSnapshot($report, null);
            $this->line('Snapshot #' . $snap->id . ' stored.');
        }

        return $v['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
