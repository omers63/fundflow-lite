<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\MemberSubscriptionFee;
use App\Models\MembershipApplication;
use Filament\Widgets\Widget;

class FeesRevenueWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.fees-revenue';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $year = now()->year;

        // ── Late fees ─────────────────────────────────────────────────────────
        $lateFeeAllTime = (float) Contribution::sum('late_fee_amount')
            + (float) LoanInstallment::sum('late_fee_amount');

        $lateFeeThisYear = (float) Contribution::whereYear('paid_at', $year)->sum('late_fee_amount')
            + (float) LoanInstallment::whereYear('paid_at', $year)->sum('late_fee_amount');

        $lateFeeCount = (int) Contribution::where('is_late', true)->count()
            + (int) LoanInstallment::where('is_late', true)->count();

        $lateFeeCountThisYear = (int) Contribution::where('is_late', true)->whereYear('paid_at', $year)->count()
            + (int) LoanInstallment::where('is_late', true)->whereYear('paid_at', $year)->count();

        // ── Membership application fees ───────────────────────────────────────
        $membershipFeeAllTime = (float) MembershipApplication::whereNotNull('membership_fee_posted_at')
            ->sum('membership_fee_amount');

        $membershipFeeThisYear = (float) MembershipApplication::whereNotNull('membership_fee_posted_at')
            ->whereYear('membership_fee_posted_at', $year)
            ->sum('membership_fee_amount');

        $membershipFeeCount = (int) MembershipApplication::whereNotNull('membership_fee_posted_at')
            ->where('membership_fee_amount', '>', 0)
            ->count();

        $membershipFeeCountThisYear = (int) MembershipApplication::whereNotNull('membership_fee_posted_at')
            ->where('membership_fee_amount', '>', 0)
            ->whereYear('membership_fee_posted_at', $year)
            ->count();

        // ── Annual subscription fees ──────────────────────────────────────────
        $subscriptionFeeAllTime = (float) MemberSubscriptionFee::sum('amount');
        $subscriptionFeeThisYear = (float) MemberSubscriptionFee::where('year', $year)->sum('amount');
        $subscriptionFeeCount = (int) MemberSubscriptionFee::count();
        $subscriptionFeeCountThisYear = (int) MemberSubscriptionFee::where('year', $year)->count();

        $lateFeeRecentContributions = Contribution::query()
            ->with('member.user')
            ->whereNotNull('late_fee_amount')
            ->where('late_fee_amount', '>', 0)
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(fn(Contribution $row): array => [
                'member' => $row->member?->user?->name ?? __('Member # :id', ['id' => $row->member_id]),
                'amount' => (float) $row->late_fee_amount,
                'date' => optional($row->paid_at)?->format('Y-m-d'),
                'source' => __('Contribution'),
            ])
            ->all();

        $lateFeeRecentRepayments = LoanInstallment::query()
            ->with('loan.member.user')
            ->whereNotNull('late_fee_amount')
            ->where('late_fee_amount', '>', 0)
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(fn(LoanInstallment $row): array => [
                'member' => $row->loan?->member?->user?->name ?? __('Member'),
                'amount' => (float) $row->late_fee_amount,
                'date' => optional($row->paid_at)?->format('Y-m-d'),
                'source' => __('Repayment'),
            ])
            ->all();

        $membershipFeeRecent = MembershipApplication::query()
            ->with('user')
            ->whereNotNull('membership_fee_posted_at')
            ->where('membership_fee_amount', '>', 0)
            ->latest('membership_fee_posted_at')
            ->limit(10)
            ->get()
            ->map(fn(MembershipApplication $row): array => [
                'member' => $row->user?->name ?? __('Applicant # :id', ['id' => $row->id]),
                'amount' => (float) $row->membership_fee_amount,
                'date' => optional($row->membership_fee_posted_at)?->format('Y-m-d'),
                'source' => __('Application'),
            ])
            ->all();

        $subscriptionFeeRecent = MemberSubscriptionFee::query()
            ->with('member.user')
            ->latest('paid_at')
            ->limit(10)
            ->get()
            ->map(fn(MemberSubscriptionFee $row): array => [
                'member' => $row->member?->user?->name ?? __('Member # :id', ['id' => $row->member_id]),
                'amount' => (float) $row->amount,
                'date' => optional($row->paid_at)?->format('Y-m-d'),
                'source' => (string) $row->year,
            ])
            ->all();

        return [
            'year' => $year,
            'late_fee_all_time' => $lateFeeAllTime,
            'late_fee_this_year' => $lateFeeThisYear,
            'late_fee_count' => $lateFeeCount,
            'late_fee_count_this_year' => $lateFeeCountThisYear,
            'membership_fee_all_time' => $membershipFeeAllTime,
            'membership_fee_this_year' => $membershipFeeThisYear,
            'membership_fee_count' => $membershipFeeCount,
            'membership_fee_count_this_year' => $membershipFeeCountThisYear,
            'subscription_fee_all_time' => $subscriptionFeeAllTime,
            'subscription_fee_this_year' => $subscriptionFeeThisYear,
            'subscription_fee_count' => $subscriptionFeeCount,
            'subscription_fee_count_this_year' => $subscriptionFeeCountThisYear,
            'late_fee_recent' => array_values(array_slice(array_merge($lateFeeRecentContributions, $lateFeeRecentRepayments), 0, 10)),
            'membership_fee_recent' => $membershipFeeRecent,
            'subscription_fee_recent' => $subscriptionFeeRecent,
        ];
    }
}
