<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Widgets\Widget;

/**
 * Single header widget for member view/edit: combines account stats, profile, and activity.
 * Avoids multiple lazy Livewire children (which can break morphing / memo after save).
 */
class MemberRecordInsightsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.member-record-insights';

    public ?Member $record = null;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    protected function getViewData(): array
    {
        $member = $this->resolveMember();

        return [
            'stats' => $this->buildStatsData($member),
            'profile' => $this->buildProfileData($member),
            'activity' => $this->buildActivityData($member),
        ];
    }

    protected function resolveMember(): ?Member
    {
        if (!$this->record) {
            return null;
        }

        return Member::query()->find($this->record->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStatsData(?Member $member): array
    {
        if (!$member) {
            return ['hasRecord' => false];
        }

        $member->load(['accounts', 'loans']);

        $cashBalance = (float) ($member->cashAccount()?->balance ?? 0);
        $fundBalance = (float) ($member->fundAccount()?->balance ?? 0);
        $minFund = Setting::loanMinFundBalance();
        $fundPct = $minFund > 0 ? min(100, round($fundBalance / $minFund * 100)) : 100;

        $lateCount = $member->contributionsMarkedLateCount();
        $lateAmount = $member->contributionsMarkedLateAmount();

        $activeLoans = $member->loans()->whereIn('status', ['active', 'approved', 'disbursed'])->get();
        $activeLoansCount = $activeLoans->count();
        $outstandingAmt = 0.0;
        foreach ($activeLoans as $loan) {
            $outstandingAmt += (float) LoanInstallment::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('amount');
        }

        $overdueInstallments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        $lateRepayCount = (int) ($member->late_repayment_count ?? 0);
        $lateRepayAmount = (float) ($member->late_repayment_amount ?? 0);

        $totalContributions = (float) Contribution::where('member_id', $member->id)->sum('amount');
        $contribCount = Contribution::where('member_id', $member->id)->count();

        $eligibilityMonths = Setting::loanEligibilityMonths();
        $loanStart = $member->loanEligibilityStartDate();
        $eligible = $loanStart !== null
            && $loanStart->copy()->addMonths($eligibilityMonths)->isPast()
            && $fundBalance >= $minFund;

        $maxBorrow = $fundBalance * Setting::loanMaxBorrowMultiplier();

        $nextInstallment = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        $now = now();
        $paidThisMonth = Contribution::where('member_id', $member->id)
            ->where('month', $now->month)->where('year', $now->year)->exists();

        return [
            'hasRecord' => true,
            'cash_balance' => $cashBalance,
            'fund_balance' => $fundBalance,
            'fund_pct' => $fundPct,
            'min_fund' => $minFund,
            'net_worth' => $cashBalance + $fundBalance,
            'total_contributions' => $totalContributions,
            'contrib_count' => $contribCount,
            'late_count' => $lateCount,
            'late_amount' => $lateAmount,
            'active_loans_count' => $activeLoansCount,
            'outstanding_amt' => $outstandingAmt,
            'overdue_installments' => $overdueInstallments,
            'late_repay_count' => $lateRepayCount,
            'late_repay_amount' => $lateRepayAmount,
            'eligible' => $eligible,
            'max_borrow' => $maxBorrow,
            'next_installment' => $nextInstallment,
            'paid_this_month' => $paidThisMonth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProfileData(?Member $member): array
    {
        if (!$member) {
            return ['hasRecord' => false];
        }

        $member->load(['user', 'parent.user', 'dependents.user']);
        $user = $member->user;
        $app = $member->latestMembershipApplication();

        $monthsActive = $member->joined_at
            ? (int) $member->joined_at->diffInMonths(now()) + 1
            : 0;

        $contribCount = Contribution::where('member_id', $member->id)->count();
        $complianceRate = $monthsActive > 0
            ? min(100, round($contribCount / $monthsActive * 100))
            : 0;

        $eligibilityMonths = Setting::loanEligibilityMonths();
        $loanEligibleDate = $member->loanEligibilityStartDate()?->copy()->addMonths($eligibilityMonths);
        $isLoanEligibleAge = $loanEligibleDate?->isPast() ?? false;

        $targetPage = $this->memberResourceTargetPage();

        $parentUrl = null;
        if ($member->parent_id !== null) {
            $parentUrl = MemberResource::getUrl($targetPage, ['record' => $member->parent_id]);
        }

        $dependents = $member->dependents->map(fn(Member $d) => [
            'id' => $d->id,
            'number' => $d->member_number,
            'name' => $d->user?->name ?? '—',
            'url' => MemberResource::getUrl($targetPage, ['record' => $d->id]),
        ]);

        return [
            'hasRecord' => true,
            'member_number' => $member->member_number,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->format('d M Y') ?? '—',
            'months_active' => $monthsActive,
            'monthly_contrib' => (int) $member->monthly_contribution_amount,
            'compliance_rate' => $complianceRate,
            'is_loan_eligible_age' => $isLoanEligibleAge,
            'loan_eligible_date' => $loanEligibleDate?->format('d M Y') ?? '—',

            'name' => $user?->name ?? '—',
            'email' => $user?->email ?? '—',
            'phone' => $user?->phone ?? $app?->mobile_phone ?? '—',

            'gender' => $app?->gender ?? null,
            'dob' => $app?->date_of_birth?->format('d M Y') ?? null,
            'national_id' => $app?->national_id ?? null,
            'city' => $app?->city ?? null,
            'occupation' => $app?->occupation ?? null,
            'employer' => $app?->employer ?? null,
            'monthly_income' => $app?->monthly_income ? (float) $app->monthly_income : null,

            'next_of_kin_name' => $app?->next_of_kin_name ?? null,
            'next_of_kin_phone' => $app?->next_of_kin_phone ?? null,

            'parent_number' => $member->parent?->member_number,
            'parent_name' => $member->parent?->user?->name,
            'parent_url' => $parentUrl,
            'dependents' => $dependents,

            'application_edit_url' => $app !== null
                ? MembershipApplicationResource::getUrl('edit', ['record' => $app])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildActivityData(?Member $member): array
    {
        if (!$member) {
            return ['hasRecord' => false];
        }

        $months = collect(range(11, 0))->map(fn($i) => now()->subMonths($i)->startOfMonth());
        $contribs = Contribution::where('member_id', $member->id)
            ->orderByRaw('year DESC, month DESC')
            ->get();

        $monthlyContrib = $member->monthly_contribution_amount;

        $grid = $months->map(function (Carbon $m) use ($contribs, $monthlyContrib) {
            $row = $contribs->first(fn($c) => $c->month == $m->month && $c->year == $m->year);
            $paid = $row !== null;
            $underpaid = $paid && (float) $row->amount < (float) $monthlyContrib;

            return [
                'label' => $m->format('M y'),
                'amount' => $row ? (float) $row->amount : 0.0,
                'paid' => $paid,
                'late' => $paid && (bool) $row->is_late,
                'underpaid' => $underpaid,
                'future' => $m->isFuture(),
            ];
        });

        $paidCount = $grid->where('paid', true)->count();
        $missedCount = $grid->where('paid', false)->where('future', false)->count();

        $installments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->with('loan')
            ->orderBy('due_date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($i) => [
                'loan_id' => $i->loan_id,
                'due_date' => $i->due_date instanceof Carbon ? $i->due_date->format('d M Y') : $i->due_date,
                'amount' => number_format((float) $i->amount, 2),
                'status' => $i->status,
                'is_late' => (bool) ($i->is_late ?? false),
                'paid_at' => $i->paid_at ? Carbon::parse($i->paid_at)->format('d M Y') : null,
            ]);

        $loans = Loan::where('member_id', $member->id)
            ->orderBy('applied_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function (Loan $l) {
                return [
                    'id' => $l->id,
                    'amount' => number_format((float) ($l->amount_approved ?? $l->amount_requested), 2),
                    'status' => $l->status,
                    'applied_at' => $l->applied_at ? Carbon::parse($l->applied_at)->format('d M Y') : '—',
                    'disbursed_at' => $l->disbursed_at ? Carbon::parse($l->disbursed_at)->format('d M Y') : null,
                    'fully_paid_at' => $l->fully_paid_at ? Carbon::parse($l->fully_paid_at)->format('d M Y') : null,
                    'installment_count' => (int) ($l->installments_count ?? 0),
                ];
            });

        $chartLabels = $grid->pluck('label')->values()->toArray();
        $chartData = $grid->pluck('amount')->values()->toArray();

        return [
            'hasRecord' => true,
            'monthly_contrib' => $monthlyContrib,
            'grid' => $grid->values()->toArray(),
            'paid_count' => $paidCount,
            'missed_count' => $missedCount,
            'chart_labels' => $chartLabels,
            'chart_data' => $chartData,
            'installments' => $installments->toArray(),
            'loans' => $loans->toArray(),
        ];
    }

    /**
     * Match member links to the current page (view vs edit) when the widget is shown on member view/edit.
     */
    protected function memberResourceTargetPage(): string
    {
        return request()->routeIs('filament.admin.resources.members.edit')
            ? 'edit'
            : 'view';
    }
}
