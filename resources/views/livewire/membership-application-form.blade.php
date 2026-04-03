<div class="min-h-screen bg-gradient-to-br from-slate-50 to-emerald-50 py-12 px-4">
    <div class="max-w-2xl mx-auto">

        @if($submitted)
        {{-- Success State --}}
        <div class="bg-white rounded-3xl shadow-xl p-12 text-center">
            <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">Application Submitted!</h2>
            <p class="text-slate-500 mb-2">Your membership application has been received and is now under review.</p>
            <p class="text-slate-500 text-sm mb-8">You will be notified via email, SMS, and WhatsApp once your application is processed.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('application.status') }}" class="bg-emerald-600 text-white font-semibold px-6 py-3 rounded-xl hover:bg-emerald-700 transition-colors">
                    Check Application Status
                </a>
                <a href="{{ route('home') }}" class="bg-slate-100 text-slate-700 font-semibold px-6 py-3 rounded-xl hover:bg-slate-200 transition-colors">
                    Back to Home
                </a>
            </div>
        </div>
        @else

        {{-- Progress Header --}}
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-slate-800 mb-2">Apply for Membership</h1>
            <p class="text-slate-500">Complete the form below to join the FundFlow family fund.</p>
        </div>

        {{-- Step Indicator --}}
        <div class="flex items-center justify-between mb-8 relative">
            <div class="absolute top-5 left-0 right-0 h-0.5 bg-slate-200 -z-0"></div>
            <div class="absolute top-5 left-0 h-0.5 bg-emerald-500 -z-0 transition-all duration-500" style="width: {{ (($currentStep - 1) / ($totalSteps - 1)) * 100 }}%"></div>

            @php $steps = ['Personal Info', 'Identity', 'Employment', 'Document']; @endphp
            @foreach($steps as $i => $label)
            <div class="flex flex-col items-center relative z-10">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all
                    {{ $currentStep > $i + 1 ? 'bg-emerald-500 text-white' : ($currentStep === $i + 1 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-white border-2 border-slate-300 text-slate-400') }}">
                    @if($currentStep > $i + 1)
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <span class="text-xs mt-2 font-medium {{ $currentStep === $i + 1 ? 'text-emerald-600' : 'text-slate-400' }}">{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Form Card --}}
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6">
                @php $stepTitles = ['Personal Information', 'Identity & Address', 'Employment & Next of Kin', 'Application Document']; @endphp
                <h2 class="text-white font-bold text-xl">Step {{ $currentStep }}: {{ $stepTitles[$currentStep - 1] }}</h2>
            </div>

            <div class="p-8">
                {{-- Step 1: Personal Info --}}
                @if($currentStep === 1)
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name *</label>
                        <input wire:model="name" type="text" placeholder="Ahmed Al-Saudi" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('name') border-red-400 @enderror">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Email Address *</label>
                        <input wire:model="email" type="email" placeholder="you@example.com" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('email') border-red-400 @enderror">
                        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Password *</label>
                            <input wire:model="password" type="password" placeholder="Min. 8 characters" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('password') border-red-400 @enderror">
                            @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Confirm Password *</label>
                            <input wire:model="password_confirmation" type="password" placeholder="Repeat password" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Step 2: Identity & Address --}}
                @if($currentStep === 2)
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Application type *</label>
                        <select wire:model="application_type" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('application_type') border-red-400 @enderror">
                            <option value="new">New</option>
                            <option value="resume">Resume</option>
                            <option value="renew">Renew</option>
                        </select>
                        @error('application_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Gender</label>
                            <select wire:model="gender" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">—</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            @error('gender')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Marital status</label>
                            <select wire:model="marital_status" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
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
                            <input wire:model="national_id" type="text" placeholder="1XXXXXXXXX" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('national_id') border-red-400 @enderror">
                            @error('national_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Date of Birth *</label>
                            <input wire:model="date_of_birth" type="date" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('date_of_birth') border-red-400 @enderror">
                            @error('date_of_birth')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Full Address *</label>
                        <textarea wire:model="address" rows="3" placeholder="Street, Building, District..." class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('address') border-red-400 @enderror resize-none"></textarea>
                        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">City *</label>
                        <input wire:model="city" type="text" placeholder="Riyadh" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('city') border-red-400 @enderror">
                        @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Contact numbers</p>
                    <div class="grid sm:grid-cols-1 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Mobile phone *</label>
                            <input wire:model="mobile_phone" type="tel" placeholder="+966 50 000 0000" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('mobile_phone') border-red-400 @enderror">
                            <p class="text-xs text-slate-500 mt-1">Used for SMS and WhatsApp and saved on your account.</p>
                            @error('mobile_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Home phone</label>
                            <input wire:model="home_phone" type="tel" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('home_phone') border-red-400 @enderror">
                            @error('home_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Work phone</label>
                            <input wire:model="work_phone" type="tel" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('work_phone') border-red-400 @enderror">
                            @error('work_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Work place</label>
                        <input wire:model="work_place" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('work_place') border-red-400 @enderror">
                        @error('work_place')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Residency place</label>
                        <input wire:model="residency_place" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('residency_place') border-red-400 @enderror">
                        @error('residency_place')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Bank account number</label>
                            <input wire:model="bank_account_number" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono @error('bank_account_number') border-red-400 @enderror">
                            @error('bank_account_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">IBAN</label>
                            <input wire:model="iban" type="text" dir="ltr" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono @error('iban') border-red-400 @enderror">
                            @error('iban')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Membership date</label>
                        <input wire:model="membership_date" type="date" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('membership_date') border-red-400 @enderror">
                        @error('membership_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                @endif

                {{-- Step 3: Employment & Next of Kin --}}
                @if($currentStep === 3)
                <div class="space-y-5">
                    <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide">Employment (Optional)</p>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Occupation</label>
                            <input wire:model="occupation" type="text" placeholder="Engineer" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Employer</label>
                            <input wire:model="employer" type="text" placeholder="ARAMCO" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Monthly Income (SAR)</label>
                        <input wire:model="monthly_income" type="number" step="0.01" placeholder="10000.00" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <p class="text-xs text-slate-400 uppercase font-semibold tracking-wide mb-4">Next of Kin *</p>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name *</label>
                                <input wire:model="next_of_kin_name" type="text" placeholder="Mohammed Al-Saudi" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('next_of_kin_name') border-red-400 @enderror">
                                @error('next_of_kin_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Phone Number *</label>
                                <input wire:model="next_of_kin_phone" type="tel" placeholder="+966 50 000 0000" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('next_of_kin_phone') border-red-400 @enderror">
                                @error('next_of_kin_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Step 4: Document Upload --}}
                @if($currentStep === 4)
                <div class="space-y-6">
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-slate-300 rounded-2xl cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition-all">
                            <input wire:model="application_form" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                            @if($application_form)
                            <div class="text-center">
                                <svg class="w-10 h-10 text-emerald-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-semibold text-emerald-600">File selected</p>
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

                    <div wire:loading wire:target="application_form" class="flex items-center gap-2 text-sm text-emerald-600">
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
                    <button wire:click="nextStep" type="button" class="flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors">
                        Next
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    @else
                    <button
                        wire:click="submit"
                        wire:loading.attr="disabled"
                        type="button"
                        class="flex items-center gap-2 px-8 py-3 text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 rounded-xl transition-all shadow-md disabled:opacity-50"
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
