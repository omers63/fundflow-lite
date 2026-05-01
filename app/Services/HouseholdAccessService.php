<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;

class HouseholdAccessService
{
    public function updateMemberLoginEmail(Member $member, User $user, string $newEmail): array
    {
        $oldEmail = (string) $user->email;
        if ($newEmail === $oldEmail) {
            return ['changed' => false, 'rejoined' => false];
        }

        $parentHouseholdEmail = (string) ($member->parent?->household_email ?? $member->parent?->user?->email ?? '');
        $emailInUseByAnother = User::query()
            ->where('email', $newEmail)
            ->whereKeyNot($user->id)
            ->exists();

        if ($emailInUseByAnother && ! ($member->parent_id !== null && $newEmail === $parentHouseholdEmail)) {
            throw new \InvalidArgumentException('Email already in use.');
        }

        $user->update(['email' => $newEmail]);

        if ($member->parent_id === null) {
            $member->update([
                'household_email' => $newEmail,
                'is_separated' => false,
                'direct_login_enabled' => false,
            ]);

            $member->dependents()
                ->where('is_separated', false)
                ->update([
                    'household_email' => $newEmail,
                    'direct_login_enabled' => false,
                ]);

            return ['changed' => true, 'rejoined' => false];
        }

        $isRejoin = $parentHouseholdEmail !== '' && $newEmail === $parentHouseholdEmail;
        $member->update([
            'household_email' => $parentHouseholdEmail !== '' ? $parentHouseholdEmail : $newEmail,
            'is_separated' => ! $isRejoin,
            'direct_login_enabled' => ! $isRejoin,
        ]);

        return ['changed' => true, 'rejoined' => $isRejoin];
    }
}
