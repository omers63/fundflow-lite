<?php

namespace App\Http\Livewire;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPasswordPage extends Component
{
    public string $email = '';
    public ?string $statusMessage = null;
    public ?string $statusType = null;

    protected array $rules = [
        'email' => 'required|email',
    ];

    public function sendResetLink(): void
    {
        $this->validate();
        $this->resetErrorBag();
        $this->statusMessage = null;
        $this->statusType = null;

        $candidate = $this->resolveResetCandidate();
        if ($candidate === null) {
            $this->statusType = 'info';
            $this->statusMessage = __('If the account exists, a reset link has been sent.');
            return;
        }

        $result = Password::sendResetLink(['email' => $candidate->email]);
        $this->statusType = $result === Password::RESET_LINK_SENT ? 'success' : 'error';
        $this->statusMessage = __($result);
    }

    protected function resolveResetCandidate(): ?User
    {
        $direct = User::query()
            ->where('email', $this->email)
            ->whereHas('member', fn ($q) => $q
                ->whereNotNull('parent_id')
                ->where('direct_login_enabled', true))
            ->first();
        if ($direct) {
            return $direct;
        }

        $parent = Member::query()
            ->with('user')
            ->whereNull('parent_id')
            ->where('household_email', $this->email)
            ->first();

        return $parent?->user;
    }

    public function render()
    {
        return view('livewire.forgot-password-page')
            ->layout('layouts.public', ['title' => __('Forgot Password')]);
    }
}
