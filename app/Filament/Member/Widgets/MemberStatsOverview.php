<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class MemberStatsOverview extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.member.widgets.member-stats-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $member = auth()->user()?->member;

        if (!$member) {
            return ['hasMember' => false];
        }

        $now = Carbon::now();

        $totalContributions = (float) Contribution::where('member_id', $member->id)->sum('amount');
        $contribCount = Contribution::where('member_id', $member->id)->count();

        $activeLoan = Loan::where('member_id', $member->id)->where('status', 'active')->first();

        $overdueCount = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')->count();

        $nextInstallment = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')->orderBy('due_date')->first();

        $paidThisMonth = Contribution::where('member_id', $member->id)
            ->where('month', $now->month)->where('year', $now->year)->exists();

        // Contribution streak: consecutive months paid ending now
        $streak = 0;
        for ($i = 0; $i < 24; $i++) {
            $d = $now->copy()->subMonths($i);
            $ok = Contribution::where('member_id', $member->id)
                ->where('month', $d->month)->where('year', $d->year)->exists();
            if (!$ok) {
                break;
            }
            $streak++;
        }

        $monthsActive = $member->joined_at ? (int) $member->joined_at->diffInMonths($now) + 1 : 1;
        $complianceRate = $monthsActive > 0
            ? min(100, round($contribCount / $monthsActive * 100))
            : 0;

        return [
            'hasMember' => true,
            'member_number' => $member->member_number,
            'joined_at' => $member->joined_at?->locale(app()->getLocale())->translatedFormat('d M Y') ?? '—',
            'total_contributions' => $totalContributions,
            'contrib_count' => $contribCount,
            'compliance_rate' => $complianceRate,
            'streak' => $streak,
            'active_loan' => $activeLoan,
            'overdue_count' => $overdueCount,
            'next_installment' => $nextInstallment,
            'paid_this_month' => $paidThisMonth,
        ];
    }
}
