<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Setting;
use App\Notifications\LoanDefaultGuarantorNotification;
use App\Notifications\LoanDefaultWarningNotification;
use Illuminate\Support\Facades\DB;

class LoanDefaultService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * For each overdue installment across all active loans:
     *  - Count total defaults on the loan.
     *  - 2 defaults (cumulative) → warn borrower.
     *  - 3rd+ default → debit guarantor's fund account + notify guarantor.
     */
    public function processDefaults(): array
    {
        $grace    = Setting::loanDefaultGraceCycles();
        $warned   = 0;
        $debited  = 0;

        Loan::active()
            ->with(['member.user', 'guarantor.user', 'installments'])
            ->each(function (Loan $loan) use ($grace, &$warned, &$debited) {
                $overdueInstallments = $loan->installments()
                    ->where('status', 'overdue')
                    ->where('paid_by_guarantor', false)
                    ->orderBy('due_date')
                    ->get();

                if ($overdueInstallments->isEmpty()) {
                    return;
                }

                $totalDefaults = $loan->late_repayment_count;

                foreach ($overdueInstallments as $installment) {
                    $totalDefaults++;

                    if ($totalDefaults <= $grace) {
                        // Warn borrower
                        try {
                            $loan->member->user->notify(
                                new LoanDefaultWarningNotification($loan, $installment, $totalDefaults, $grace)
                            );
                            $warned++;
                        } catch (\Throwable $e) {
                            logger()->error('LoanDefaultService: warning notification failed', ['loan_id' => $loan->id]);
                        }
                    } else {
                        // Debit guarantor's fund + notify guarantor
                        if ($loan->guarantor_member_id && ! $loan->isGuarantorReleased()) {
                            try {
                                DB::transaction(function () use ($loan, $installment) {
                                    $this->accounting->debitGuarantorFundForDefault(
                                        $loan->guarantor,
                                        $installment
                                    );

                                    // Also post the repayment credit to fund accounts for the loan
                                    $installment->update([
                                        'status'             => 'paid',
                                        'paid_at'            => now(),
                                        'paid_by_guarantor'  => true,
                                    ]);

                                    $loan->releaseGuarantorIfDue();
                                });

                                $loan->guarantor->user->notify(
                                    new LoanDefaultGuarantorNotification($loan, $installment)
                                );
                                $debited++;
                            } catch (\Throwable $e) {
                                logger()->error('LoanDefaultService: guarantor debit failed', [
                                    'loan_id'      => $loan->id,
                                    'installment'  => $installment->id,
                                    'error'        => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            });

        return ['warned' => $warned, 'debited_from_guarantor' => $debited];
    }

    /**
     * Check all active loans and mark them settled if both conditions are met:
     * 1. repaid_to_master >= master_portion
     * 2. member fund account >= settlement_threshold * amount_approved
     *
     * Returns count of loans settled.
     */
    public function checkSettlements(): int
    {
        $settled = 0;

        Loan::active()->with('member.accounts')->each(function (Loan $loan) use (&$settled) {
            if ($loan->isReadyToSettle()) {
                $loan->update([
                    'status'     => 'completed',
                    'settled_at' => now(),
                ]);

                try {
                    $loan->member->user->notify(
                        new \App\Notifications\LoanSettledNotification($loan)
                    );
                } catch (\Throwable $e) {
                    // best-effort
                }

                $settled++;
            }
        });

        return $settled;
    }
}
