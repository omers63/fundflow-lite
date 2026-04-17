<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-12 px-4">
    <div class="max-w-2xl mx-auto">

        @if($submitted)
        {{-- Success State --}}
        <div class="bg-white rounded-3xl shadow-xl p-12 text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">Application Submitted!</h2>
            <p class="text-slate-500 mb-2">Your membership application has been received and is now under review.</p>
            <p class="text-slate-500 text-sm mb-8">You will be notified via email, SMS, and WhatsApp once your application is processed.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('application.status') }}" class="bg-blue-600 text-white font-semibold px-6 py-3 rounded-xl hover:bg-blue-700 transition-colors">
                    Check Application Status
                </a>
                <a href="{{ route('home') }}" class="bg-slate-100 text-slate-700 font-semibold px-6 py-3 rounded-xl hover:bg-slate-200 transition-colors">
                    Back to Home
                </a>
            </div>
        </div>
        @elseif($applicationCapReached)
        <div class="bg-white rounded-3xl shadow-xl p-10 sm:p-12 text-center">
            <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">Applications temporarily closed</h2>
            <p class="text-slate-600 max-w-md mx-auto mb-2">
                We are not accepting new membership applications right now because our review queue is full.
            </p>
            <p class="text-slate-500 text-sm max-w-md mx-auto mb-8">
                If you already applied, you can check your status below. Otherwise, please try again later.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('application.status') }}" class="bg-blue-600 text-white font-semibold px-6 py-3 rounded-xl hover:bg-blue-700 transition-colors">
                    Check application status
                </a>
                <a href="{{ route('home') }}" class="bg-slate-100 text-slate-700 font-semibold px-6 py-3 rounded-xl hover:bg-slate-200 transition-colors">
                    Back to home
                </a>
            </div>
        </div>
        @else

        {{-- Progress Header --}}
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-slate-800 mb-2">Apply for Membership</h1>
            <p class="text-slate-500">Complete the form below to join the FundFlow family fund.</p>
            <a
                href="{{ route('downloads.terms-and-conditions') }}"
                class="inline-flex mt-4 items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-8m0 8l-3-3m3 3l3-3M4 16.5A2.5 2.5 0 006.5 19h11a2.5 2.5 0 002.5-2.5"/>
                </svg>
                Download Fund Terms &amp; Conditions (PDF)
            </a>
        </div>

        {{-- Step Indicator --}}
        <div class="flex items-center justify-between mb-8 relative">
            <div class="absolute top-5 left-0 right-0 h-0.5 bg-slate-200 -z-0"></div>
            <div class="absolute top-5 left-0 h-0.5 bg-blue-500 -z-0 transition-all duration-500" style="width: {{ $totalSteps > 1 ? (($currentStep - 1) / ($totalSteps - 1)) * 100 : 100 }}%"></div>

            @foreach($stepLabels as $i => $label)
            <div class="flex flex-col items-center relative z-10">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all
                    {{ $currentStep > $i + 1 ? 'bg-blue-500 text-white' : ($currentStep === $i + 1 ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-white border-2 border-slate-300 text-slate-400') }}">
                    @if($currentStep > $i + 1)
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <span class="text-xs mt-2 font-medium {{ $currentStep === $i + 1 ? 'text-blue-600' : 'text-slate-400' }}">{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Form Card --}}
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6">
                <h2 class="text-white font-bold text-xl">Step {{ $currentStep }}: @php
                    $kind = $this->stepKindAt($currentStep);
                    $stepTitle = match ($kind) {
                        'personal' => 'Personal Information',
                        'payment' => 'Membership fee',
                        'identity' => 'Identity & Address',
                        'employment' => 'Employment & Next of Kin',
                        'document' => 'Application Document',
                        default => 'Application',
                    };
                @endphp{{ $stepTitle }}</h2>
            </div>

            <div class="p-8">
                @error('form')
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                {{-- Step: Personal Info --}}
                @if($this->stepKindAt($currentStep) === 'personal')
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name *</label>
                        <input wire:model="name" type="text" placeholder="Ahmed Al-Saudi" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Email Address *</label>
                        <input wire:model="email" type="email" placeholder="you@example.com" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-400 @enderror">
                        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Password *</label>
                            <input wire:model="password" type="password" placeholder="Min. 8 characters" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-400 @enderror">
                            @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Confirm Password *</label>
                            <input wire:model="password_confirmation" type="password" placeholder="Repeat password" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Membership fee (bank transfer) --}}
                @if($this->stepKindAt($currentStep) === 'payment')
                <div class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-sm font-semibold text-slate-800 mb-1">Application fee</p>
                        <p class="text-2xl font-bold text-blue-700">SAR {{ number_format(\App\Models\Setting::membershipApplicationFee(), 2) }}</p>
                        <p class="text-xs text-slate-500 mt-2">Transfer this amount before submitting your application. Fees are credited to the fund’s cash account (not the pooled master fund).</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                        {{ \App\Models\Setting::membershipApplicationFeeBankInstructions() }}
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Your transfer reference / note *</label>
                        <input wire:model="membership_fee_transfer_reference" type="text" placeholder="As shown on your bank receipt" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono @error('membership_fee_transfer_reference') border-red-400 @enderror">
                        @error('membership_fee_transfer_reference')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        <p class="text-xs text-slate-500 mt-1">Use the same reference you entered on the bank transfer so we can match your payment.</p>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input wire:model="membership_fee_acknowledged" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-700">I confirm I have transferred SAR {{ number_format(\App\Models\Setting::membershipApplicationFee(), 2) }} to the fund bank account above.</span>
                    </label>
                    @error('membership_fee_acknowledged')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
                </div>
                @endif

                {{-- Step: Identity & Address --}}
                @if($this->stepKindAt($currentStep) === 'identity')
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Application type *</label>
                        <select wire:model="application_type" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('application_type') border-red-400 @enderror">
                            <option value="new">New</option>
                            <option value="resume">Resume</option>
                            <option value="renew">Renew</option>
                        </select>
                        @error('application_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Gender</label>
                            <select wire:model="gender" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">—</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            @error('gender')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Marital status</label>
                            <select wire:model="marital_status" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">—</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                                <option value="other">Other</option>
                            </select>
                            @error('marital_status')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">National ID *</label>
                            <input wire:model="national_id" type="text" placeholder="1XXXXXXXXX" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('national_id') border-red-400 @enderror">
                            @error('national_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Date of Birth *</label>
                            <input wire:model="date_of_birth" type="date" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('date_of_birth') border-red-400 @enderror">
                            @error('date_of_birth')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Full Address *</label>
                        <textarea wire:model="address" rows="3" placeholder="Street, Building, District..." class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('address') border-red-400 @enderror resize-none"></textarea>
                        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">City *</label>
                        <input wire:model="city" type="text" placeholder="Riyadh" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('city') border-red-400 @enderror">
                        @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Contact numbers</p>
                    <div class="grid sm:grid-cols-1 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Mobile phone *</label>
                            <input wire:model="mobile_phone" type="tel" placeholder="+966 50 000 0000" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('mobile_phone') border-red-400 @enderror">
                            <p class="text-xs text-slate-500 mt-1">Used for SMS and WhatsApp and saved on your account.</p>
                            @error('mobile_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Home phone</label>
                            <input wire:model="home_phone" type="tel" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('home_phone') border-red-400 @enderror">
                            @error('home_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Work phone</label>
                            <input wire:model="work_phone" type="tel" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('work_phone') border-red-400 @enderror">
                            @error('work_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Work place</label>
                        <input wire:model="work_place" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('work_place') border-red-400 @enderror">
                        @error('work_place')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Residency place</label>
                        <input wire:model="residency_place" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('residency_place') border-red-400 @enderror">
                        @error('residency_place')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Bank account number *</label>
                            <input wire:model="bank_account_number" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono @error('bank_account_number') border-red-400 @enderror">
                            @error('bank_account_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">IBAN *</label>
                            <input wire:model="iban" type="text" dir="ltr" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono @error('iban') border-red-400 @enderror">
                            @error('iban')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Membership date</label>
                        <input wire:model="membership_date" type="date" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('membership_date') border-red-400 @enderror">
                        @error('membership_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                @endif

                {{-- Step: Employment & Next of Kin --}}
                @if($this->stepKindAt($currentStep) === 'employment')
                <div class="space-y-5">
                    <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Employment (Optional)</p>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Occupation</label>
                            <input wire:model="occupation" type="text" placeholder="Engineer" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Employer</label>
                            <input wire:model="employer" type="text" placeholder="ARAMCO" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Monthly Income (SAR)</label>
                        <input wire:model="monthly_income" type="number" step="0.01" placeholder="10000.00" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide mb-4">Next of Kin *</p>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name *</label>
                                <input wire:model="next_of_kin_name" type="text" placeholder="Mohammed Al-Saudi" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('next_of_kin_name') border-red-400 @enderror">
                                @error('next_of_kin_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Phone Number *</label>
                                <input wire:model="next_of_kin_phone" type="tel" placeholder="+966 50 000 0000" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('next_of_kin_phone') border-red-400 @enderror">
                                @error('next_of_kin_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Step: Document Upload --}}
                @if($this->stepKindAt($currentStep) === 'document')
                <div class="space-y-6">
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Application Form Upload</p>
                                <p class="text-xs text-slate-500 mt-1">Upload a signed copy of the membership application form. Accepted formats: PDF, JPG, PNG (max 5MB). This step is optional but recommended.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-3">Signed Application Form (Optional)</label>
                        <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-slate-300 rounded-2xl cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-all">
                            <input wire:model="application_form" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                            @if($application_form)
                            <div class="text-center">
                                <svg class="w-10 h-10 text-blue-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-semibold text-blue-600">File selected</p>
                                <p class="text-xs text-slate-500 mt-1">{{ $application_form->getClientOriginalName() }}</p>
                            </div>
                            @else
                            <div class="text-center">
                                <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm font-semibold text-slate-600">Click to upload</p>
                                <p class="text-xs text-slate-400 mt-1">PDF, JPG, PNG up to 5MB</p>
                            </div>
                            @endif
                        </label>
                        @error('application_form')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div wire:loading wire:target="application_form" class="flex items-center gap-2 text-sm text-blue-600">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Uploading file...
                    </div>
                </div>
                @endif

                {{-- Navigation Buttons --}}
                <div class="flex justify-between mt-8 pt-6 border-t border-slate-100">
                    @if($currentStep > 1)
                    <button wire:click="previousStep" type="button" class="flex items-center gap-2 px-6 py-3 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Previous
                    </button>
                    @else
                    <div></div>
                    @endif

                    @if($currentStep < $totalSteps)
                    <button wire:click="nextStep" type="button" class="flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors">
                        Next
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    @else
                    <button
                        wire:click="submit"
                        wire:loading.attr="disabled"
                        type="button"
                        class="flex items-center gap-2 px-8 py-3 text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl transition-all shadow-md disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="submit">Submit Application</span>
                        <span wire:loading wire:target="submit" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Submitting...
                        </span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
