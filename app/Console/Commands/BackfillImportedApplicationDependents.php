<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\MembershipApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillImportedApplicationDependents extends Command
{
    protected $signature = 'fundflow:backfill-imported-application-dependents
        {--dry-run : Show what would be changed without writing}';

    protected $description = 'Relink approved imported duplicate-email applications into parent/dependent members.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = MembershipApplication::query()
            ->with(['user.member', 'submittedBy.member.user'])
            ->where('status', 'approved')
            ->whereNull('parent_member_id')
            ->whereNotNull('submitted_by_user_id')
            ->whereColumn('submitted_by_user_id', '!=', 'user_id')
            ->orderBy('id');

        $scanned = (clone $query)->count();
        $updated = 0;
        $skipped = 0;

        $query->chunkById(200, function ($applications) use (&$updated, &$skipped, $dryRun): void {
            foreach ($applications as $application) {
                /** @var MembershipApplication $application */
                $childMember = $application->user?->member;
                $parentMember = $application->submittedBy?->member;

                if (!$childMember instanceof Member || !$parentMember instanceof Member) {
                    $skipped++;

                    continue;
                }

                if ($childMember->id === $parentMember->id) {
                    $skipped++;

                    continue;
                }

                if ($childMember->parent_id !== null || $parentMember->parent_id !== null) {
                    $skipped++;

                    continue;
                }

                if ($childMember->dependents()->exists()) {
                    $skipped++;

                    continue;
                }

                $householdEmail = $parentMember->household_email ?: $parentMember->user?->email ?: $childMember->user?->email;

                if (!$dryRun) {
                    DB::transaction(function () use ($childMember, $parentMember, $householdEmail, $application): void {
                        $childMember->update([
                            'parent_id' => $parentMember->id,
                            'household_email' => $householdEmail,
                        ]);

                        $application->update([
                            'parent_member_id' => $parentMember->id,
                        ]);
                    });
                }

                $updated++;
            }
        });

        $this->info(($dryRun ? 'Dry run: ' : '') . "updated={$updated}, skipped={$skipped}, scanned={$scanned}");

        return self::SUCCESS;
    }
}
