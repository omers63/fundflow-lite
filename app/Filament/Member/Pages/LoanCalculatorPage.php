<?php

namespace App\Filament\Member\Pages;

use App\Models\Loan;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use Filament\Pages\Page;

class LoanCalculatorPage extends Page
{
    protected string $view = 'filament.member.pages.loan-calculator';

    protected static ?string $navigationLabel = 'Loan Calculator';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.loans');
    }

    // =========================================================================
    // Livewire reactive properties
    // =========================================================================

    public float $loanAmount = 0;
    public int $tierIndex = 0;

    // =========================================================================
    // Computed output (updated reactively)
    // =========================================================================

    /** @return array<int, array{tier: LoanTier, min_installment: float, installments: int, member_portion: float, master_portion: float, total_repay: float}> */
    public function getCalculationsProperty(): array
    {
        if ($this->loanAmount <= 0) {
            return [];
        }

        $member = $this->currentMember();
        $fundBalance = $member ? (float) $member->fund_balance : 0.0;
        $settlementPct = Setting::loanSettlementThreshold();

        $results = [];
        foreach (LoanTier::where('is_active', true)->orderBy('tier_number')->get() as $tier) {
            if ($this->loanAmount < (float) $tier->min_amount || $this->loanAmount > (float) $tier->max_amount) {
                continue;
            }

            $memberPortion = min(max(0.0, $fundBalance), $this->loanAmount);
            $masterPortion = $this->loanAmount - $memberPortion;
            $minInstallment = (float) $tier->min_monthly_installment;
            $installments = Loan::computeInstallmentsCount(
                $this->loanAmount,
                $fundBalance,
                $minInstallment,
                $settlementPct,
            );
            $settlementAmt = $this->loanAmount * $settlementPct;
            $totalToRepay = $masterPortion + $settlementAmt;

            $results[] = [
                'tier'           => $tier,
                'min_installment' => $minInstallment,
                'installments'   => $installments,
                'member_portion' => $memberPortion,
                'master_portion' => $masterPortion,
                'settlement_amt' => $settlementAmt,
                'total_repay'    => $totalToRepay,
            ];
        }

        return $results;
    }

    public function getActiveTiersProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return LoanTier::where('is_active', true)->orderBy('min_amount')->get();
    }

    public function getSettlementPctProperty(): float
    {
        return Setting::loanSettlementThreshold();
    }

    public function getMemberFundBalanceProperty(): float
    {
        $member = $this->currentMember();
        return $member ? (float) $member->fund_balance : 0.0;
    }

    protected function currentMember(): ?Member
    {
        return Member::where('user_id', auth()->id())
            ->withSum(['accounts as fund_balance' => fn($q) => $q->where('type', \App\Models\Account::TYPE_MEMBER_FUND)], 'balance')
            ->first();
    }

    public function getTitle(): string
    {
        return 'Loan Calculator';
    }
}
