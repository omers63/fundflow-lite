<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Member;
use Filament\Widgets\Widget;

class AdminWelcomeBannerWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.admin-welcome-banner';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $user = auth()->user();
        $now = now();

        $greeting = match (true) {
            $now->hour < 12 => 'Good morning',
            $now->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $masterFund = (float) (Account::masterFund()?->balance ?? 0);
        $masterCash = (float) (Account::masterCash()?->balance ?? 0);
        $activeLoans = Loan::where('status', 'active')->count();
        $activeMembers = Member::active()->count();

        $paidThisMonth = Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->distinct('member_id')
            ->count('member_id');

        $complianceRate = $activeMembers > 0
            ? round($paidThisMonth / $activeMembers * 100)
            : 0;

        return [
            'greeting' => $greeting,
            'name' => $user?->name ?? 'Admin',
            'date' => $now->format('l, F j Y'),
            'masterFund' => $masterFund,
            'masterCash' => $masterCash,
            'activeLoans' => $activeLoans,
            'activeMembers' => $activeMembers,
            'complianceRate' => $complianceRate,
            'paidThisMonth' => $paidThisMonth,
        ];
    }
}
