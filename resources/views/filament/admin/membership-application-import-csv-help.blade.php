<div class="space-y-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
    <p>
        Use a UTF-8 CSV with a <strong class="text-gray-950 dark:text-white">header row</strong>.
        Column names must match the keys below; column order can be anything.
    </p>

    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-950 dark:text-white mb-2">
            Required columns
        </h3>
        <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2 list-none pl-0">
            @foreach ([
                'name' => 'Applicant full name',
                'email' => 'Login email (unique per new user)',
                'national_id' => 'National / ID number',
                'date_of_birth' => 'Date of birth (e.g. YYYY-MM-DD)',
                'city' => 'City',
                'address' => 'Full address (quote if it contains commas)',
                'mobile_phone' => 'Mobile (SMS / WhatsApp)',
                'next_of_kin_name' => 'Next of kin name',
                'next_of_kin_phone' => 'Next of kin phone',
            ] as $col => $hint)
                <li class="flex gap-2">
                    <code class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $col }}</code>
                    <span class="text-gray-500 dark:text-gray-500">{{ $hint }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-950 dark:text-white mb-2">
            Optional columns
        </h3>
        <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2 list-none pl-0">
            @foreach ([
                'password' => 'If ≥ 8 characters, overrides the default password below',
                'application_type' => 'new, resume, or renew (omit → defaults to new)',
                'gender' => 'See application form options (e.g. male, female, other)',
                'marital_status' => 'See application form options (e.g. single, married)',
                'membership_date' => 'Membership date',
                'home_phone' => 'Home phone',
                'work_phone' => 'Work phone',
                'work_place' => 'Work place',
                'residency_place' => 'Residency place',
                'occupation' => 'Occupation',
                'employer' => 'Employer',
                'monthly_income' => 'Monthly income (numeric)',
                'bank_account_number' => 'Bank account number',
                'iban' => 'IBAN',
            ] as $col => $hint)
                <li class="flex gap-2">
                    <code class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $col }}</code>
                    <span class="text-gray-500 dark:text-gray-500">{{ $hint }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-xs dark:border-white/10 dark:bg-white/5">
        <p class="font-medium text-gray-800 dark:text-gray-200 mb-1.5">How rows are handled</p>
        <ul class="list-disc space-y-1 pl-4 text-gray-600 dark:text-gray-400">
            <li>Empty or short <code class="font-mono text-[0.7rem]">password</code> → the default password you set in this form is used.</li>
            <li>Email already has a membership application → row is <strong class="text-gray-800 dark:text-gray-200">skipped</strong>.</li>
            <li>Email belongs to an existing member → row <strong class="text-gray-800 dark:text-gray-200">fails</strong> with an error.</li>
        </ul>
    </div>
</div>
