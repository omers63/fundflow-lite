<?php

namespace App\Http\Livewire;

use App\Models\MembershipApplication;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class MembershipApplicationForm extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;

    public int $totalSteps = 4;

    /** When true, the membership fee step is shown last (before submit). */
    public bool $hasApplicationFee = false;

    /** @var array<int, string> */
    public array $stepLabels = [];

    // Step 1: Personal Information
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Payment (when fee > 0)
    public string $membership_fee_transfer_reference = '';

    public bool $membership_fee_acknowledged = false;

    // Identity & Address
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

    // Employment & Next of Kin
    public string $occupation = '';

    public string $employer = '';

    public string $monthly_income = '';

    public string $next_of_kin_name = '';

    public string $next_of_kin_phone = '';

    // Document Upload
    public $application_form = null;

    public bool $submitted = false;

    /** True when total applications are at or above the configured public cap. */
    public bool $applicationCapReached = false;

    public function mount(): void
    {
        $this->applicationCapReached = $this->checkApplicationCapReached();
        $this->hasApplicationFee = Setting::publicMembershipApplicationFeesEnabled();
        $this->totalSteps = $this->hasApplicationFee ? 5 : 4;
        $this->stepLabels = $this->hasApplicationFee
            ? ['Personal Info', 'Identity', 'Employment', 'Document', 'Membership fee']
            : ['Personal Info', 'Identity', 'Employment', 'Document'];
    }

    /** Fee (SAR) for the currently selected application type; 0 if fees are disabled or this type is free. */
    public function currentApplicationFeeAmount(): float
    {
        if (! $this->hasApplicationFee) {
            return 0.0;
        }

        return Setting::membershipApplicationFeeForType($this->application_type);
    }

    protected function checkApplicationCapReached(): bool
    {
        if (! Setting::publicApplicationCapEnabled()) {
            return false;
        }

        return MembershipApplication::query()->count() >= Setting::maxPublicApplications();
    }

    /** Step index 1-based → logical step name. */
    public function stepKindAt(int $step): string
    {
        $sequence = $this->hasApplicationFee
            ? ['personal', 'identity', 'employment', 'document', 'payment']
            : ['personal', 'identity', 'employment', 'document'];

        return $sequence[$step - 1] ?? 'personal';
    }

    protected function rules(): array
    {
        $rules = [
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
            'bank_account_number' => 'required|string|max:50',
            'iban' => 'required|string|max:34',
            'membership_date' => 'nullable|date',
            'occupation' => 'nullable|string|max:150',
            'employer' => 'nullable|string|max:150',
            'monthly_income' => 'nullable|numeric|min:0',
            'next_of_kin_name' => 'required|string|max:150',
            'next_of_kin_phone' => 'required|string|max:30',
            'application_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        if ($this->hasApplicationFee && $this->currentApplicationFeeAmount() > 0) {
            $rules['membership_fee_transfer_reference'] = 'required|string|min:3|max:120';
            $rules['membership_fee_acknowledged'] = 'accepted';
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesForStep(int $step): array
    {
        $kind = $this->stepKindAt($step);

        return match ($kind) {
            'personal' => [
                'name' => 'required|string|max:150',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'application_type' => 'required|in:new,resume,renew',
            ],
            'payment' => $this->hasApplicationFee && $this->currentApplicationFeeAmount() > 0
                ? [
                    'membership_fee_transfer_reference' => 'required|string|min:3|max:120',
                    'membership_fee_acknowledged' => 'accepted',
                ]
                : [],
            'identity' => [
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
                'bank_account_number' => 'required|string|max:50',
                'iban' => 'required|string|max:34',
                'membership_date' => 'nullable|date',
            ],
            'employment' => [
                'next_of_kin_name' => 'required|string|max:150',
                'next_of_kin_phone' => 'required|string|max:30',
            ],
            'document' => [
                'application_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ],
            default => [],
        };
    }

    public function nextStep(): void
    {
        if ($this->applicationCapReached) {
            return;
        }

        $this->validate($this->rulesForStep($this->currentStep));
        $this->currentStep++;
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function submit(): void
    {
        if ($this->applicationCapReached) {
            throw ValidationException::withMessages([
                'form' => 'We are not accepting new applications at the moment. Please try again later.',
            ]);
        }

        $this->validate();

        $feeAmount = $this->hasApplicationFee ? $this->currentApplicationFeeAmount() : 0.0;

        try {
            Cache::lock('membership_public_apply_submit', 15)->block(8, function () use ($feeAmount): void {
                if (Setting::publicApplicationCapEnabled()) {
                    $applicationCount = MembershipApplication::query()->count();
                    if ($applicationCount >= Setting::maxPublicApplications()) {
                        throw ValidationException::withMessages([
                            'form' => 'We are not accepting new applications at the moment. Please try again later.',
                        ]);
                    }
                }

                DB::transaction(function () use ($feeAmount) {
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

                    $application = MembershipApplication::create([
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
                        'membership_fee_amount' => $feeAmount > 0 ? $feeAmount : null,
                        'membership_fee_transfer_reference' => $feeAmount > 0 ? $this->membership_fee_transfer_reference : null,
                        'status' => 'pending',
                    ]);

                    if ($feeAmount > 0) {
                        app(AccountingService::class)->postMembershipApplicationFeeToMasterCash($application);
                    }
                });
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'form' => 'The application service is busy. Please wait a moment and try again.',
            ]);
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.membership-application-form')
            ->layout('layouts.public', ['title' => 'Apply for Membership']);
    }
}
