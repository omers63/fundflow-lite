<div class="space-y-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
    <div class="rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs dark:border-blue-500/30 dark:bg-blue-500/10">
        <p class="font-semibold text-blue-900 dark:text-blue-200 mb-1">Need a starter file?</p>
        <p>
            Download a ready sample with 20 varied rows (including optional fields):
            <a
                href="{{ route('downloads.membership-application-import-sample') }}"
                class="font-semibold text-blue-700 underline hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200"
            >
                membership-applications-sample-20.csv
            </a>
        </p>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-xs dark:border-white/10 dark:bg-white/5">
        <p>
            Use a UTF-8 CSV with a <strong class="text-gray-950 dark:text-white">header row</strong>.
            Column names must match exactly; order can be anything.
        </p>
    </div>

    <div class="rounded-lg border border-gray-200 overflow-hidden dark:border-white/10">
        <div class="bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
            Required Columns
        </div>
        <table class="w-full text-xs">
            <thead class="bg-gray-50/60 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 w-56">Column</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ([
                    'name' => 'Applicant full name',
                    'email' => 'Login email (must be unique for a new user)',
                    'national_id' => 'National / ID number',
                    'date_of_birth' => 'Date of birth (example: YYYY-MM-DD)',
                    'city' => 'City',
                    'address' => 'Full address (quote if it contains commas)',
                    'mobile_phone' => 'Mobile number (used for SMS / WhatsApp)',
                    'bank_account_number' => 'Bank account number',
                    'iban' => 'IBAN',
                    'next_of_kin_name' => 'Next of kin full name',
                    'next_of_kin_phone' => 'Next of kin phone number',
                ] as $col => $hint)
                    <tr>
                        <td class="px-3 py-2 align-top">
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $col }}</code>
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $hint }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="rounded-lg border border-gray-200 overflow-hidden dark:border-white/10">
        <div class="bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
            Optional Columns
        </div>
        <table class="w-full text-xs">
            <thead class="bg-gray-50/60 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 w-56">Column</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ([
                    'password' => 'If 8+ characters, overrides the default password provided in the modal',
                    'application_type' => 'new, resume, or renew (blank defaults to new)',
                    'gender' => 'male, female, other',
                    'marital_status' => 'single, married, divorced, widowed, other',
                    'membership_date' => 'Membership date',
                    'home_phone' => 'Home phone',
                    'work_phone' => 'Work phone',
                    'work_place' => 'Work place',
                    'residency_place' => 'Residency place',
                    'occupation' => 'Occupation',
                    'employer' => 'Employer',
                    'monthly_income' => 'Monthly income (numeric, >= 0)',
                ] as $col => $hint)
                    <tr>
                        <td class="px-3 py-2 align-top">
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $col }}</code>
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $hint }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="rounded-lg border border-gray-200 overflow-hidden dark:border-white/10">
        <div class="bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
            Row Handling Rules
        </div>
        <table class="w-full text-xs">
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                <tr>
                    <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 w-56">Password fallback</td>
                    <td class="px-3 py-2">Empty or short <code class="font-mono text-[11px]">password</code> uses the default password set in this modal.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Existing application</td>
                    <td class="px-3 py-2">If email already has a membership application, the row is <strong class="text-gray-800 dark:text-gray-200">skipped</strong>.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Existing member</td>
                    <td class="px-3 py-2">If email belongs to an existing member, the row <strong class="text-gray-800 dark:text-gray-200">fails</strong> with an error.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
