<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Services\LoanEligibilityService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    public function mount(): void
    {
        parent::mount();

        $memberId = request()->integer('member_id');
        if ($memberId > 0) {
            $this->form->fill(['member_id' => $memberId]);
        }
    }

    /**
     * Final server-side guard: reject if the requested amount exceeds
     * 2× the member's fund account balance before the record is persisted.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $memberId = $data['member_id'] ?? null;
        $amount = (float) ($data['amount_requested'] ?? 0);
        $member = null;

        if ($memberId) {
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
            }
        }

        // Avoid DB default (12): estimate months from requested amount + tier + fund balance.
        if ($member && $amount > 0) {
            $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
            $threshold = Setting::loanSettlementThreshold();
            $loanTier = LoanTier::forAmount($amount);
            $minInstall = (float) ($loanTier?->min_monthly_installment ?? 1000);
            $data['installments_count'] = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);
        } else {
            $data['installments_count'] = 0;
        }

        return $data;
    }
}

