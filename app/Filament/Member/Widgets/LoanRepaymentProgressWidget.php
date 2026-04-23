<?php

namespace App\Filament\Member\Widgets;

use App\Models\Loan;
use App\Services\LoanEarlySettlementService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class LoanRepaymentProgressWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.member.widgets.loan-repayment-progress';

    public static function canView(): bool
    {
        $member = auth()->user()?->member;
        if (!$member) {
            return false;
        }

        return Loan::where('member_id', $member->id)
            ->where('status', 'active')
            ->exists();
    }

    public function getLoans(): Collection
    {
        $member = auth()->user()?->member;
        if (!$member) {
            return collect();
        }

        $earlySvc = app(LoanEarlySettlementService::class);

        return Loan::where('member_id', $member->id)
            ->where('status', 'active')
            ->with(['loanTier', 'installments'])
            ->get()
            ->map(function (Loan $loan) use ($member, $earlySvc) {
                $totalInstallments = $loan->installments_count;
                $paidInstallments = $loan->installments()->where('status', 'paid')->count();
                $paidPercent = $totalInstallments > 0
                    ? round($paidInstallments / $totalInstallments * 100)
                    : 0;

                $fundBalance = (float) ($member->fundAccount()?->balance ?? 0);
                $settleRequired = (float) $loan->amount_approved * (float) $loan->settlement_threshold;
                $fundPercent = $settleRequired > 0
                    ? min(100, round($fundBalance / $settleRequired * 100))
                    : 100;

                $masterPortion = (float) $loan->master_portion;
                $repaidToMaster = (float) $loan->repaid_to_master;
                $masterPercent = $masterPortion > 0
                    ? min(100, round($repaidToMaster / $masterPortion * 100))
                    : 100;

                $nextInstallment = $loan->installments()
                    ->where('status', 'pending')
                    ->orderBy('due_date')
                    ->first();

                $remainingSettlementCash = $earlySvc->requiredCash($loan);
                $canEarlySettleCash = $earlySvc->hasSufficientCash($loan);
                $cashBalance = (float) ($member->cashAccount()?->balance ?? 0);

                return [
                    'loan' => $loan,
                    'paid_installments' => $paidInstallments,
                    'total_installments' => $totalInstallments,
                    'paid_percent' => $paidPercent,
                    'fund_balance' => $fundBalance,
                    'settle_required' => $settleRequired,
                    'fund_percent' => $fundPercent,
                    'master_portion' => $masterPortion,
                    'repaid_to_master' => $repaidToMaster,
                    'master_percent' => $masterPercent,
                    'guarantor_released' => $loan->isGuarantorReleased(),
                    'next_installment' => $nextInstallment,
                    'remaining_amount' => $loan->remaining_amount,
                    'remaining_settlement_cash' => $remainingSettlementCash,
                    'can_early_settle_cash' => $canEarlySettleCash,
                    'cash_balance' => $cashBalance,
                    'is_ready_to_settle' => $loan->isReadyToSettle(),
                ];
            });
    }

    public function settleEarly(int $loanId): void
    {
        $member = auth()->user()?->member;
        if ($member === null) {
            Notification::make()->title(__('Member record not found'))->danger()->send();

            return;
        }

        $loan = Loan::query()
            ->where('member_id', $member->id)
            ->whereKey($loanId)
            ->where('status', 'active')
            ->first();

        if ($loan === null) {
            Notification::make()->title(__('Active loan not found'))->danger()->send();

            return;
        }

        try {
            app(LoanEarlySettlementService::class)->earlySettle($loan);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Notification::make()
                ->title(__('Could not pay off early'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Loan paid off'))
            ->body(__('Your loan is closed. You may apply for a new loan when you meet eligibility rules.'))
            ->success()
            ->send();
    }
}
