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

    public string $password = '';

    public string $password_confirmation = '';

    // Step 2: Identity & Address
    public string $application_type = 'new';

    public string $gender = '';

    public string $marital_status = '';

    public string $national_id = '';

    public string $date_of_birth = '';

    public string $address = '';

    public string $city = '';

    public string $home_phone = '';

    public string $work_phone = '';

    public string $mobile_phone = '';

    public string $work_place = '';

    public string $residency_place = '';

    public string $bank_account_number = '';

    public string $iban = '';

    public string $membership_date = '';

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
            'password' => 'required|min:8|confirmed',
            'application_type' => 'required|in:new,resume,renew',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed,other',
            'national_id' => 'required|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'home_phone' => 'nullable|string|max:30',
            'work_phone' => 'nullable|string|max:30',
            'mobile_phone' => 'required|string|max:30',
            'work_place' => 'nullable|string|max:255',
            'residency_place' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'iban' => 'nullable|string|max:34',
            'membership_date' => 'nullable|date',
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
                'password' => 'required|min:8|confirmed',
            ],
            2 => [
                'application_type' => 'required|in:new,resume,renew',
                'gender' => 'nullable|in:male,female,other',
                'marital_status' => 'nullable|in:single,married,divorced,widowed,other',
                'national_id' => 'required|string|max:20',
                'date_of_birth' => 'required|date|before:today',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'home_phone' => 'nullable|string|max:30',
                'work_phone' => 'nullable|string|max:30',
                'mobile_phone' => 'required|string|max:30',
                'work_place' => 'nullable|string|max:255',
                'residency_place' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:50',
                'iban' => 'nullable|string|max:34',
                'membership_date' => 'nullable|date',
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
        $this->validate();

        $filePath = null;
        if ($this->application_form) {
            $filePath = $this->application_form->store('applications', 'public');
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->mobile_phone,
            'role' => 'member',
            'status' => 'pending',
            'password' => Hash::make($this->password),
        ]);

        MembershipApplication::create([
            'user_id' => $user->id,
            'application_type' => $this->application_type,
            'gender' => filled($this->gender) ? $this->gender : null,
            'marital_status' => filled($this->marital_status) ? $this->marital_status : null,
            'national_id' => $this->national_id,
            'date_of_birth' => $this->date_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'home_phone' => filled($this->home_phone) ? $this->home_phone : null,
            'work_phone' => filled($this->work_phone) ? $this->work_phone : null,
            'mobile_phone' => $this->mobile_phone,
            'occupation' => $this->occupation ?: null,
            'employer' => $this->employer ?: null,
            'work_place' => filled($this->work_place) ? $this->work_place : null,
            'residency_place' => filled($this->residency_place) ? $this->residency_place : null,
            'monthly_income' => filled($this->monthly_income) ? $this->monthly_income : null,
            'bank_account_number' => filled($this->bank_account_number) ? $this->bank_account_number : null,
            'iban' => filled($this->iban) ? strtoupper($this->iban) : null,
            'membership_date' => filled($this->membership_date) ? $this->membership_date : null,
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
