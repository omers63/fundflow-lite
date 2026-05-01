<?php

namespace App\Http\Livewire;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class LoginPage extends Component
{
    public string $email = '';
    public string $password = '';
    public string $verificationSecret = '';
    public bool $remember = false;
    public bool $showProfilePicker = false;
    public ?int $householdParentId = null;
    public ?int $selectedMemberId = null;
    public array $availableProfiles = [];

    public ?string $statusMessage = null;
    public ?string $statusType = null;
    public ?string $rejectionReason = null;

    public function mount(): void
    {
        if (session()->pull('member_suspended_notice')) {
            $this->statusType = 'suspended';
            $this->statusMessage = __('Your member portal access is currently suspended. Please contact fund administration for support.');
        } elseif (session()->pull('member_terminated_notice')) {
            $this->statusType = 'terminated';
            $this->statusMessage = __('Your membership has been terminated. Member portal access is no longer available. Please contact fund administration for support.');
        }
    }

    protected array $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];

    public function login(): void
    {
        $this->validate();
        $this->resetErrorBag();
        if (!$this->ensureNotRateLimited('login')) {
            return;
        }

        $directUser = $this->resolveDirectUser();
        if ($directUser !== null) {
            $this->clearRateLimiter('login');
            $this->completeLogin($directUser);

            return;
        }

        $householdParent = $this->resolveHouseholdParent();
        if ($householdParent === null) {
            $adminUser = $this->resolveAdminUser();
            if ($adminUser !== null) {
                $this->clearRateLimiter('login');
                $this->completeLogin($adminUser);

                return;
            }

            $this->addError('email', __('No account found with this email address.'));

            return;
        }

        if (!Hash::check($this->password, (string) $householdParent->user?->password)) {
            RateLimiter::hit($this->rateLimitKey('login'), 300);
            $this->addError('password', __('The provided credentials are incorrect.'));

            return;
        }

        if (!$householdParent->dependents()->exists()) {
            $this->clearRateLimiter('login');
            $this->completeLogin($householdParent->user);

            return;
        }

        $this->clearRateLimiter('login');

        $this->householdParentId = $householdParent->id;
        $this->showProfilePicker = true;
        $this->loadAvailableProfiles($householdParent);
        $this->password = '';
    }

    public function selectProfile(int $memberId): void
    {
        if (!$this->showProfilePicker || $this->householdParentId === null) {
            return;
        }

        $selected = Member::query()
            ->with('user')
            ->whereKey($memberId)
            ->where(function ($query): void {
                $query
                    ->whereKey($this->householdParentId)
                    ->orWhere('parent_id', $this->householdParentId);
            })
            ->first();

        if ($selected === null) {
            $this->addError('selectedMemberId', __('Invalid profile selected.'));

            return;
        }

        $this->selectedMemberId = $selected->id;
    }

    public function verifySelectedProfile(): void
    {
        $this->resetErrorBag('verificationSecret');
        if (!$this->ensureNotRateLimited('profile_verification')) {
            return;
        }

        if ($this->householdParentId === null || $this->selectedMemberId === null) {
            $this->addError('selectedMemberId', __('Please select a profile.'));

            return;
        }

        $selected = Member::query()
            ->with('user')
            ->whereKey($this->selectedMemberId)
            ->where(function ($query): void {
                $query
                    ->whereKey($this->householdParentId)
                    ->orWhere('parent_id', $this->householdParentId);
            })
            ->first();

        if ($selected === null) {
            $this->addError('selectedMemberId', __('Invalid profile selected.'));

            return;
        }

        if ($selected->id === $this->householdParentId) {
            $pinHash = (string) ($selected->portal_pin ?? '');
            $isValidParentSecret = $pinHash !== ''
                ? Hash::check($this->verificationSecret, $pinHash)
                : Hash::check($this->verificationSecret, (string) $selected->user?->password);

            if (!$isValidParentSecret) {
                RateLimiter::hit($this->rateLimitKey('profile_verification'), 300);
                $this->addError('verificationSecret', __('The parent PIN is incorrect.'));

                return;
            }
        } else {
            if (!Hash::check($this->verificationSecret, (string) $selected->user?->password)) {
                RateLimiter::hit($this->rateLimitKey('profile_verification'), 300);
                $this->addError('verificationSecret', __('The dependent password is incorrect.'));

                return;
            }
        }

        $this->clearRateLimiter('profile_verification');
        $this->completeLogin($selected->user);
    }

    public function backToEmailStep(): void
    {
        $this->showProfilePicker = false;
        $this->householdParentId = null;
        $this->selectedMemberId = null;
        $this->availableProfiles = [];
        $this->verificationSecret = '';
    }

    protected function completeLogin(User $user): void
    {
        if ($user->status === 'pending') {
            $this->statusType = 'pending';
            $this->statusMessage = __('Your membership application is currently under review. You will be notified once it is processed.');
            return;
        }

        if ($user->status === 'rejected') {
            $this->statusType = 'rejected';
            $this->statusMessage = __('Your membership application was not approved.');
            $this->rejectionReason = $user->membershipApplication?->rejection_reason;
            return;
        }

        if ($user->member?->status === 'suspended') {
            $this->statusType = 'suspended';
            $this->statusMessage = __('Your member portal access is currently suspended. Please contact fund administration for support.');
            return;
        }

        if ($user->member?->status === 'terminated') {
            $this->statusType = 'terminated';
            $this->statusMessage = __('Your membership has been terminated. Member portal access is no longer available. Please contact fund administration for support.');
            return;
        }

        if (!$this->canAccessMemberPanel($user)) {
            if ($user->isAdmin()) {
                Auth::login($user, $this->remember);
                session()->regenerate();
                $this->redirect('/admin', navigate: false);

                return;
            }

            $this->addError('email', __('Use the administrator login page for admin access.'));

            return;
        }

        Auth::login($user, $this->remember);

        session()->regenerate();

        $this->redirect('/member', navigate: false);
    }

    protected function resolveDirectUser(): ?User
    {
        $user = User::query()
            ->where('email', $this->email)
            ->whereHas('member', fn($q) => $q
                ->whereNotNull('parent_id')
                ->where('direct_login_enabled', true))
            ->first();

        if ($user === null) {
            return null;
        }

        if (!Hash::check($this->password, (string) $user->password)) {
            return null;
        }

        return $user;
    }

    protected function resolveHouseholdParent(): ?Member
    {
        return Member::query()
            ->with('user')
            ->whereNull('parent_id')
            ->where('household_email', $this->email)
            ->first();
    }

    protected function resolveAdminUser(): ?User
    {
        $user = User::query()
            ->where('email', $this->email)
            ->where('role', 'admin')
            ->first();

        if ($user === null) {
            return null;
        }

        if (!Hash::check($this->password, (string) $user->password)) {
            return null;
        }

        return $user;
    }

    protected function loadAvailableProfiles(Member $householdParent): void
    {
        $profiles = Member::query()
            ->with('user')
            ->where(function ($query) use ($householdParent): void {
                $query->whereKey($householdParent->id)
                    ->orWhere('parent_id', $householdParent->id);
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$householdParent->id])
            ->get();

        $this->availableProfiles = $profiles
            ->map(fn(Member $m): array => [
                'id' => $m->id,
                'name' => (string) ($m->user?->name ?? __('Member')),
                'is_parent' => $m->id === $householdParent->id,
                'is_separated' => (bool) $m->is_separated,
                'avatar_url' => filled($m->user?->avatar_path) ? asset('storage/' . $m->user->avatar_path) : null,
            ])
            ->all();
    }

    protected function ensureNotRateLimited(string $scope): bool
    {
        $key = $this->rateLimitKey($scope);
        if (!RateLimiter::tooManyAttempts($key, 5)) {
            return true;
        }

        $seconds = RateLimiter::availableIn($key);
        $this->addError('email', __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]));

        return false;
    }

    protected function clearRateLimiter(string $scope): void
    {
        RateLimiter::clear($this->rateLimitKey($scope));
    }

    protected function rateLimitKey(string $scope): string
    {
        return $scope . '|' . Str::lower($this->email) . '|' . request()->ip();
    }

    protected function canAccessMemberPanel(User $user): bool
    {
        $memberPanel = \Filament\Facades\Filament::getPanel('member');

        return $memberPanel !== null && $user->canAccessPanel($memberPanel);
    }

    public function render()
    {
        return view('livewire.login-page')
            ->layout('layouts.public', ['title' => __('Sign In')]);
    }
}
