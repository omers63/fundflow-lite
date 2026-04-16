<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Notifications\MemberDelinquencyRestoredNotification;
use App\Notifications\MemberDelinquencySuspensionNotification;
use Illuminate\Support\Facades\DB;

class DelinquencyService
{
    public function __construct(
        protected MemberDelinquencyEvaluator $evaluator,
    ) {}

    /**
     * Mark installments whose due date has passed as overdue (pending → overdue).
     */
    public function markOverdueInstallments(): int
    {
        return LoanInstallment::where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    /**
     * Apply configurable delinquency rules: suspend members who breach thresholds,
     * transfer repayment liability to guarantors, and restore members who recover.
     *
     * @return array{suspended: int, restored: int}
     */
    public function flagDelinquentMembers(): array
    {
        $suspended = 0;
        $restored = 0;

        Member::query()
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'suspended')->whereNotNull('delinquency_suspended_at');
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($members) use (&$suspended, &$restored) {
                foreach ($members as $member) {
                    if ($member->trashed()) {
                        continue;
                    }

                    $stats = $this->evaluator->evaluate($member);
                    $breach = $this->evaluator->shouldSuspend(
                        $stats['trailing_consecutive'],
                        $stats['rolling_total'],
                    );

                    if ($breach) {
                        if (!in_array($member->status, ['suspended', 'terminated'], true)) {
                            DB::transaction(function () use ($member, &$suspended) {
                                $member->update([
                                    'status' => 'suspended',
                                    'delinquency_suspended_at' => $member->delinquency_suspended_at ?? now(),
                                ]);

                                $this->transferGuarantorLiabilityForMemberLoans($member);

                                $suspended++;
                            });

                            try {
                                $member->user?->notify(new MemberDelinquencySuspensionNotification(
                                    trailingConsecutive: $stats['trailing_consecutive'],
                                    rollingTotal: $stats['rolling_total'],
                                ));
                            } catch (\Throwable $e) {
                                logger()->error('DelinquencyService: suspension notification failed', [
                                    'member_id' => $member->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        } elseif ($member->status === 'suspended' && $member->delinquency_suspended_at !== null) {
                            $this->transferGuarantorLiabilityForMemberLoans($member);
                        }
                    } elseif ($member->status === 'suspended' && $member->delinquency_suspended_at !== null) {
                        DB::transaction(function () use ($member, &$restored) {
                            $member->update([
                                'status' => 'active',
                                'delinquency_suspended_at' => null,
                            ]);

                            Loan::query()
                                ->where('member_id', $member->id)
                                ->whereNotNull('guarantor_liability_transferred_at')
                                ->update(['guarantor_liability_transferred_at' => null]);

                            $restored++;
                        });

                        try {
                            $member->user?->notify(new MemberDelinquencyRestoredNotification());
                        } catch (\Throwable $e) {
                            logger()->error('DelinquencyService: restore notification failed', [
                                'member_id' => $member->id,
                            ]);
                        }
                    }
                }
            });

        return [
            'suspended' => $suspended,
            'restored' => $restored,
        ];
    }

    protected function transferGuarantorLiabilityForMemberLoans(Member $member): void
    {
        Loan::query()
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->whereNotNull('guarantor_member_id')
            ->whereNull('guarantor_released_at')
            ->whereNull('guarantor_liability_transferred_at')
            ->update(['guarantor_liability_transferred_at' => now()]);
    }
}
