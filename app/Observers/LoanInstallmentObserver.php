<?php

namespace App\Observers;

use App\Models\LoanInstallment;
use App\Services\AccountingService;
use Throwable;

class LoanInstallmentObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function updated(LoanInstallment $installment): void
    {
        // Only react when the status changes to 'paid' for the first time
        if (
            $installment->wasChanged('status') &&
            $installment->status === 'paid' &&
            $installment->getOriginal('status') !== 'paid'
        ) {
            try {
                $this->accounting->postLoanRepayment($installment);
            } catch (Throwable $e) {
                logger()->error('LoanInstallmentObserver: failed to post repayment', [
                    'installment_id' => $installment->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
