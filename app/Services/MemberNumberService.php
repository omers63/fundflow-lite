<?php

namespace App\Services;

use App\Models\Member;

class MemberNumberService
{
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "FF-{$year}-";

        $lastMember = Member::where('member_number', 'like', "{$prefix}%")
            ->orderByDesc('member_number')
            ->first();

        $sequence = 1;
        if ($lastMember) {
            $parts = explode('-', $lastMember->member_number);
            $sequence = (int) end($parts) + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
