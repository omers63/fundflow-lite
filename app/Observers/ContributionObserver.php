<?php

namespace App\Observers;

use App\Models\Contribution;
use App\Models\Member;
use App\Services\AccountingService;
use Throwable;

class ContributionObserver
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function deleting(Contribution $contribution): void
    {
        try {
            $this->accounting->reverseContributionPosting($contribution);
        } catch (Throwable $e) {
            logger()->error('ContributionObserver: failed to reverse contribution ledger', [
                'contribution_id' => $contribution->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleted(Contribution $contribution): void
    {
        $this->refreshMemberLateStatsForContribution($contribution);
    }

    public function restored(Contribution $contribution): void
    {
        try {
            $this->accounting->postContribution($contribution);
        } catch (Throwable $e) {
            logger()->error('ContributionObserver: failed to re-post contribution after restore', [
                'contribution_id' => $contribution->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->refreshMemberLateStatsForContribution($contribution);
    }

    public function forceDeleted(Contribution $contribution): void
    {
        $this->refreshMemberLateStatsForContribution($contribution);
    }

    public function created(Contribution $contribution): void
    {
        try {
            $this->accounting->postContribution($contribution);
        } catch (Throwable $e) {
            // Best-effort: log but do not block contribution creation
            logger()->error('ContributionObserver: failed to post contribution', [
                'contribution_id' => $contribution->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->refreshMemberLateStatsForContribution($contribution);
    }

    public function updated(Contribution $contribution): void
    {
        if ($contribution->wasChanged('member_id')) {
            $originalMemberId = $contribution->getOriginal('member_id');
            if (filled($originalMemberId)) {
                $this->refreshMemberLateStatsForMemberId((int) $originalMemberId);
            }
        }

        $this->refreshMemberLateStatsForContribution($contribution);
    }

    protected function refreshMemberLateStatsForContribution(Contribution $contribution): void
    {
        $this->refreshMemberLateStatsForMemberId((int) $contribution->member_id);
    }

    protected function refreshMemberLateStatsForMemberId(int $memberId): void
    {
        if ($memberId <= 0) {
            return;
        }

        Member::query()->whereKey($memberId)->first()?->refreshLateContributionStats();
    }
}
