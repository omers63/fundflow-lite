<?php

namespace App\Observers;

use App\Models\Contribution;
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
    }
}
