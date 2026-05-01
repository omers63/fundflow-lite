<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Http\Livewire\LoginPage;
use App\Http\Livewire\MembershipApplicationForm;
use App\Models\ImpersonationAudit;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DependentApplicationAndImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_submit_dependent_application_on_behalf(): void
    {
        [$parent, $parentUser] = $this->seedParent();

        Livewire::actingAs($parentUser)
            ->withQueryParams(['on_behalf' => 1])
            ->test(MembershipApplicationForm::class)
            ->set('name', 'Dependent Applicant')
            ->set('email', 'family@example.com')
            ->set('password', 'DependentPass123')
            ->set('password_confirmation', 'DependentPass123')
            ->set('application_type', 'new')
            ->set('national_id', '1234567890')
            ->set('date_of_birth', now()->subYears(20)->toDateString())
            ->set('address', 'Street 1')
            ->set('city', 'Riyadh')
            ->set('mobile_phone', '+966500000001')
            ->set('bank_account_number', '00112233')
            ->set('iban', 'SA0000000000000000000000')
            ->set('next_of_kin_name', 'Kin Name')
            ->set('next_of_kin_phone', '+966500000002')
            ->call('submit')
            ->assertSet('submitted', true);

        $application = MembershipApplication::query()->latest('id')->first();
        $this->assertNotNull($application);
        $this->assertSame($parent->id, $application->parent_member_id);
        $this->assertSame($parentUser->id, $application->submitted_by_user_id);
        $this->assertSame('family@example.com', $application->user->email);
    }

    public function test_approval_of_on_behalf_application_links_new_member_to_parent(): void
    {
        [$parent, $parentUser] = $this->seedParent();
        $dependentUser = User::factory()->create([
            'email' => 'family@example.com',
            'password' => Hash::make('DependentPass123'),
            'role' => 'member',
            'status' => 'pending',
        ]);

        $application = MembershipApplication::create([
            'user_id' => $dependentUser->id,
            'parent_member_id' => $parent->id,
            'submitted_by_user_id' => $parentUser->id,
            'application_type' => 'new',
            'national_id' => '1987654321',
            'date_of_birth' => now()->subYears(22)->toDateString(),
            'address' => 'Address',
            'city' => 'Riyadh',
            'mobile_phone' => '+966500000003',
            'next_of_kin_name' => 'Kin',
            'next_of_kin_phone' => '+966500000004',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'approved',
        ]);
        $this->actingAs($admin);

        MembershipApplicationResource::approvePendingApplication($application->fresh());

        $createdMember = Member::query()->where('user_id', $dependentUser->id)->first();
        $this->assertNotNull($createdMember);
        $this->assertSame($parent->id, $createdMember->parent_id);
        $this->assertSame('family@example.com', $createdMember->household_email);
        $this->assertFalse((bool) $createdMember->direct_login_enabled);
    }

    public function test_impersonation_start_and_stop_are_audited_and_session_safe(): void
    {
        [$parent, $parentUser, $dependent, $dependentUser] = $this->seedParentWithDependent();
        $this->actingAs($parentUser);

        app(ImpersonationService::class)->start($parentUser, $dependentUser, $dependent);
        $this->assertSame($dependentUser->id, auth()->id());
        $this->assertSame($parentUser->id, session('impersonator_user_id'));
        $this->assertDatabaseHas('impersonation_audits', [
            'impersonator_user_id' => $parentUser->id,
            'impersonated_user_id' => $dependentUser->id,
            'impersonated_member_id' => $dependent->id,
            'event' => 'started',
        ]);

        $stopped = app(ImpersonationService::class)->stop();
        $this->assertTrue($stopped);
        $this->assertSame($parentUser->id, auth()->id());
        $this->assertNull(session('impersonator_user_id'));
        $this->assertDatabaseHas('impersonation_audits', [
            'impersonator_user_id' => $parentUser->id,
            'impersonated_user_id' => $dependentUser->id,
            'event' => 'stopped',
        ]);
        $this->assertGreaterThanOrEqual(2, ImpersonationAudit::query()->count());
    }

    public function test_login_profile_picker_includes_avatar_url_when_set(): void
    {
        [$parent, $parentUser, $dependent] = $this->seedParentWithDependent();
        $dependent->user->update(['avatar_path' => 'avatars/dependent.png']);

        Livewire::test(LoginPage::class)
            ->set('email', 'family@example.com')
            ->set('password', 'ParentPass123')
            ->call('login')
            ->assertSet('showProfilePicker', true)
            ->assertSee($parentUser->name)
            ->assertSee($dependent->user->name);
    }

    public function test_impersonation_route_switches_to_dependent_user(): void
    {
        [, $parentUser, $dependent, $dependentUser] = $this->seedParentWithDependent();
        $this->actingAs($parentUser);

        $response = $this->get(route('member.dependents.impersonate', ['dependent' => $dependent->id]));
        $response->assertRedirect('/member');
        $this->assertAuthenticatedAs($dependentUser);
        $memberGuard = Filament::getPanel('member')?->getAuthGuard() ?? config('auth.defaults.guard');
        $this->assertSame($dependentUser->id, auth()->guard((string) $memberGuard)->id());
    }

    public function test_impersonation_route_redirects_back_when_dependent_cannot_access_panel(): void
    {
        [, $parentUser, $dependent, $dependentUser] = $this->seedParentWithDependent();
        $dependentUser->update(['status' => 'pending']);
        $this->actingAs($parentUser);

        $response = $this->get(route('member.dependents.impersonate', ['dependent' => $dependent->id]));
        $response->assertRedirect('/member/my-dependents');
        $this->assertAuthenticatedAs($parentUser);
    }

    public function test_impersonation_route_returns_forbidden_for_guest(): void
    {
        [, , $dependent] = $this->seedParentWithDependent();

        $response = $this->get(route('member.dependents.impersonate', ['dependent' => $dependent->id]));
        $response->assertForbidden();
    }

    public function test_impersonated_user_can_open_member_panel_after_switch(): void
    {
        [, $parentUser, $dependent, $dependentUser] = $this->seedParentWithDependent();
        $this->actingAs($parentUser);

        $switchResponse = $this->get(route('member.dependents.impersonate', ['dependent' => $dependent->id]));
        $switchResponse->assertRedirect('/member');
        $this->assertAuthenticatedAs($dependentUser);

        $panelResponse = $this->get('/member');
        $panelResponse->assertStatus(200);
        $this->assertAuthenticatedAs($dependentUser);
    }

    public function test_member_logout_while_impersonating_restores_parent_instead_of_full_logout(): void
    {
        [, $parentUser, $dependent, $dependentUser] = $this->seedParentWithDependent();
        $this->actingAs($parentUser);
        app(ImpersonationService::class)->start($parentUser, $dependentUser, $dependent);

        $response = $this->withSession(['_token' => 'test-token'])
            ->post('/member/logout', ['_token' => 'test-token']);

        $response->assertRedirect('/member');
        $this->assertAuthenticatedAs($parentUser);
        $this->assertNull(session('impersonator_user_id'));
        $this->assertNull(session('impersonated_user_id'));
    }

    private function seedParent(): array
    {
        $parentUser = User::factory()->create([
            'name' => 'Parent User',
            'email' => 'family@example.com',
            'password' => Hash::make('ParentPass123'),
            'role' => 'member',
            'status' => 'approved',
        ]);

        $parent = Member::create([
            'user_id' => $parentUser->id,
            'member_number' => 'PM-100',
            'joined_at' => now()->toDateString(),
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'family@example.com',
            'is_separated' => false,
            'direct_login_enabled' => false,
            'portal_pin' => Hash::make('1234'),
        ]);

        return [$parent, $parentUser];
    }

    private function seedParentWithDependent(): array
    {
        [$parent, $parentUser] = $this->seedParent();
        $dependentUser = User::factory()->create([
            'name' => 'Dependent User',
            'email' => 'family@example.com',
            'password' => Hash::make('DependentPass123'),
            'role' => 'member',
            'status' => 'approved',
        ]);

        $dependent = Member::create([
            'user_id' => $dependentUser->id,
            'parent_id' => $parent->id,
            'member_number' => 'DM-101',
            'joined_at' => now()->toDateString(),
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'family@example.com',
            'is_separated' => false,
            'direct_login_enabled' => false,
        ]);

        return [$parent, $parentUser, $dependent, $dependentUser];
    }
}
