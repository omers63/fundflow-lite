<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MemberPortalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure the role exists in Shield/Spatie role manager.
        $memberRole = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'web',
        ]);

        // Note:
        // "My*" resources reuse model permissions (e.g. Loan, Contribution), so
        // the permission names are model/page/widget based instead of "My*".
        $permissionNames = [
            // Resources/models used by member portal.
            'ViewAny:Loan',
            'View:Loan',
            'Create:Loan',
            'Update:Loan',

            'ViewAny:LoanInstallment',
            'View:LoanInstallment',

            'ViewAny:Contribution',
            'View:Contribution',

            'ViewAny:MonthlyStatement',
            'View:MonthlyStatement',

            'ViewAny:AccountTransaction',
            'View:AccountTransaction',

            // Needed for dependent management and member self-profile interactions.
            'ViewAny:Member',
            'View:Member',
            'Create:Member',
            'Update:Member',
            'Delete:Member',

            // Member panel pages.
            'View:Dashboard',
            'View:LoanCalculatorPage',
            'View:MyProfilePage',
            'View:MyContributionSettingsPage',
            'View:SupportPage',
            'View:MyInboxPage',

            // Member dashboard widgets.
            'View:MemberStatsOverview',
            'View:MemberWelcomeBannerWidget',
            'View:MemberStatusWidget',
            'View:AccountBalancesWidget',
            'View:UpcomingPaymentsWidget',
            'View:LoanRepaymentProgressWidget',
            'View:ContributionHistoryWidget',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $memberRole->syncPermissions($permissionNames);

        // Backfill role assignment for existing users marked as members.
        User::query()
            ->where('role', 'member')
            ->each(function (User $user) use ($memberRole): void {
                if (!$user->hasRole($memberRole->name)) {
                    $user->assignRole($memberRole);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
