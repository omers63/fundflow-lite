<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class LoanStatsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.loan-stats';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $statuses = ['pending', 'approved', 'active', 'completed', 'early_settled', 'rejected', 'cancelled'];

        $counts = Loan::selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(amount_approved),0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $byStatus = [];
        foreach ($statuses as $s) {
            $row = $counts->get($s);
            $byStatus[$s] = [
                'count' => (int) ($row->cnt ?? 0),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        $overdueCount = LoanInstallment::where('status', 'overdue')->count();
        $overdueAmount = (float) LoanInstallment::where('status', 'overdue')->sum('amount');

        $pendingAmount = (float) Loan::whereIn('status', ['pending', 'approved'])->sum('amount_requested');

        $thisMonth = Carbon::now();
        $newThisMonth = Loan::whereMonth('applied_at', $thisMonth->month)
            ->whereYear('applied_at', $thisMonth->year)
            ->count();

        $disbursedThisMonth = Loan::whereMonth('disbursed_at', $thisMonth->month)
            ->whereYear('disbursed_at', $thisMonth->year)
            ->sum('amount_approved');

        $totalActive = $byStatus['active']['count'];
        $totalActiveSAR = $byStatus['active']['total'];

        $totalQueue = $byStatus['pending']['count'] + $byStatus['approved']['count'];

        return [
            'by_status' => $byStatus,
            'overdue_count' => $overdueCount,
            'overdue_amount' => $overdueAmount,
            'pending_queue_count' => $totalQueue,
            'pending_queue_amount' => $pendingAmount,
            'active_count' => $totalActive,
            'active_amount' => $totalActiveSAR,
            'new_this_month' => $newThisMonth,
            'disbursed_this_month' => (float) $disbursedThisMonth,
        ];
    }
}
