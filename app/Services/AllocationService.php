<?php

namespace App\Services;

use App\Models\DependentAllocationChange;
use App\Models\Member;
use App\Models\User;
use App\Notifications\DependentAllocationChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocationService
{
    /**
     * Update a single dependent's monthly contribution amount.
     *
     * - Writes an audit row to `dependent_allocation_changes`.
     * - Notifies the dependent via their channel preferences.
     * - Notifies all admin users (in-app only).
     *
     * Returns the change record (or null if the amount was unchanged).
     */
    public function changeAllocation(
        Member  $parent,
        Member  $dependent,
        int     $newAmount,
        ?string $note      = null,
        ?User   $changedBy = null,
    ): ?DependentAllocationChange {
        $oldAmount = (int) $dependent->monthly_contribution_amount;

        if ($oldAmount === $newAmount) {
            return null;
        }

        $change = null;

        DB::transaction(function () use ($parent, $dependent, $oldAmount, $newAmount, $note, $changedBy, &$change): void {
            $dependent->update(['monthly_contribution_amount' => $newAmount]);

            $change = DependentAllocationChange::create([
                'parent_member_id'   => $parent->id,
                'dependent_member_id'=> $dependent->id,
                'old_amount'         => $oldAmount,
                'new_amount'         => $newAmount,
                'changed_by_user_id' => $changedBy?->id ?? auth()->id(),
                'note'               => $note,
            ]);
        });

        if ($change === null) {
            return null;
        }

        $this->dispatchNotifications($change);

        return $change;
    }

    /**
     * Bulk-update allocations for multiple dependents in one call.
     *
     * $updates is an array of [ dependent_id => new_amount ] pairs.
     *
     * Returns an array of { dependent, change|null, error|null } rows for UI feedback.
     */
    public function changeMultiple(
        Member  $parent,
        array   $updates,
        ?string $note      = null,
        ?User   $changedBy = null,
    ): array {
        $results = [];

        foreach ($updates as $dependentId => $newAmount) {
            $dependent = $parent->dependents()
                ->where('id', (int) $dependentId)
                ->first();

            if (!$dependent) {
                continue;
            }

            try {
                $change = $this->changeAllocation($parent, $dependent, (int) $newAmount, $note, $changedBy);
                $results[] = [
                    'dependent' => $dependent,
                    'change'    => $change,
                    'error'     => null,
                ];
            } catch (\Throwable $e) {
                Log::error("AllocationService: failed for dependent #{$dependent->id}: " . $e->getMessage());
                $results[] = [
                    'dependent' => $dependent,
                    'change'    => null,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Build a human-readable summary string from changeMultiple() results.
     */
    public function buildSummary(array $results): string
    {
        $changed = collect($results)->filter(fn($r) => $r['change'] !== null);
        $errors  = collect($results)->filter(fn($r) => $r['error'] !== null);
        $same    = collect($results)->filter(fn($r) => $r['change'] === null && $r['error'] === null);

        $parts = [];

        if ($changed->count()) {
            $names = $changed->map(fn($r) => $r['dependent']->user->name)->join(', ');
            $parts[] = "Updated {$changed->count()} dependent(s): {$names}.";
        }

        if ($same->count()) {
            $parts[] = "{$same->count()} unchanged (same amount).";
        }

        if ($errors->count()) {
            $parts[] = "{$errors->count()} failed: " . $errors->map(fn($r) => $r['error'])->join('; ');
        }

        return implode(' ', $parts) ?: 'No changes made.';
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function dispatchNotifications(DependentAllocationChange $change): void
    {
        $change->load(['dependent.user', 'parent.user', 'changedBy']);

        // ── Notify dependent ──────────────────────────────────────────────────
        $dependentUser = $change->dependent?->user;
        if ($dependentUser) {
            try {
                $dependentUser->notify(new DependentAllocationChangedNotification($change, 'dependent'));
            } catch (\Throwable $e) {
                Log::error("AllocationService: dependent notification failed: " . $e->getMessage());
            }
        }

        // ── Notify parent member (confirmation) ───────────────────────────────
        $parentUser = $change->parent?->user;
        if ($parentUser) {
            try {
                $parentUser->notify(new DependentAllocationChangedNotification($change, 'parent'));
            } catch (\Throwable $e) {
                Log::error("AllocationService: parent notification failed: " . $e->getMessage());
            }
        }

        // ── Notify all admin users (in-app only) ─────────────────────────────
        User::where('role', 'admin')->each(function (User $admin) use ($change): void {
            try {
                $admin->notify(new DependentAllocationChangedNotification($change, 'admin'));
            } catch (\Throwable $e) {
                Log::error("AllocationService: admin notification failed for user#{$admin->id}: " . $e->getMessage());
            }
        });
    }
}
