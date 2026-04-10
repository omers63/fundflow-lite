<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Setting;
use Filament\Widgets\Widget;

class MemberProfileWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.member-profile';

    public ?Member $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        if (!$this->record) {
            return ['hasRecord' => false];
        }

        $member = $this->record;
        $member->unsetRelation('user');
        $member->load(['user', 'parent.user', 'dependents.user']);
        $user = $member->user;
        $app = $member->latestMembershipApplication();

        $monthsActive = $member->joined_at
            ? (int) $member->joined_at->diffInMonths(now()) + 1
            : 0;

        $contribCount = Contribution::where('member_id', $member->id)->count();
        $complianceRate = $monthsActive > 0
            ? min(100, round($contribCount / $monthsActive * 100))
            : 0;

        $eligibilityMonths = Setting::loanEligibilityMonths();
        $loanEligibleDate = $member->loanEligibilityStartDate()?->copy()->addMonths($eligibilityMonths);
        $isLoanEligibleAge = $loanEligibleDate?->isPast() ?? false;

        $targetPage = $this->memberResourceTargetPage();

        $parentUrl = null;
        if ($member->parent_id !== null) {
            $parentUrl = MemberResource::getUrl($targetPage, ['record' => $member->parent_id]);
        }

        $dependents = $member->dependents->map(fn(Member $d) => [
            'id' => $d->id,
            'number' => $d->member_number,
            'name' => $d->user?->name ?? '—',
            'url' => MemberResource::getUrl($targetPage, ['record' => $d->id]),
        ]);

        return [
            'hasRecord' => true,
            'member_number' => $member->member_number,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->format('d M Y') ?? '—',
            'months_active' => $monthsActive,
            'monthly_contrib' => (int) $member->monthly_contribution_amount,
            'compliance_rate' => $complianceRate,
            'is_loan_eligible_age' => $isLoanEligibleAge,
            'loan_eligible_date' => $loanEligibleDate?->format('d M Y') ?? '—',

            // User
            'name' => $user?->name ?? '—',
            'email' => $user?->email ?? '—',
            'phone' => $user?->phone ?? $app?->mobile_phone ?? '—',

            // Application personal
            'gender' => $app?->gender ?? null,
            'dob' => $app?->date_of_birth?->format('d M Y') ?? null,
            'national_id' => $app?->national_id ?? null,
            'city' => $app?->city ?? null,
            'occupation' => $app?->occupation ?? null,
            'employer' => $app?->employer ?? null,
            'monthly_income' => $app?->monthly_income ? (float) $app->monthly_income : null,

            // Kin
            'next_of_kin_name' => $app?->next_of_kin_name ?? null,
            'next_of_kin_phone' => $app?->next_of_kin_phone ?? null,

            // Relationships
            'parent_number' => $member->parent?->member_number,
            'parent_name' => $member->parent?->user?->name,
            'parent_url' => $parentUrl,
            'dependents' => $dependents,

            'application_edit_url' => $app !== null
                ? MembershipApplicationResource::getUrl('edit', ['record' => $app])
                : null,
        ];
    }

    protected function memberResourceTargetPage(): string
    {
        return request()->routeIs('filament.admin.resources.members.edit')
            ? 'edit'
            : 'view';
    }
}
