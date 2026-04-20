<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LoginPage extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public ?string $statusMessage = null;
    public ?string $statusType = null;
    public ?string $rejectionReason = null;

    protected array $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];

    public function login(): void
    {
        $this->validate();

        $user = \App\Models\User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', __('No account found with this email address.'));
            return;
        }

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

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('password', __('The provided credentials are incorrect.'));
            return;
        }

        session()->regenerate();

        $this->redirect(
            $user->role === 'admin' ? '/admin' : '/member',
            navigate: false
        );
    }

    public function render()
    {
        return view('livewire.login-page')
            ->layout('layouts.public', ['title' => __('Sign In')]);
    }
}
