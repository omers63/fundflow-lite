<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\LoanResource;
use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MembershipApplication;
use Filament\Widgets\Widget;

class AdminStatsOverview extends Widget
{
    protected string $view = 'filament.admin.widgets.admin-stats-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $now = now();

        $activeMembers = Member::active()->count();
        $pendingApps = MembershipApplication::where('status', 'pending')->count();
        $totalFund = (float) Contribution::sum('amount');
        $activeLoans = Loan::where('status', 'active')->count();
        $overdueCount = LoanInstallment::where('status', 'overdue')->count();
        $overdueAmount = (float) LoanInstallment::where('status', 'overdue')->sum('amount');
        $delinquent = Member::delinquent()->count();

        $newThisMonth = Member::whereMonth('joined_at', $now->month)
            ->whereYear('joined_at', $now->year)->count();

        $loansThisMonth = Loan::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)->count();

        $contribThisMonth = (float) Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)->sum('amount');

        return [
            'active_members' => $activeMembers,
            'new_this_month' => $newThisMonth,
            'pending_apps' => $pendingApps,
            'total_fund' => $totalFund,
            'active_loans' => $activeLoans,
            'loans_this_month' => $loansThisMonth,
            'overdue_count' => $overdueCount,
            'overdue_amount' => $overdueAmount,
            'delinquent' => $delinquent,
            'contrib_this_month' => $contribThisMonth,
            'members_url' => MemberResource::getUrl('index'),
            'applications_url' => MembershipApplicationResource::getUrl('index'),
            'loans_url' => LoanResource::getUrl('index'),
        ];
    }
}
