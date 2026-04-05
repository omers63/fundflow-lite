<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SmsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberDeletionService
{
    public function __construct(
        private readonly AccountingService $accounting,
    ) {
    }

    /**
     * @return array<int, string> Human-readable reasons; empty = deletable
     */
    public function deletionBlockers(Member $member): array
    {
        $reasons = [];

        if ($member->contributions()->exists()) {
            $reasons[] = 'This member has contribution records. Delete or adjust those first.';
        }

        return $reasons;
    }

    /**
     * @throws \RuntimeException When deletion would leave inconsistent business data
     */
    public function delete(Member $member): void
    {
        $blockers = $this->deletionBlockers($member);
        if ($blockers !== []) {
            throw new \RuntimeException(implode(' ', $blockers));
        }

        DB::transaction(function () use ($member) {
            $member->loadMissing('user');
            $user = $member->user;
            if (!$user instanceof User) {
                throw new \RuntimeException('Member has no linked user account.');
            }

            Member::query()->where('parent_id', $member->id)->update(['parent_id' => null]);

            foreach (Loan::query()->where('member_id', $member->id)->orderBy('id')->cursor() as $loan) {
                $this->accounting->safeDeleteLoan($loan);
            }

            foreach (BankTransaction::query()->where('member_id', $member->id)->orderBy('id')->cursor() as $tx) {
                $this->accounting->safeDeleteBankTransaction($tx);
            }

            foreach (SmsTransaction::query()->where('member_id', $member->id)->orderBy('id')->cursor() as $tx) {
                $this->accounting->safeDeleteSmsTransaction($tx);
            }

            foreach (Account::query()->where('member_id', $member->id)->orderBy('id')->cursor() as $account) {
                $account->delete();
            }

            $userId = $user->id;
            $member->delete();
            User::query()->whereKey($userId)->delete();
        });
    }
}
