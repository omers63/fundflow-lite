<?php

namespace App\Filament\Member\Widgets;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\Widget;

class LoanRepaymentProgressWidget extends Widget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.member.widgets.loan-repayment-progress';

    public static function canView(): bool
    {
        $member = auth()->user()?->member;
        if (! $member) return false;
        return Loan::where('member_id', $member->id)
            ->where('status', 'active')
            ->exists();
    }

    public function getLoans(): \Illuminate\Support\Collection
    {
        $member = auth()->user()?->member;
        if (! $member) return collect();

        return Loan::where('member_id', $member->id)
            ->where('status', 'active')
            ->with(['loanTier', 'installments'])
            ->get()
            ->map(function (Loan $loan) use ($member) {
                $totalInstallments  = $loan->installments_count;
                $paidInstallments   = $loan->installments()->where('status', 'paid')->count();
                $paidPercent        = $totalInstallments > 0
                    ? round($paidInstallments / $totalInstallments * 100)
                    : 0;

                $fundBalance        = (float) ($member->fundAccount()?->balance ?? 0);
                $settleRequired     = (float) $loan->amount_approved * (float) $loan->settlement_threshold;
                $fundPercent        = $settleRequired > 0
                    ? min(100, round($fundBalance / $settleRequired * 100))
                    : 100;

                $masterPortion      = (float) $loan->master_portion;
                $repaidToMaster     = (float) $loan->repaid_to_master;
                $masterPercent      = $masterPortion > 0
                    ? min(100, round($repaidToMaster / $masterPortion * 100))
                    : 100;

                $nextInstallment = $loan->installments()
                    ->where('status', 'pending')
                    ->orderBy('due_date')
                    ->first();

                return [
                    'loan'              => $loan,
                    'paid_installments' => $paidInstallments,
                    'total_installments'=> $totalInstallments,
                    'paid_percent'      => $paidPercent,
                    'fund_balance'      => $fundBalance,
                    'settle_required'   => $settleRequired,
                    'fund_percent'      => $fundPercent,
                    'master_portion'    => $masterPortion,
                    'repaid_to_master'  => $repaidToMaster,
                    'master_percent'    => $masterPercent,
                    'guarantor_released'=> $loan->isGuarantorReleased(),
                    'next_installment'  => $nextInstallment,
                    'remaining_amount'  => $loan->remaining_amount,
                    'is_ready_to_settle'=> $loan->isReadyToSettle(),
                ];
            });
    }
}
