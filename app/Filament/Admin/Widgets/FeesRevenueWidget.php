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
        ];
    }
}
