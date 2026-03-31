<?php

namespace App\Services;

use App\Models\LoanInstallment;
use App\Models\Loan;
use App\Models\Member;

class LoanEligibilityService
{
    public function isEligible(Member $member): bool
    {
        if (! $member->isActive()) {
            return false;
        }

        $hasOverdue = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->exists();

        if ($hasOverdue) {
            return false;
        }

        $hasActiveLoan = Loan::where('member_id', $member->id)
            ->whereIn('status', ['pending', 'approved', 'active'])
            ->exists();

        if ($hasActiveLoan) {
            return false;
        }

        return true;
    }

    public function getIneligibilityReason(Member $member): string
    {
        if (! $member->isActive()) {
            return 'Your membership status is not active.';
        }

        $hasOverdue = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->exists();

        if ($hasOverdue) {
            return 'You have overdue installments. Please clear all overdue payments before applying for a new loan.';
        }

        $hasActiveLoan = Loan::where('member_id', $member->id)
            ->whereIn('status', ['pending', 'approved', 'active'])
            ->exists();

        if ($hasActiveLoan) {
            return 'You already have an active or pending loan. Please complete the current loan before applying for another.';
        }

        return '';
    }
}
