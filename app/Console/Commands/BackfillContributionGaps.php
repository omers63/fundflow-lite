<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\Member;
use App\Services\ContributionCycleService;
use App\Services\MemberDelinquencyEvaluator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Inserts missing contribution rows for months where the member was expected to contribute
 * (same rules as delinquency: not loan-exempt, positive monthly amount, after join).
 *
 * Default mode is **records only** (no ContributionObserver / no ledger postings). Use
 * --with-ledger only if fund balances do not already include these amounts.
 */
class BackfillContributionGaps extends Command
{
    protected $signature = 'fund:backfill-contribution-gaps
                            {--execute : Create rows (otherwise dry-run)}
                            {--member= : Only this member id}
                            {--with-ledger : Run observer and post to ledger (default: off)}
                            {--from= : Earliest month to consider (Y-m, e.g. 2024-01)}';

    protected $description = 'Backfill missing contribution rows for delinquency alignment (default: no ledger).';

    public function handle(
        MemberDelinquencyEvaluator $evaluator,
        ContributionCycleService $cycles,
    ): int {
        $execute = (bool) $this->option('execute');
        if (!$execute) {
            $this->warn('Dry run only. Re-run with --execute to insert rows.');
        }

        $memberId = $this->option('member');
        $withLedger = (bool) $this->option('with-ledger');
        $fromOpt = $this->option('from');

        $fromKey = null;
        if (filled($fromOpt)) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $fromOpt, $m)) {
                $this->error('Invalid --from= use Y-m (e.g. 2024-01)');

                return self::FAILURE;
            }
            $fromKey = (int) $m[1] * 12 + (int) $m[2];
        }

        $query = Member::query()
            ->where('status', '!=', 'terminated')
            ->orderBy('id');

        if ($memberId !== null && $memberId !== '') {
            $query->whereKey((int) $memberId);
        }

        $now = now();
        [$lastM, $lastY] = $evaluator->lastClosedPeriodMonthYear($now);

        $wouldCreate = 0;
        $created = 0;
        $skippedMember = 0;

        $query->each(function (Member $member) use (
            $evaluator,
            $cycles,
            $withLedger,
            $execute,
            $fromKey,
            $lastM,
            $lastY,
            &$wouldCreate,
            &$created,
            &$skippedMember,
        ) {
            if ($member->trashed()) {
                $skippedMember++;
                return;
            }

            $evaluator->clearCaches();

            $joined = $member->joined_at instanceof Carbon
                ? $member->joined_at->copy()->startOfMonth()
                : Carbon::parse($member->joined_at)->startOfMonth();

            $cursor = $joined->copy();
            $end = Carbon::create($lastY, $lastM, 1)->startOfMonth();
            $createdThisMember = 0;

            while ($cursor->lte($end)) {
                $m = (int) $cursor->month;
                $y = (int) $cursor->year;

                if ($fromKey !== null) {
                    $k = $y * 12 + $m;
                    if ($k < $fromKey) {
                        $cursor->addMonthNoOverflow();
                        continue;
                    }
                }

                if (!$evaluator->hasContributionGap($member, $m, $y)) {
                    $cursor->addMonthNoOverflow();
                    continue;
                }

                $amount = (float) $member->monthly_contribution_amount;
                if ($amount <= 0) {
                    $cursor->addMonthNoOverflow();
                    continue;
                }

                $paidAt = $cycles->cycleDueEndAt($m, $y);

                $wouldCreate++;
                if ($execute) {
                    $payload = [
                        'member_id' => $member->id,
                        'month' => $m,
                        'year' => $y,
                        'amount' => $amount,
                        'paid_at' => $paidAt,
                        'payment_method' => Contribution::PAYMENT_METHOD_ADMIN,
                        'reference_number' => 'backfill-gap',
                        'notes' => 'Auto backfill (fund:backfill-contribution-gaps)',
                        'is_late' => false,
                    ];

                    try {
                        if ($withLedger) {
                            Contribution::create($payload);
                        } else {
                            Contribution::withoutEvents(function () use ($payload) {
                                Contribution::create($payload);
                            });
                        }
                        $createdThisMember++;
                        $created++;
                    } catch (\Throwable $e) {
                        $this->error("Member {$member->id} {$y}-{$m}: {$e->getMessage()}");
                    }
                }

                $cursor->addMonthNoOverflow();
            }

            if ($execute && $createdThisMember > 0) {
                $member->refreshLateContributionStats();
            }
        });

        $this->info($execute
            ? "Created {$created} contribution row(s)."
            : "Would create {$wouldCreate} contribution row(s) (dry run).");
        if ($skippedMember > 0) {
            $this->line("Skipped trashed members: {$skippedMember}");
        }

        if (!$withLedger && $execute && $created > 0) {
            $this->warn('Records were inserted without ledger postings. If fund balances already included these amounts, that is correct. Otherwise use --with-ledger (or post adjustments separately).');
        }

        return self::SUCCESS;
    }
}
