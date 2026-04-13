<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Services\LoanEligibilityService;
use App\Services\LoanQueueOrderingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LoanResource::approveLoanAction(),
            LoanResource::rejectLoanAction(),
        ];
    }

    /**
     * Same ceiling as create: requested amount must not exceed 2× member fund balance.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $memberId = $data['member_id'] ?? null;
        $amount = (float) ($data['amount_requested'] ?? 0);

        if ($memberId && $amount > 0) {
            $member = Member::with('accounts')->find($memberId);
            if ($member) {
                $max = app(LoanEligibilityService::class)->maxLoanAmount($member);
                if ($amount > $max) {
                    $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                    Notification::make()
                        ->title('Amount Exceeds Maximum')
                        ->body(
                            'Requested SAR ' . number_format($amount)
                            . ' exceeds the maximum of SAR ' . number_format($max)
                            . ' (2× fund balance of SAR ' . number_format($fundBal) . ').'
                        )
                        ->danger()
                        ->send();

                    $this->halt();
                }

                if ($this->getRecord()->status === 'pending') {
                    $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                    $threshold = Setting::loanSettlementThreshold();
                    $loanTier = LoanTier::forAmount($amount);
                    $minInstall = (float) ($loanTier?->min_monthly_installment ?? 1000);
                    $data['installments_count'] = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record->fund_tier_id !== null && in_array($record->status, ['approved', 'active'], true)) {
            LoanQueueOrderingService::resequenceFundTier((int) $record->fund_tier_id);
        }
    }
}
