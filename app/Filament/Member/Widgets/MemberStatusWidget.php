<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\Widget;

class MemberStatusWidget extends Widget
{
    protected string $view = 'filament.member.widgets.member-status';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $user   = auth()->user();
        $member = $user?->member;

        if (!$member) {
            return ['hasMember' => false];
        }

        // Compliance score: on-time contributions out of total expected
        $totalExpected   = max(1, (int) $member->joined_at?->diffInMonths(now()) ?: 1);
        $totalContrib    = $member->contributions()->count();
        $lateContrib     = (int) $member->late_contributions_count;
        $onTimeContrib   = max(0, $totalContrib - $lateContrib);
        $complianceScore = $totalContrib > 0
            ? (int) round($onTimeContrib / max(1, $totalContrib) * 100)
            : 100;

        // Overdue installments with late fees
        $overdueInstallments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->get();
        $overdueCount      = $overdueInstallments->count();
        $overdueAmount     = (float) $overdueInstallments->sum('amount');
        $overdueLateFees   = (float) $overdueInstallments->sum('late_fee_amount');

        // Total late fees paid over all time
        $totalLateContribFees  = (float) $member->late_contributions_amount;
        $totalLateRepayFees    = (float) $member->late_repayment_amount;

        // Contribution streak: consecutive months contributed (current + prior months)
        $streak = 0;
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $contributed = Contribution::where('member_id', $member->id)
                ->where('month', $cursor->month)
                ->where('year', $cursor->year)
                ->exists();
            if (!$contributed) break;
            $streak++;
            $cursor = $cursor->subMonth();
        }

        // Is delinquent or suspended
        $isDelinquent  = $member->status === 'delinquent';
        $isSuspended   = $member->delinquency_suspended_at !== null;

        return [
            'hasMember'           => true,
            'status'              => $member->status,
            'isDelinquent'        => $isDelinquent,
            'isSuspended'         => $isSuspended,
            'suspendedAt'         => $member->delinquency_suspended_at,
            'complianceScore'     => $complianceScore,
            'totalContrib'        => $totalContrib,
            'lateContribCount'    => $lateContrib,
            'lateContribAmount'   => $totalLateContribFees,
            'lateRepayCount'      => (int) $member->late_repayment_count,
            'lateRepayAmount'     => $totalLateRepayFees,
            'overdueCount'        => $overdueCount,
            'overdueAmount'       => $overdueAmount,
            'overdueLateFees'     => $overdueLateFees,
            'streak'              => $streak,
        ];
    }
}
