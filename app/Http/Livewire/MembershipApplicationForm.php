<?php

namespace App\Http\Livewire;

use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class MembershipApplicationForm extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;
    public int $totalSteps = 4;

    // Step 1: Personal Information
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Step 2: Identity & Address
    public string $national_id = '';
    public string $date_of_birth = '';
    public string $address = '';
    public string $city = '';

    // Step 3: Employment & Next of Kin
    public string $occupation = '';
    public string $employer = '';
    public string $monthly_income = '';
    public string $next_of_kin_name = '';
    public string $next_of_kin_phone = '';

    // Step 4: Document Upload
    public $application_form = null;

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:30',
            'password' => 'required|min:8|confirmed',
            'national_id' => 'required|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'occupation' => 'nullable|string|max:150',
            'employer' => 'nullable|string|max:150',
            'monthly_income' => 'nullable|numeric|min:0',
            'next_of_kin_name' => 'required|string|max:150',
            'next_of_kin_phone' => 'required|string|max:30',
            'application_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    protected function stepRules(): array
    {
        return [
            1 => [
                'name' => 'required|string|max:150',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:30',
                'password' => 'required|min:8|confirmed',
            ],
            2 => [
                'national_id' => 'required|string|max:20',
                'date_of_birth' => 'required|date|before:today',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
            ],
            3 => [
                'next_of_kin_name' => 'required|string|max:150',
                'next_of_kin_phone' => 'required|string|max:30',
            ],
            4 => [
                'application_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ],
        ];
    }

    public function nextStep(): void
    {
        $this->validate($this->stepRules()[$this->currentStep]);
        $this->currentStep++;
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function submit(): void
    {
        $this->validate($this->stepRules()[4]);

        $filePath = null;
        if ($this->application_form) {
            $filePath = $this->application_form->store('applications', 'public');
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => 'member',
            'status' => 'pending',
            'password' => Hash::make($this->password),
        ]);

        MembershipApplication::create([
            'user_id' => $user->id,
            'national_id' => $this->national_id,
            'date_of_birth' => $this->date_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'occupation' => $this->occupation ?: null,
            'employer' => $this->employer ?: null,
            'monthly_income' => $this->monthly_income ?: null,
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'application_form_path' => $filePath,
            'status' => 'pending',
        ]);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.membership-application-form')
            ->layout('layouts.public', ['title' => 'Apply for Membership']);
    }
}
