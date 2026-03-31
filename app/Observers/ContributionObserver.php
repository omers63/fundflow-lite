<?php

namespace App\Observers;

use App\Models\Contribution;
use App\Services\AccountingService;
use Throwable;

class ContributionObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function created(Contribution $contribution): void
    {
        try {
            $this->accounting->postContribution($contribution);
        } catch (Throwable $e) {
            // Best-effort: log but do not block contribution creation
            logger()->error('ContributionObserver: failed to post contribution', [
                'contribution_id' => $contribution->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
