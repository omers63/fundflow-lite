<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\Member;
use App\Models\MembershipApplication;
use Filament\Widgets\Widget;

class MemberPipelineWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.admin.widgets.member-pipeline';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $pendingApps = MembershipApplication::where('status', 'pending')->count();
        $approvedApps = MembershipApplication::where('status', 'approved')->count();
        $activeMembers = Member::active()->count();
        $delinquent = Member::delinquent()->count();
        $suspended = Member::where('status', 'suspended')->count();

        // New members this month
        $newThisMonth = Member::whereMonth('joined_at', now()->month)
            ->whereYear('joined_at', now()->year)
            ->count();

        // New applications this month
        $appsThisMonth = MembershipApplication::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            'pending_apps' => $pendingApps,
            'approved_apps' => $approvedApps,
            'active_members' => $activeMembers,
            'delinquent' => $delinquent,
            'suspended' => $suspended,
            'new_this_month' => $newThisMonth,
            'apps_this_month' => $appsThisMonth,

            'applications_url' => MembershipApplicationResource::getUrl('index'),
            'members_url' => MemberResource::getUrl('index'),
        ];
    }
}
