<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberRequestService
{
    public function submit(Member $requester, string $type, array $payload): MemberRequest
    {
        $this->validatePayload($requester, $type, $payload);

        $request = MemberRequest::create([
            'requester_member_id' => $requester->id,
            'type' => $type,
            'status' => MemberRequest::STATUS_PENDING,
            'payload' => $payload,
        ]);

        User::where('role', 'admin')->each(function (User $admin) use ($request, $requester): void {
            Notification::make()
                ->title('New member request')
                ->body(
                    ($requester->user?->name ?? 'Member')
                    .' — '
                    .MemberRequest::typeLabel($request->type)
                )
                ->icon('heroicon-o-clipboard-document-list')
                ->iconColor('warning')
                ->sendToDatabase($admin);
        });

        return $request;
    }

    /**
     * @throws ValidationException
     */
    protected function validatePayload(Member $requester, string $type, array $payload): void
    {
        match ($type) {
            MemberRequest::TYPE_ADD_DEPENDENT => $this->validateAddDependent($payload),
            MemberRequest::TYPE_REMOVE_DEPENDENT => $this->validateRemoveDependent($requester, $payload),
            MemberRequest::TYPE_OWN_ALLOCATION => $this->validateOwnAllocation($requester, $payload),
            MemberRequest::TYPE_DEPENDENT_ALLOCATION => $this->validateDependentAllocation($requester, $payload),
            MemberRequest::TYPE_REQUEST_INDEPENDENCE => $this->validateIndependence($requester),
            default => throw ValidationException::withMessages(['type' => 'Invalid request type.']),
        };
    }

    protected function validateAddDependent(array $payload): void
    {
        if (blank($payload['details'] ?? null)) {
            throw ValidationException::withMessages(['details' => 'Please describe who you want to add as a dependent.']);
        }
    }

    protected function validateRemoveDependent(Member $requester, array $payload): void
    {
        $id = (int) ($payload['dependent_member_id'] ?? 0);
        if ($id <= 0) {
            throw ValidationException::withMessages(['dependent_member_id' => 'Select a dependent.']);
        }
        $dep = Member::query()->find($id);
        if (! $dep || (int) $dep->parent_id !== (int) $requester->id) {
            throw ValidationException::withMessages(['dependent_member_id' => 'Invalid dependent.']);
        }
    }

    protected function validateOwnAllocation(Member $requester, array $payload): void
    {
        if ($requester->parent_id !== null) {
            throw ValidationException::withMessages(['member' => 'You must become independent before changing your own allocation. Submit an independence request first.']);
        }
        $amount = (int) ($payload['requested_amount'] ?? 0);
        if (! Member::isValidContributionAmount($amount)) {
            throw ValidationException::withMessages(['requested_amount' => 'Choose a valid monthly amount (SAR 500–3,000 in steps of 500).']);
        }
    }

    protected function validateDependentAllocation(Member $requester, array $payload): void
    {
        $depId = (int) ($payload['dependent_member_id'] ?? 0);
        $amount = (int) ($payload['requested_amount'] ?? 0);
        if ($depId <= 0) {
            throw ValidationException::withMessages(['dependent_member_id' => 'Select a dependent.']);
        }
        if (! Member::isValidContributionAmount($amount)) {
            throw ValidationException::withMessages(['requested_amount' => 'Choose a valid monthly amount (SAR 500–3,000 in steps of 500).']);
        }
        $dep = Member::query()->find($depId);
        if (! $dep || (int) $dep->parent_id !== (int) $requester->id) {
            throw ValidationException::withMessages(['dependent_member_id' => 'Invalid dependent.']);
        }
    }

    protected function validateIndependence(Member $requester): void
    {
        if ($requester->parent_id === null) {
            throw ValidationException::withMessages(['member' => 'You are not linked to a parent sponsor.']);
        }
    }

    public function approve(MemberRequest $request, User $admin): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request is no longer pending.']);
        }

        $requester = $request->requester()->with('user')->firstOrFail();
        $payload = $request->payload ?? [];

        DB::transaction(function () use ($request, $requester, $payload, $admin): void {
            match ($request->type) {
                MemberRequest::TYPE_ADD_DEPENDENT => null,
                MemberRequest::TYPE_REMOVE_DEPENDENT => $this->applyRemoveDependent($requester, $payload),
                MemberRequest::TYPE_OWN_ALLOCATION => $this->applyOwnAllocation($requester, $payload),
                MemberRequest::TYPE_DEPENDENT_ALLOCATION => $this->applyDependentAllocation($requester, $payload),
                MemberRequest::TYPE_REQUEST_INDEPENDENCE => $this->applyIndependence($requester),
                default => throw ValidationException::withMessages(['type' => 'Unknown request type.']),
            };

            $request->update([
                'status' => MemberRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->notifyRequester($requester, $request, 'approved');
    }

    public function reject(MemberRequest $request, User $admin, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request is no longer pending.']);
        }

        $requester = $request->requester()->with('user')->firstOrFail();

        $request->update([
            'status' => MemberRequest::STATUS_REJECTED,
            'admin_note' => $note,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyRequester($requester, $request, 'rejected');
    }

    protected function applyRemoveDependent(Member $parent, array $payload): void
    {
        $id = (int) ($payload['dependent_member_id'] ?? 0);
        $dep = Member::query()->findOrFail($id);
        if ((int) $dep->parent_id !== (int) $parent->id) {
            throw ValidationException::withMessages(['dependent' => 'Dependent no longer linked to this parent.']);
        }
        $dep->update(['parent_id' => null]);
    }

    protected function applyOwnAllocation(Member $member, array $payload): void
    {
        $amount = (int) ($payload['requested_amount'] ?? 0);
        if (! Member::isValidContributionAmount($amount)) {
            throw ValidationException::withMessages(['requested_amount' => 'Invalid amount.']);
        }
        if ((int) $member->monthly_contribution_amount === $amount) {
            throw ValidationException::withMessages(['requested_amount' => 'Amount matches current allocation; nothing to apply.']);
        }
        $member->update(['monthly_contribution_amount' => $amount]);
    }

    protected function applyDependentAllocation(Member $parent, array $payload): void
    {
        $depId = (int) ($payload['dependent_member_id'] ?? 0);
        $amount = (int) ($payload['requested_amount'] ?? 0);
        $dependent = Member::query()->findOrFail($depId);
        if ((int) $dependent->parent_id !== (int) $parent->id) {
            throw ValidationException::withMessages(['dependent' => 'Invalid dependent.']);
        }
        if ((int) $dependent->monthly_contribution_amount === $amount) {
            throw ValidationException::withMessages(['requested_amount' => 'Amount matches current allocation; nothing to apply.']);
        }
        $note = isset($payload['note']) ? (string) $payload['note'] : null;
        $change = app(AllocationService::class)->changeAllocation(
            parent: $parent,
            dependent: $dependent,
            newAmount: $amount,
            note: $note,
            changedBy: auth()->user(),
        );
        if ($change === null) {
            throw ValidationException::withMessages(['requested_amount' => 'Allocation could not be applied.']);
        }
    }

    protected function applyIndependence(Member $member): void
    {
        if ($member->parent_id === null) {
            return;
        }
        $member->update(['parent_id' => null]);
    }

    protected function notifyRequester(Member $requester, MemberRequest $request, string $outcome): void
    {
        $user = $requester->user;
        if (! $user) {
            return;
        }

        $title = $outcome === 'approved' ? 'Request approved' : 'Request declined';
        $body = MemberRequest::typeLabel($request->type);
        if ($request->admin_note && $outcome === 'rejected') {
            $body .= ': '.$request->admin_note;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($outcome === 'approved' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($outcome === 'approved' ? 'success' : 'danger')
            ->sendToDatabase($user);
    }
}
