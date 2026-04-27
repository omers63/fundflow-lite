<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\DependentAllocationChange;
use App\Models\DependentCashAllocation;
use App\Models\DirectMessage;
use App\Models\FundTier;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\MemberCommunicationPreference;
use App\Models\MemberRequest;
use App\Models\MemberSubscriptionFee;
use App\Models\MembershipApplication;
use App\Models\MonthlyStatement;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\SmsImportSession;
use App\Models\SmsImportTemplate;
use App\Models\SmsTransaction;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\UserWidgetPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComprehensiveFeatureExerciseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCoreSettings();
            [$admin, $ops] = $this->seedStaffUsers();
            $this->seedLoanAndFundTiers();

            $users = $this->seedMemberUsers();
            $apps = $this->seedMembershipApplications($users, $admin);
            $members = $this->seedMembers($users, $apps);

            $this->seedAccountsAndLedger($members, $admin);
            $this->seedCommunicationPreferences($users);
            $this->seedUserWidgetPreferences($users);

            $this->seedContributions($members);
            $this->seedLoansInstallmentsAndDisbursements($members, $admin);
            $this->seedMonthlyStatements($members);
            $this->seedMemberRequestsAndDependentFlows($members, $admin);
            $this->seedMessagingAndSupport($users, $members);
            $this->seedNotificationLogs($users);
            $this->seedAnnualSubscriptionFees($members, $admin);
            $this->seedBankAndSmsImportArtifacts($admin, $members);
        });
    }

    private function seedCoreSettings(): void
    {
        $settings = [
            'loan.settlement_threshold_pct' => '0.16',
            'loan.min_fund_balance' => '6000',
            'loan.eligibility_months' => '12',
            'loan.max_borrow_multiplier' => '2',
            'loan.default_grace_cycles' => '2',
            'membership.max_pending_public' => '25',
            'membership.application_fee_new' => '500',
            'membership.application_fee_resume' => '300',
            'membership.application_fee_renew' => '300',
            'contribution.cycle_start_day' => '6',
            'delinquency.consecutive_miss_threshold' => '3',
            'delinquency.total_miss_threshold' => '15',
            'delinquency.total_miss_lookback_months' => '60',
            'statement.brand_name' => 'FundFlow',
            'statement.tagline' => 'Member Fund Management',
            'statement.footer_disclaimer' => 'System-generated statement for demo validation.',
            'statement.signature_line' => 'FundFlow Administration',
            'subscription.annual_fee' => '1200',
            'communication.channel.in_app' => '1',
            'communication.channel.email' => '1',
            'communication.channel.sms' => '1',
            'communication.channel.whatsapp' => '1',
            'email_template.global.signature_line.en' => 'FundFlow Member Care',
            'email_template.global.signature_line.ar' => 'خدمة أعضاء فندفلو',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }

    private function seedStaffUsers(): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@fundflow.sa'],
            [
                'name' => 'FundFlow Admin',
                'phone' => '+966500000000',
                'role' => 'admin',
                'status' => 'approved',
                'preferred_locale' => 'en',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $ops = User::updateOrCreate(
            ['email' => 'ops@fundflow.sa'],
            [
                'name' => 'FundFlow Operations',
                'phone' => '+966500000001',
                'role' => 'admin',
                'status' => 'approved',
                'preferred_locale' => 'ar',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        return [$admin, $ops];
    }

    private function seedMemberUsers(): array
    {
        $records = [
            'primary' => ['email' => 'member.primary@fundflow.sa', 'name' => 'Primary Member', 'locale' => 'en', 'status' => 'approved'],
            'dependent' => ['email' => 'member.dependent@fundflow.sa', 'name' => 'Dependent Member', 'locale' => 'ar', 'status' => 'approved'],
            'independent' => ['email' => 'member.independent@fundflow.sa', 'name' => 'Independent Member', 'locale' => 'en', 'status' => 'approved'],
            'suspended' => ['email' => 'member.suspended@fundflow.sa', 'name' => 'Suspended Member', 'locale' => 'ar', 'status' => 'approved'],
            'terminated' => ['email' => 'member.terminated@fundflow.sa', 'name' => 'Terminated Member', 'locale' => 'en', 'status' => 'approved'],
            'pendingApplicant' => ['email' => 'applicant.pending@fundflow.sa', 'name' => 'Pending Applicant', 'locale' => 'ar', 'status' => 'pending'],
            'rejectedApplicant' => ['email' => 'applicant.rejected@fundflow.sa', 'name' => 'Rejected Applicant', 'locale' => 'en', 'status' => 'rejected'],
        ];

        $users = [];
        foreach ($records as $key => $record) {
            $users[$key] = User::updateOrCreate(
                ['email' => $record['email']],
                [
                    'name' => $record['name'],
                    'phone' => '+96655' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'role' => 'member',
                    'status' => $record['status'],
                    'preferred_locale' => $record['locale'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        return $users;
    }

    private function seedMembershipApplications(array $users, User $admin): array
    {
        $today = now();
        $base = [
            'application_type' => 'new',
            'gender' => 'male',
            'marital_status' => 'single',
            'date_of_birth' => '1990-05-10',
            'address' => 'Riyadh Demo District',
            'city' => 'Riyadh',
            'home_phone' => null,
            'work_phone' => null,
            'mobile_phone' => '+966550000000',
            'occupation' => 'Engineer',
            'employer' => 'FundFlow Demo Co',
            'work_place' => 'Riyadh',
            'residency_place' => 'Riyadh',
            'monthly_income' => 15000,
            'bank_account_number' => '1234567890',
            'iban' => 'SA0380000000608010167519',
            'membership_date' => $today->copy()->subYears(2)->toDateString(),
            'next_of_kin_name' => 'Demo Kin',
            'next_of_kin_phone' => '+966511111111',
            'membership_fee_amount' => 500,
            'membership_fee_transfer_reference' => 'REF-DEMO-001',
            'membership_fee_posted_at' => $today->copy()->subMonths(2),
        ];

        $applications = [];
        $applications['primary'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['primary']->id],
            array_merge($base, [
                'national_id' => '2000000001',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subMonths(23),
            ])
        );
        $applications['dependent'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['dependent']->id],
            array_merge($base, [
                'gender' => 'female',
                'marital_status' => 'married',
                'national_id' => '2000000002',
                'membership_date' => $today->copy()->subMonths(14)->toDateString(),
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subMonths(13),
            ])
        );
        $applications['independent'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['independent']->id],
            array_merge($base, [
                'national_id' => '2000000003',
                'membership_date' => $today->copy()->subYears(3)->toDateString(),
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subYears(3)->addDay(),
            ])
        );
        $applications['suspended'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['suspended']->id],
            array_merge($base, [
                'national_id' => '2000000004',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subYears(1),
            ])
        );
        $applications['terminated'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['terminated']->id],
            array_merge($base, [
                'national_id' => '2000000005',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subYears(4),
            ])
        );
        $applications['pendingApplicant'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['pendingApplicant']->id],
            array_merge($base, [
                'national_id' => '2000000006',
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])
        );
        $applications['rejectedApplicant'] = MembershipApplication::updateOrCreate(
            ['user_id' => $users['rejectedApplicant']->id],
            array_merge($base, [
                'national_id' => '2000000007',
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => $today->copy()->subDays(10),
                'rejection_reason' => 'Demo rejection to validate rejection flows',
            ])
        );

        return $applications;
    }

    private function seedMembers(array $users, array $apps): array
    {
        $members = [];
        $members['primary'] = Member::updateOrCreate(
            ['user_id' => $users['primary']->id],
            [
                'parent_id' => null,
                'member_number' => 'M0001',
                'monthly_contribution_amount' => 2000,
                'joined_at' => $apps['primary']->membership_date ?? now()->subYears(2)->toDateString(),
                'status' => 'active',
                'late_contributions_count' => 1,
                'late_contributions_amount' => 150,
                'late_repayment_count' => 0,
                'late_repayment_amount' => 0,
                'delinquency_suspended_at' => null,
            ]
        );
        $members['dependent'] = Member::updateOrCreate(
            ['user_id' => $users['dependent']->id],
            [
                'parent_id' => $members['primary']->id,
                'member_number' => 'M0002',
                'monthly_contribution_amount' => 1000,
                'joined_at' => $apps['dependent']->membership_date ?? now()->subMonths(12)->toDateString(),
                'status' => 'active',
            ]
        );
        $members['independent'] = Member::updateOrCreate(
            ['user_id' => $users['independent']->id],
            [
                'member_number' => 'M0003',
                'parent_id' => null,
                'monthly_contribution_amount' => 1500,
                'joined_at' => $apps['independent']->membership_date ?? now()->subYears(3)->toDateString(),
                'status' => 'active',
            ]
        );
        $members['suspended'] = Member::updateOrCreate(
            ['user_id' => $users['suspended']->id],
            [
                'member_number' => 'M0004',
                'parent_id' => null,
                'monthly_contribution_amount' => 500,
                'joined_at' => $apps['suspended']->membership_date ?? now()->subYears(1)->toDateString(),
                'status' => 'suspended',
                'late_contributions_count' => 4,
                'late_repayment_count' => 2,
                'delinquency_suspended_at' => now()->subDays(40),
            ]
        );
        $members['terminated'] = Member::updateOrCreate(
            ['user_id' => $users['terminated']->id],
            [
                'member_number' => 'M0005',
                'parent_id' => null,
                'monthly_contribution_amount' => 500,
                'joined_at' => $apps['terminated']->membership_date ?? now()->subYears(4)->toDateString(),
                'status' => 'terminated',
            ]
        );

        return $members;
    }

    private function seedAccountsAndLedger(array $members, User $admin): void
    {
        $masterCash = Account::firstOrCreate(
            ['slug' => 'master_cash'],
            ['name' => 'Cash Account', 'type' => Account::TYPE_MASTER_CASH, 'balance' => 0, 'is_active' => true]
        );
        $masterFund = Account::firstOrCreate(
            ['slug' => 'master_fund'],
            ['name' => 'Fund Account', 'type' => Account::TYPE_MASTER_FUND, 'balance' => 0, 'is_active' => true]
        );

        foreach ($members as $key => $member) {
            $cash = Account::updateOrCreate(
                ['slug' => "member_{$member->id}_cash"],
                [
                    'name' => "{$member->member_number} Cash",
                    'type' => Account::TYPE_MEMBER_CASH,
                    'member_id' => $member->id,
                    'loan_id' => null,
                    'is_active' => true,
                    'balance' => $key === 'suspended' ? 200 : 8500,
                ]
            );

            $fund = Account::updateOrCreate(
                ['slug' => "member_{$member->id}_fund"],
                [
                    'name' => "{$member->member_number} Fund",
                    'type' => Account::TYPE_MEMBER_FUND,
                    'member_id' => $member->id,
                    'loan_id' => null,
                    'is_active' => true,
                    'balance' => $key === 'suspended' ? 1200 : 9000,
                ]
            );

            $this->recordLedgerEntry($cash, $member, $admin, 10000, 'credit', 'Seed opening cash balance');
            $this->recordLedgerEntry($fund, $member, $admin, 6000, 'credit', 'Seed opening fund balance');
        }

        $masterCash->balance = 1000000;
        $masterCash->save();
        $masterFund->balance = 2000000;
        $masterFund->save();
    }

    private function recordLedgerEntry(Account $account, ?Member $member, User $admin, float $amount, string $entryType, string $description): void
    {
        $sourceType = get_class($admin);
        $sourceId = $admin->id;
        $key = sha1($account->id . '|' . $entryType . '|' . $amount . '|' . $description);

        $tx = AccountTransaction::firstOrCreate(
            ['description' => "[seed:$key] $description"],
            [
                'account_id' => $account->id,
                'amount' => $amount,
                'entry_type' => $entryType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'member_id' => $member?->id,
                'posted_by' => $admin->id,
                'transacted_at' => now()->subDays(30),
            ]
        );

        if ($tx->wasRecentlyCreated) {
            $delta = $entryType === 'credit' ? $amount : -$amount;
            $account->forceFill([
                'balance' => (float) $account->balance + $delta,
            ])->save();
        }
    }

    private function seedCommunicationPreferences(array $users): void
    {
        foreach ($users as $user) {
            foreach (NotificationPreferenceService::CATEGORIES as $type => $meta) {
                MemberCommunicationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'notification_type' => $type],
                    ['channels' => $meta['defaults']]
                );
            }
        }
    }

    private function seedUserWidgetPreferences(array $users): void
    {
        foreach (['primary', 'dependent', 'independent'] as $key) {
            UserWidgetPreference::updateOrCreate(
                ['user_id' => $users[$key]->id, 'panel' => 'member', 'page' => 'dashboard'],
                [
                    'visible_widgets' => [
                        'member-welcome-banner-widget',
                        'member-status-widget',
                        'member-stats-overview',
                        'loan-repayment-progress-widget',
                    ]
                ]
            );
        }
    }

    private function seedContributions(array $members): void
    {
        $periods = [
            now()->copy()->subMonths(3),
            now()->copy()->subMonths(2),
            now()->copy()->subMonths(1),
        ];

        foreach (['primary', 'dependent', 'independent', 'suspended'] as $key) {
            $member = $members[$key];
            foreach ($periods as $index => $period) {
                Contribution::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'month' => (int) $period->month,
                        'year' => (int) $period->year,
                    ],
                    [
                        'amount' => (float) $member->monthly_contribution_amount,
                        'paid_at' => $period->copy()->day(12),
                        'payment_method' => Contribution::PAYMENT_METHOD_CASH_ACCOUNT,
                        'reference_number' => strtoupper("CNTR-{$member->member_number}-{$period->format('Ym')}"),
                        'notes' => 'Seeded contribution',
                        'is_late' => $key === 'suspended' && $index > 0,
                        'late_fee_amount' => $key === 'suspended' && $index > 0 ? 75 : 0,
                    ]
                );
            }
        }
    }

    private function seedLoansInstallmentsAndDisbursements(array $members, User $admin): void
    {
        $tier1 = LoanTier::query()->where('tier_number', 1)->firstOrFail();
        $tier2 = LoanTier::query()->where('tier_number', 2)->firstOrFail();
        $fund1 = FundTier::forLoanTier($tier1->id);
        $fund2 = FundTier::forLoanTier($tier2->id);

        $activeLoan = Loan::updateOrCreate(
            ['member_id' => $members['primary']->id, 'purpose' => 'Home renovation'],
            [
                'loan_tier_id' => $tier2->id,
                'fund_tier_id' => $fund2?->id,
                'queue_position' => 1,
                'amount_requested' => 40000,
                'amount_approved' => 38000,
                'amount_disbursed' => 24000,
                'member_portion' => 6000,
                'master_portion' => 32000,
                'repaid_to_master' => 7000,
                'purpose' => 'Home renovation',
                'installments_count' => 16,
                'status' => 'active',
                'applied_at' => now()->subMonths(6),
                'approved_at' => now()->subMonths(6)->addDays(2),
                'approved_by_id' => $admin->id,
                'disbursed_at' => now()->subMonths(5),
                'due_date' => now()->addMonths(11)->toDateString(),
                'guarantor_member_id' => $members['independent']->id,
                'witness1_name' => 'Witness One',
                'witness1_phone' => '+966500010001',
                'witness2_name' => 'Witness Two',
                'witness2_phone' => '+966500010002',
                'exempted_month' => (int) now()->subMonths(5)->month,
                'exempted_year' => (int) now()->subMonths(5)->year,
                'first_repayment_month' => (int) now()->subMonths(3)->month,
                'first_repayment_year' => (int) now()->subMonths(3)->year,
                'settlement_threshold' => 0.1600,
                'late_repayment_count' => 1,
                'late_repayment_amount' => 120,
            ]
        );

        LoanDisbursement::updateOrCreate(
            ['loan_id' => $activeLoan->id, 'amount' => 24000],
            [
                'member_portion' => 6000,
                'master_portion' => 18000,
                'disbursed_at' => now()->subMonths(5),
                'disbursed_by_id' => $admin->id,
                'notes' => 'Initial partial disbursement',
            ]
        );

        for ($i = 1; $i <= 6; $i++) {
            $isPaid = $i <= 3;
            $isOverdue = $i === 4;
            LoanInstallment::updateOrCreate(
                ['loan_id' => $activeLoan->id, 'installment_number' => $i],
                [
                    'amount' => 2375,
                    'due_date' => now()->subMonths(6 - $i)->startOfMonth()->addDays(4)->toDateString(),
                    'paid_at' => $isPaid ? now()->subMonths(6 - $i)->startOfMonth()->addDays(6) : null,
                    'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                    'is_late' => $i === 3,
                    'late_fee_amount' => $i === 3 ? 120 : 0,
                    'paid_by_guarantor' => false,
                ]
            );
        }

        Loan::updateOrCreate(
            ['member_id' => $members['dependent']->id, 'purpose' => 'Education'],
            [
                'loan_tier_id' => $tier1->id,
                'fund_tier_id' => $fund1?->id,
                'queue_position' => 2,
                'amount_requested' => 15000,
                'purpose' => 'Education',
                'status' => 'pending',
                'installments_count' => 12,
                'applied_at' => now()->subDays(7),
            ]
        );

        Loan::updateOrCreate(
            ['member_id' => $members['independent']->id, 'purpose' => 'Vehicle purchase'],
            [
                'loan_tier_id' => $tier2->id,
                'fund_tier_id' => $fund2?->id,
                'queue_position' => 3,
                'amount_requested' => 30000,
                'amount_approved' => 30000,
                'amount_disbursed' => 30000,
                'member_portion' => 8000,
                'master_portion' => 22000,
                'repaid_to_master' => 22000,
                'purpose' => 'Vehicle purchase',
                'status' => 'completed',
                'installments_count' => 14,
                'applied_at' => now()->subYears(1),
                'approved_at' => now()->subYears(1)->addDays(3),
                'approved_by_id' => $admin->id,
                'disbursed_at' => now()->subYears(1)->addWeek(),
                'settled_at' => now()->subMonths(1),
                'due_date' => now()->subMonths(1)->toDateString(),
                'settlement_threshold' => 0.1600,
            ]
        );
    }

    private function seedMonthlyStatements(array $members): void
    {
        $period = now()->subMonth()->format('Y-m');
        foreach (['primary', 'dependent', 'independent', 'suspended'] as $key) {
            $member = $members[$key];
            MonthlyStatement::upsertForMember($member->id, $period, [
                'opening_balance' => 10000,
                'total_contributions' => (float) $member->monthly_contribution_amount,
                'total_repayments' => $key === 'primary' ? 2375 : 0,
                'closing_balance' => 10000 + (float) $member->monthly_contribution_amount - ($key === 'primary' ? 2375 : 0),
                'generated_at' => now()->subDays(10),
                'details' => [
                    'contributions' => [['amount' => (float) $member->monthly_contribution_amount]],
                    'repayments' => $key === 'primary' ? [['amount' => 2375]] : [],
                ],
                'notified_at' => now()->subDays(9),
            ]);
        }
    }

    private function seedMemberRequestsAndDependentFlows(array $members, User $admin): void
    {
        MemberRequest::updateOrCreate(
            ['requester_member_id' => $members['dependent']->id, 'type' => MemberRequest::TYPE_REQUEST_INDEPENDENCE],
            [
                'status' => MemberRequest::STATUS_PENDING,
                'payload' => ['reason' => 'Seeded request to validate workflow'],
            ]
        );

        MemberRequest::updateOrCreate(
            ['requester_member_id' => $members['primary']->id, 'type' => MemberRequest::TYPE_DEPENDENT_ALLOCATION],
            [
                'status' => MemberRequest::STATUS_APPROVED,
                'payload' => ['dependent_member_id' => $members['dependent']->id, 'requested_amount' => 1200],
                'admin_note' => 'Approved in seed data',
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now()->subDays(20),
            ]
        );

        DependentAllocationChange::updateOrCreate(
            [
                'parent_member_id' => $members['primary']->id,
                'dependent_member_id' => $members['dependent']->id,
                'new_amount' => 1200,
            ],
            [
                'old_amount' => 1000,
                'changed_by_user_id' => $admin->id,
                'note' => 'Seeded allocation increase',
            ]
        );

        DependentCashAllocation::updateOrCreate(
            [
                'parent_member_id' => $members['primary']->id,
                'dependent_member_id' => $members['dependent']->id,
                'allocation_month' => (int) now()->month,
                'allocation_year' => (int) now()->year,
            ],
            ['amount' => 1200]
        );
    }

    private function seedMessagingAndSupport(array $users, array $members): void
    {
        $root = DirectMessage::updateOrCreate(
            ['from_user_id' => $users['primary']->id, 'to_user_id' => $users['dependent']->id, 'subject' => 'Contribution clarification'],
            [
                'body' => 'Please review the latest contribution allocation.',
                'attachments' => [],
                'read_at' => now()->subDay(),
            ]
        );

        DirectMessage::updateOrCreate(
            ['from_user_id' => $users['dependent']->id, 'to_user_id' => $users['primary']->id, 'parent_id' => $root->id],
            [
                'subject' => 'Re: Contribution clarification',
                'body' => 'Acknowledged, I reviewed the update.',
                'attachments' => [],
                'read_at' => now()->subHours(20),
            ]
        );

        SupportRequest::updateOrCreate(
            ['user_id' => $users['primary']->id, 'subject' => 'Demo support request'],
            [
                'member_id' => $members['primary']->id,
                'category' => SupportRequest::CATEGORY_CONTRIBUTION_QUERY,
                'message' => 'Seeded support ticket to validate support workflows.',
            ]
        );
    }

    private function seedNotificationLogs(array $users): void
    {
        foreach (['primary', 'dependent', 'independent', 'suspended'] as $key) {
            NotificationLog::updateOrCreate(
                ['user_id' => $users[$key]->id, 'channel' => 'mail', 'subject' => 'Seeded Notification'],
                [
                    'body' => "Seeded notification for {$users[$key]->email}",
                    'status' => 'sent',
                    'sent_at' => now()->subHours(6),
                ]
            );
        }
    }

    private function seedAnnualSubscriptionFees(array $members, User $admin): void
    {
        MemberSubscriptionFee::updateOrCreate(
            ['member_id' => $members['primary']->id, 'year' => (int) now()->year],
            [
                'amount' => 1200,
                'paid_at' => now()->subMonths(2),
                'notes' => 'Seeded annual fee payment',
                'posted_by' => $admin->id,
            ]
        );
    }

    private function seedBankAndSmsImportArtifacts(User $admin, array $members): void
    {
        $bank = Bank::updateOrCreate(
            ['code' => 'ALRAJHI'],
            [
                'name' => 'Al Rajhi Bank',
                'swift_code' => 'RJHISARI',
                'account_number' => '001234567890',
                'is_active' => true,
            ]
        );

        $bankTemplate = BankImportTemplate::updateOrCreate(
            ['bank_id' => $bank->id, 'name' => 'Default CSV Template'],
            [
                'is_default' => true,
                'delimiter' => ',',
                'encoding' => 'UTF-8',
                'has_header' => true,
                'skip_rows' => 1,
                'date_column' => 'date',
                'date_format' => 'Y-m-d',
                'amount_type' => 'single',
                'amount_column' => 'amount',
                'optional_columns' => [
                    ['key' => 'reference', 'column' => 'reference'],
                    ['key' => 'description', 'column' => 'description'],
                ],
                'duplicate_match_fields' => ['date', 'amount', 'reference'],
                'duplicate_date_tolerance' => 0,
            ]
        );

        $bankSession = BankImportSession::updateOrCreate(
            ['bank_id' => $bank->id, 'filename' => 'demo-transactions.csv'],
            [
                'template_id' => $bankTemplate->id,
                'imported_by' => $admin->id,
                'file_path' => 'imports/bank/demo-transactions.csv',
                'status' => 'completed',
                'total_rows' => 2,
                'imported_count' => 2,
                'duplicate_count' => 0,
                'error_count' => 0,
                'completed_at' => now()->subDays(5),
            ]
        );

        BankTransaction::updateOrCreate(
            ['import_session_id' => $bankSession->id, 'reference' => 'BNK-DEMO-001'],
            [
                'bank_id' => $bank->id,
                'member_id' => $members['primary']->id,
                'transaction_date' => now()->subDays(5)->toDateString(),
                'amount' => 2500,
                'running_balance' => 52500,
                'transaction_type' => 'credit',
                'description' => 'Seeded bank credit',
                'raw_data' => ['seed' => true],
                'posted_at' => now()->subDays(4),
                'posted_by' => $admin->id,
            ]
        );

        $smsTemplate = SmsImportTemplate::updateOrCreate(
            ['name' => 'Demo SMS Template'],
            [
                'bank_id' => $bank->id,
                'is_default' => true,
                'delimiter' => ',',
                'encoding' => 'UTF-8',
                'has_header' => true,
                'skip_rows' => 1,
                'sms_column' => 'message',
                'date_column' => null,
                'date_format' => 'Y-m-d H:i:s',
                'amount_pattern' => '/SAR\\s*(?P<amount>[\\d,]+\\.?\\d*)/i',
                'date_pattern' => null,
                'date_pattern_format' => null,
                'reference_pattern' => '/Ref[:\\s]+(?P<reference>[A-Z0-9-]+)/i',
                'credit_keywords' => ['credited', 'deposit'],
                'debit_keywords' => ['debited', 'withdraw'],
                'default_transaction_type' => 'credit',
                'duplicate_match_fields' => ['date', 'amount', 'reference'],
                'duplicate_date_tolerance' => 1,
                'member_match_pattern' => '/Member[:\\s]+(?P<member>M\\d+)/',
                'member_match_field' => 'member_number',
            ]
        );

        $smsSession = SmsImportSession::updateOrCreate(
            ['template_id' => $smsTemplate->id, 'filename' => 'demo-sms.csv'],
            [
                'bank_id' => $bank->id,
                'imported_by' => $admin->id,
                'file_path' => 'imports/sms/demo-sms.csv',
                'status' => 'completed',
                'total_rows' => 1,
                'imported_count' => 1,
                'duplicate_count' => 0,
                'error_count' => 0,
                'completed_at' => now()->subDays(3),
            ]
        );

        SmsTransaction::updateOrCreate(
            ['import_session_id' => $smsSession->id, 'reference' => 'SMS-DEMO-001'],
            [
                'bank_id' => $bank->id,
                'member_id' => $members['dependent']->id,
                'transaction_date' => now()->subDays(3)->toDateString(),
                'amount' => 1000,
                'transaction_type' => 'credit',
                'raw_sms' => 'Member M0002 credited SAR 1,000 Ref SMS-DEMO-001',
                'raw_data' => ['seed' => true],
                'posted_at' => now()->subDays(2),
                'posted_by' => $admin->id,
            ]
        );
    }

    private function seedLoanAndFundTiers(): void
    {
        $loanTier1 = LoanTier::updateOrCreate(
            ['tier_number' => 1],
            ['label' => 'Tier 1', 'min_amount' => 5000, 'max_amount' => 20000, 'min_monthly_installment' => 500, 'is_active' => true]
        );
        $loanTier2 = LoanTier::updateOrCreate(
            ['tier_number' => 2],
            ['label' => 'Tier 2', 'min_amount' => 20001, 'max_amount' => 50000, 'min_monthly_installment' => 1000, 'is_active' => true]
        );

        FundTier::updateOrCreate(
            ['tier_number' => 0],
            ['label' => 'Emergency', 'loan_tier_id' => null, 'percentage' => 10, 'is_active' => true]
        );
        FundTier::updateOrCreate(
            ['tier_number' => 1],
            ['label' => 'Fund Tier 1', 'loan_tier_id' => $loanTier1->id, 'percentage' => 45, 'is_active' => true]
        );
        FundTier::updateOrCreate(
            ['tier_number' => 2],
            ['label' => 'Fund Tier 2', 'loan_tier_id' => $loanTier2->id, 'percentage' => 45, 'is_active' => true]
        );
    }
}
