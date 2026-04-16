<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Services\LoanEligibilityService;
use App\Services\LoanRepaymentService;
use Filament\Widgets\Widget;

class MemberWelcomeBannerWidget extends Widget
{
    protected string $view = 'filament.member.widgets.member-welcome-banner';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $user = auth()->user();
        $member = $user?->member;
        $now = now();

        $greeting = match (true) {
            $now->hour < 12 => 'Good morning',
            $now->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        if (!$member) {
            return [
                'greeting' => $greeting,
                'name' => $user?->name ?? 'Member',
                'date' => $now->format('l, F j Y'),
                'hasMember' => false,
                'cash' => 0,
                'fund' => 0,
                'activeLoan' => null,
                'nextPayment' => null,
                'overdueCount' => 0,
                'paidThisMonth' => false,
            ];
        }

        $cash = (float) ($member->cashAccount()?->balance ?? 0);
        $fund = (float) ($member->fundAccount()?->balance ?? 0);

        $activeLoan = Loan::where('member_id', $member->id)
            ->where('status', 'active')
            ->first();

        $nextInstallment = $activeLoan
            ? LoanInstallment::where('loan_id', $activeLoan->id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->first()
            : null;

        $nextContribution = [
            'amount' => $member->monthly_contribution_amount ?? 500,
            'label' => 'Monthly contribution',
        ];

        $nextPayment = $nextInstallment
            ? ['amount' => (float) $nextInstallment->amount, 'label' => 'Loan installment due ' . $nextInstallment->due_date->format('d M'), 'type' => 'installment']
            : ['amount' => $nextContribution['amount'], 'label' => $nextContribution['label'], 'type' => 'contribution'];

        $overdueCount = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        $paidThisMonth = Contribution::where('member_id', $member->id)
            ->where('month', $now->month)
            ->where('year', $now->year)
            ->exists();

        // Eligibility check
        $eligService  = app(LoanEligibilityService::class);
        $isEligible   = $eligService->isEligible($member);

        // Pending loan check
        $pendingLoan  = Loan::where('member_id', $member->id)->where('status', 'pending')->first();

        // Repayment availability
        $repayService = app(LoanRepaymentService::class);
        $canRepay     = $repayService->shouldOfferOpenPeriodRepayment($member);
        $repayInsufficient = $canRepay && $repayService->hasInsufficientCashForOpenPeriodRepayment($member);

        // Contribution status
        $contributionDue = !$paidThisMonth && $member->isActive();

        // Overdue installment amounts
        $overdueAmount = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->sum('amount');

        return [
            'greeting' => $greeting,
            'name' => $user->name,
            'memberNumber' => $member->member_number,
            'date' => $now->format('l, F j Y'),
            'hasMember' => true,
            'memberStatus' => $member->status,
            'cash' => $cash,
            'fund' => $fund,
            'activeLoan' => $activeLoan,
            'nextPayment' => $nextPayment,
            'overdueCount' => $overdueCount,
            'overdueAmount' => (float) $overdueAmount,
            'paidThisMonth' => $paidThisMonth,
            'contributionDue' => $contributionDue,
            'isEligible' => $isEligible,
            'pendingLoan' => $pendingLoan,
            'canRepay' => $canRepay,
            'repayInsufficient' => $repayInsufficient,
        ];
    }
}
