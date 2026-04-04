<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class MemberStatsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.member-stats';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $now = Carbon::now();

        $active = Member::where('status', 'active')->count();
        $suspended = Member::where('status', 'suspended')->count();
        $delinquent = Member::where('status', 'delinquent')->count();
        $total = $active + $suspended + $delinquent;

        $newThisMonth = Member::whereMonth('joined_at', $now->month)
            ->whereYear('joined_at', $now->year)
            ->count();

        $withActiveLoans = Member::whereHas('loans', fn($q) => $q->where('status', 'active'))->count();

        $withOverdue = Member::whereHas(
            'loans',
            fn($q) => $q->whereHas('installments', fn($i) => $i->where('status', 'overdue'))
        )->count();

        $avgContribution = (float) Member::active()
            ->avg('monthly_contribution_amount');

        // Top 5 members by contribution amount this year
        $topContributors = Member::withSum([
            'contributions as year_total' => fn($q) => $q->whereYear('paid_at', $now->year),
        ], 'amount')
            ->with('user')
            ->orderByDesc('year_total')
            ->limit(5)
            ->get()
            ->map(fn($m) => [
                'name' => $m->user?->name ?? '—',
                'number' => $m->member_number,
                'total' => (float) ($m->year_total ?? 0),
            ])
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'suspended' => $suspended,
            'delinquent' => $delinquent,
            'new_this_month' => $newThisMonth,
            'with_active_loans' => $withActiveLoans,
            'with_overdue' => $withOverdue,
            'avg_contribution' => round($avgContribution),
            'top_contributors' => $topContributors,
            'year_label' => $now->year,
        ];
    }
}
