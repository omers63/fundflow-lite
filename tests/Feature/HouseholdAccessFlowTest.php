<?php

namespace Tests\Feature;

use App\Http\Livewire\ForgotPasswordPage;
use App\Http\Livewire\LoginPage;
use App\Models\Member;
use App\Models\User;
use App\Services\HouseholdAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_login_shows_parent_and_dependents_in_profile_picker(): void
    {
        [$parent, $dependent] = $this->seedHousehold();

        Livewire::test(LoginPage::class)
            ->set('email', 'family@example.com')
            ->set('password', 'ParentPass123')
            ->call('login')
            ->assertSet('showProfilePicker', true)
            ->assertSee($parent->user->name)
            ->assertSee($dependent->user->name);
    }

    public function test_member_without_dependents_logs_in_directly_without_profile_picker(): void
    {
        [$parent] = $this->seedHousehold();
        $parent->dependents()->delete();

        Livewire::test(LoginPage::class)
            ->set('email', 'family@example.com')
            ->set('password', 'ParentPass123')
            ->call('login')
            ->assertRedirect('/member');
    }

    public function test_admin_without_member_record_can_login_from_member_login_page_to_admin_panel(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass123'),
            'role' => 'admin',
            'status' => 'approved',
        ]);

        Livewire::test(LoginPage::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'AdminPass123')
            ->call('login')
            ->assertRedirect('/admin');
    }

    public function test_admin_with_member_record_can_login_from_member_login_page_as_member(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Member',
            'email' => 'admin-member@example.com',
            'password' => Hash::make('AdminPass123'),
            'role' => 'admin',
            'status' => 'approved',
        ]);

        Member::create([
            'user_id' => $admin->id,
            'member_number' => 'M-ADM-1',
            'joined_at' => now()->toDateString(),
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'admin-member@example.com',
            'is_separated' => false,
            'direct_login_enabled' => false,
        ]);

        Livewire::test(LoginPage::class)
            ->set('email', 'admin-member@example.com')
            ->set('password', 'AdminPass123')
            ->call('login')
            ->assertRedirect('/member');
    }

    public function test_separated_dependent_can_login_directly(): void
    {
        [, $dependent] = $this->seedHousehold();
        $dependent->update([
            'is_separated' => true,
            'direct_login_enabled' => true,
            'household_email' => 'family@example.com',
        ]);
        $dependent->user->update(['email' => 'dep@example.com']);

        Livewire::test(LoginPage::class)
            ->set('email', 'dep@example.com')
            ->set('password', 'DependentPass123')
            ->call('login')
            ->assertRedirect('/member');
    }

    public function test_rejoin_disables_direct_public_access(): void
    {
        [, $dependent] = $this->seedHousehold();
        $dependent->update(['is_separated' => true, 'direct_login_enabled' => true]);
        $dependent->user->update(['email' => 'dep@example.com']);

        app(HouseholdAccessService::class)->updateMemberLoginEmail(
            $dependent->fresh('parent.user'),
            $dependent->user()->first(),
            'family@example.com'
        );

        $dependent->refresh();
        $this->assertFalse($dependent->direct_login_enabled);
        $this->assertFalse($dependent->is_separated);
    }

    public function test_login_rate_limit_blocks_after_multiple_failed_attempts(): void
    {
        $this->seedHousehold();

        $component = Livewire::test(LoginPage::class)->set('email', 'family@example.com');
        for ($i = 0; $i < 6; $i++) {
            $component->set('password', 'WrongPassword123')->call('login');
        }

        $component->assertHasErrors(['email']);
    }

    public function test_forgot_password_targets_household_parent_for_shared_email(): void
    {
        [$parent] = $this->seedHousehold();
        Notification::fake();

        Livewire::test(ForgotPasswordPage::class)
            ->set('email', 'family@example.com')
            ->call('sendResetLink');

        Notification::assertSentTo($parent->user, ResetPassword::class);
    }

    public function test_forgot_password_targets_separated_dependent_direct_email(): void
    {
        [, $dependent] = $this->seedHousehold();
        $dependent->update(['is_separated' => true, 'direct_login_enabled' => true]);
        $dependent->user->update(['email' => 'dep@example.com']);
        Notification::fake();

        Livewire::test(ForgotPasswordPage::class)
            ->set('email', 'dep@example.com')
            ->call('sendResetLink');

        Notification::assertSentTo($dependent->user, ResetPassword::class);
    }

    private function seedHousehold(): array
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
            'member_number' => 'M-100',
            'joined_at' => now()->toDateString(),
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'family@example.com',
            'is_separated' => false,
            'direct_login_enabled' => false,
            'portal_pin' => Hash::make('1234'),
        ]);

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
            'member_number' => 'M-101',
            'joined_at' => now()->toDateString(),
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'family@example.com',
            'is_separated' => false,
            'direct_login_enabled' => false,
        ]);

        return [$parent, $dependent];
    }
}
