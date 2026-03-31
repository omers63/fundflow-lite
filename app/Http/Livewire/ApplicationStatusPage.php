<?php

namespace App\Http\Livewire;

use App\Models\MembershipApplication;
use App\Models\User;
use Livewire\Component;

class ApplicationStatusPage extends Component
{
    public string $email = '';
    public string $national_id = '';
    public bool $searched = false;
    public ?array $result = null;

    protected array $rules = [
        'email' => 'required|email',
        'national_id' => 'required|string|min:5',
    ];

    public function check(): void
    {
        $this->validate();
        $this->searched = true;

        $user = User::where('email', $this->email)->first();
        if (! $user) {
            $this->result = null;
            return;
        }

        $application = MembershipApplication::where('user_id', $user->id)
            ->where('national_id', $this->national_id)
            ->first();

        if (! $application) {
            $this->result = null;
            return;
        }

        $this->result = [
            'name' => $user->name,
            'status' => $application->status,
            'submitted_at' => $application->created_at->format('d M Y'),
            'reviewed_at' => $application->reviewed_at?->format('d M Y'),
            'rejection_reason' => $application->rejection_reason,
            'city' => $application->city,
        ];
    }

    public function render()
    {
        return view('livewire.application-status-page')
            ->layout('layouts.public', ['title' => 'Application Status']);
    }
}
