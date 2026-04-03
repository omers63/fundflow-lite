<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberAccountStatsWidget extends BaseWidget
{
    public ?Member $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $member = $this->record->load(['accounts', 'loans']);

        // ── Account balances ─────────────────────────────────────────────────
        $cashBalance = (float) ($member->cashAccount()?->balance ?? 0);
        $fundBalance = (float) ($member->fundAccount()?->balance ?? 0);

        // ── Late contributions ────────────────────────────────────────────────
        $lateCount = (int) ($member->late_contributions_count ?? 0);
        $lateAmount = (float) ($member->late_contributions_amount ?? 0);

        // ── Active / disbursed loans ──────────────────────────────────────────
        $activeLoans = $member->loans()
            ->whereIn('status', ['active', 'approved', 'disbursed'])
            ->get();

        $activeCount = $activeLoans->count();
        $outstandingAmount = 0.0;

        foreach ($activeLoans as $loan) {
            // Sum unpaid installments for each active loan
            $outstandingAmount += (float) LoanInstallment::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('amount');
        }

        // ── Late loan repayments ──────────────────────────────────────────────
        $lateRepayCount = (int) ($member->late_repayment_count ?? 0);
        $lateRepayAmount = (float) ($member->late_repayment_amount ?? 0);

        // ── Colour helpers ────────────────────────────────────────────────────
        $cashColor = match (true) {
            $cashBalance >= 1000 => 'success',
            $cashBalance > 0 => 'warning',
            default => 'danger',
        };
        $fundColor = match (true) {
            $fundBalance >= 6000 => 'success',
            $fundBalance > 0 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Cash Balance', 'SAR '.number_format($cashBalance, 2))
                ->description('Member cash account')
                ->descriptionIcon('heroicon-o-banknotes')
                ->icon('heroicon-o-banknotes')
                ->color($cashColor),

            Stat::make('Fund Balance', 'SAR '.number_format($fundBalance, 2))
                ->description($fundBalance >= 6000 ? 'Loan-eligible balance' : 'Below SAR 6,000 loan threshold')
                ->descriptionIcon($fundBalance >= 6000 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle')
                ->icon('heroicon-o-building-library')
                ->color($fundColor),

            Stat::make('Late Contributions', $lateCount.' occurrence'.($lateCount !== 1 ? 's' : ''))
                ->description('SAR '.number_format($lateAmount, 2).' total late amount')
                ->descriptionIcon($lateCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->icon('heroicon-o-clock')
                ->color($lateCount > 0 ? 'warning' : 'success'),

            Stat::make('Active Loans', $activeCount.' loan'.($activeCount !== 1 ? 's' : ''))
                ->description('SAR '.number_format($outstandingAmount, 2).' outstanding installments')
                ->descriptionIcon($activeCount > 0 ? 'heroicon-o-credit-card' : 'heroicon-o-check-circle')
                ->icon('heroicon-o-credit-card')
                ->color($activeCount > 0 ? 'info' : 'success'),

            Stat::make('Late Loan Repayments', $lateRepayCount.' occurrence'.($lateRepayCount !== 1 ? 's' : ''))
                ->description('SAR '.number_format($lateRepayAmount, 2).' total late repayments')
                ->descriptionIcon($lateRepayCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->icon('heroicon-o-arrow-path')
                ->color($lateRepayCount > 0 ? 'danger' : 'success'),
        ];
    }
}
