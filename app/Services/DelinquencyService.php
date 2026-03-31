<?php

namespace App\Services;

use App\Models\LoanInstallment;
use App\Models\Member;
use App\Notifications\DelinquencyAlertNotification;

class DelinquencyService
{
    public function markOverdueInstallments(): int
    {
        $updated = LoanInstallment::where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        return $updated;
    }

    public function flagDelinquentMembers(): int
    {
        $flagged = 0;

        Member::with(['loans.installments'])->each(function (Member $member) use (&$flagged) {
            $overdueCount = LoanInstallment::whereHas(
                'loan',
                fn ($q) => $q->where('member_id', $member->id)
            )
                ->where('status', 'overdue')
                ->count();

            if ($overdueCount >= 3 && $member->status !== 'delinquent') {
                $member->update(['status' => 'delinquent']);

                try {
                    $member->user->notify(new DelinquencyAlertNotification($overdueCount));
                } catch (\Throwable $e) {
                    // best-effort
                }

                $flagged++;
            } elseif ($overdueCount === 0 && $member->status === 'delinquent') {
                $member->update(['status' => 'active']);
            }
        });

        return $flagged;
    }
}
