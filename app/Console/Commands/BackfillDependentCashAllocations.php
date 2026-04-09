<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\DependentCashAllocation;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Rebuilds dependent_cash_allocations from historical member-cash credit lines whose
 * description matches parent→dependent transfers with note "Allocation — {month year}".
 */
class BackfillDependentCashAllocations extends Command
{
    protected $signature = 'fundflow:backfill-dependent-cash-allocations
                            {--dry-run : List rows that would be created or updated without persisting}
                            {--force : Overwrite amount for cycles that already have a dependent_cash_allocations row}';

    protected $description = 'Backfill dependent_cash_allocations from ledger credits tagged with Allocation — {period}.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Dry run — no database writes.');
        }

        $aggregates = $this->aggregateFromLedger();

        if ($aggregates->isEmpty()) {
            $this->info('No matching allocation credits found in account_transactions.');

            return self::SUCCESS;
        }

        $this->info('Found ' . $aggregates->count() . ' distinct dependent + cycle group(s) from the ledger.');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedNoParent = 0;

        $rows = [];

        foreach ($aggregates as $row) {
            $dependent = Member::query()->find($row['dependent_member_id']);
            if ($dependent === null) {
                $skipped++;
                $this->warn("Skipping missing dependent member_id={$row['dependent_member_id']}.");

                continue;
            }

            $parentId = $dependent->parent_id;
            if ($parentId === null) {
                $skippedNoParent++;
                $this->warn("Skipping dependent {$dependent->member_number} (id {$dependent->id}): no parent_id.");

                continue;
            }

            $existing = DependentCashAllocation::query()
                ->where('dependent_member_id', $dependent->id)
                ->where('allocation_month', $row['allocation_month'])
                ->where('allocation_year', $row['allocation_year'])
                ->first();

            if ($existing !== null && !$force) {
                $skipped++;
                $rows[] = [
                    $dependent->member_number,
                    $row['allocation_year'] . '-' . str_pad((string) $row['allocation_month'], 2, '0', STR_PAD_LEFT),
                    number_format((float) $row['amount'], 2),
                    'skip (exists)',
                ];

                continue;
            }

            $payload = [
                'parent_member_id' => $parentId,
                'dependent_member_id' => $dependent->id,
                'allocation_month' => $row['allocation_month'],
                'allocation_year' => $row['allocation_year'],
                'amount' => $row['amount'],
            ];

            if ($dryRun) {
                $action = $existing !== null ? 'would update' : 'would create';
                $rows[] = [
                    $dependent->member_number,
                    $row['allocation_year'] . '-' . str_pad((string) $row['allocation_month'], 2, '0', STR_PAD_LEFT),
                    number_format((float) $row['amount'], 2),
                    $action,
                ];
                if ($existing !== null) {
                    $updated++;
                } else {
                    $created++;
                }

                continue;
            }

            if ($existing !== null) {
                $existing->update([
                    'parent_member_id' => $parentId,
                    'amount' => $row['amount'],
                ]);
                $updated++;
                $rows[] = [
                    $dependent->member_number,
                    $row['allocation_year'] . '-' . str_pad((string) $row['allocation_month'], 2, '0', STR_PAD_LEFT),
                    number_format((float) $row['amount'], 2),
                    'updated',
                ];
            } else {
                DependentCashAllocation::query()->create($payload);
                $created++;
                $rows[] = [
                    $dependent->member_number,
                    $row['allocation_year'] . '-' . str_pad((string) $row['allocation_month'], 2, '0', STR_PAD_LEFT),
                    number_format((float) $row['amount'], 2),
                    'created',
                ];
            }
        }

        if ($rows !== []) {
            $this->newLine();
            $this->table(
                ['Dependent #', 'Cycle', 'Amount (SAR)', 'Action'],
                $rows
            );
        }

        $this->newLine();
        if (!$dryRun) {
            $this->info("Created: {$created}  Updated: {$updated}  Skipped (unchanged): {$skipped}  Skipped (no parent): {$skippedNoParent}");
        } else {
            $this->info("Would create: {$created}  Would update: {$updated}  Skipped (unchanged): {$skipped}  Skipped (no parent): {$skippedNoParent}");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{dependent_member_id: int, allocation_month: int, allocation_year: int, amount: float}>
     */
    protected function aggregateFromLedger(): Collection
    {
        /** @var array<string, array{dependent_member_id: int, allocation_month: int, allocation_year: int, amount: float}> $map */
        $map = [];

        $memberCashAccountIds = Account::query()
            ->where('type', Account::TYPE_MEMBER_CASH)
            ->whereNotNull('member_id')
            ->pluck('id');

        if ($memberCashAccountIds->isEmpty()) {
            return collect();
        }

        $query = AccountTransaction::query()
            ->where('entry_type', 'credit')
            ->whereNotNull('member_id')
            ->whereIn('account_id', $memberCashAccountIds)
            ->where(function ($q): void {
                $q->where('description', 'like', '%Allocation —%')
                    ->orWhere('description', 'like', '%Allocation -%');
            })
            ->select(['id', 'description', 'amount', 'member_id']);

        $query->orderBy('id')->chunkById(500, function ($chunk) use (&$map): void {
            foreach ($chunk as $tx) {
                $parsed = $this->parseAllocationPeriod((string) $tx->description);
                if ($parsed === null) {
                    continue;
                }

                [$month, $year] = $parsed;
                $dependentId = (int) $tx->member_id;
                $key = $dependentId . '-' . $year . '-' . $month;

                if (!isset($map[$key])) {
                    $map[$key] = [
                        'dependent_member_id' => $dependentId,
                        'allocation_month' => $month,
                        'allocation_year' => $year,
                        'amount' => 0.0,
                    ];
                }

                $map[$key]['amount'] += (float) $tx->amount;
            }
        });

        return collect($map)->values();
    }

    /**
     * @return array{0: int, 1: int}|null month, year
     */
    protected function parseAllocationPeriod(string $description): ?array
    {
        if (!preg_match('/Allocation\s*[—\-]\s*(.+)$/u', $description, $m)) {
            return null;
        }

        try {
            $period = Carbon::parse(trim($m[1]));

            return [(int) $period->month, (int) $period->year];
        } catch (\Throwable) {
            return null;
        }
    }
}
