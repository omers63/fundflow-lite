<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MemberRequest;
use App\Models\MembershipApplication;
use App\Models\MonthlyStatement;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySeedHealth extends Command
{
    protected $signature = 'seed:verify
                            {--json : Output a machine-readable JSON report}
                            {--strict : Fail when warning checks fail}';

    protected $description = 'Verify seeded database health (counts, referential integrity, status distribution).';

    public function handle(): int
    {
        $checks = array_merge(
            $this->countChecks(),
            $this->referentialIntegrityChecks(),
            $this->statusDistributionChecks(),
            $this->scenarioMarkerChecks(),
        );

        $summary = [
            'total' => count($checks),
            'passed' => collect($checks)->where('passed', true)->count(),
            'failed' => collect($checks)->where('passed', false)->count(),
            'warnings' => collect($checks)->where('severity', 'warning')->where('passed', false)->count(),
            'errors' => collect($checks)->where('severity', 'error')->where('passed', false)->count(),
        ];

        $strict = (bool) $this->option('strict');
        $hardFailures = $strict
            ? ($summary['failed'] > 0)
            : ($summary['errors'] > 0);

        $report = [
            'ok' => !$hardFailures,
            'strict' => $strict,
            'environment' => app()->environment(),
            'connection' => DB::connection()->getName(),
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'checks' => $checks,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line("Seed verification report ({$report['environment']} / {$report['connection']})");
            $this->line("Passed: {$summary['passed']}  Failed: {$summary['failed']}  Errors: {$summary['errors']}  Warnings: {$summary['warnings']}");
            foreach ($checks as $check) {
                $status = $check['passed'] ? 'PASS' : 'FAIL';
                $this->line("[{$status}] {$check['name']} | expected={$check['expected']} actual={$check['actual']}");
            }
        }

        return $hardFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countChecks(): array
    {
        return [
            $this->buildCheck('count.users_min', 'error', User::count() >= 8, '>= 8', User::count()),
            $this->buildCheck('count.members_min', 'error', Member::count() >= 5, '>= 5', Member::count()),
            $this->buildCheck('count.membership_applications_min', 'error', MembershipApplication::count() >= 7, '>= 7', MembershipApplication::count()),
            $this->buildCheck('count.contributions_min', 'error', Contribution::count() >= 8, '>= 8', Contribution::count()),
            $this->buildCheck('count.loans_min', 'error', Loan::count() >= 3, '>= 3', Loan::count()),
            $this->buildCheck('count.loan_installments_min', 'error', LoanInstallment::count() >= 6, '>= 6', LoanInstallment::count()),
            $this->buildCheck('count.accounts_min', 'error', Account::count() >= 10, '>= 10', Account::count()),
            $this->buildCheck('count.account_transactions_min', 'warning', AccountTransaction::count() >= 4, '>= 4', AccountTransaction::count()),
            $this->buildCheck('count.monthly_statements_min', 'warning', MonthlyStatement::count() >= 4, '>= 4', MonthlyStatement::count()),
            $this->buildCheck('count.notification_logs_min', 'warning', NotificationLog::count() >= 4, '>= 4', NotificationLog::count()),
            $this->buildCheck('count.member_requests_min', 'warning', MemberRequest::count() >= 2, '>= 2', MemberRequest::count()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function referentialIntegrityChecks(): array
    {
        $orphanMembers = Member::query()->whereDoesntHave('user')->count();
        $orphanApps = MembershipApplication::query()->whereDoesntHave('user')->count();
        $orphanContributions = Contribution::query()->whereDoesntHave('member')->count();
        $orphanLoans = Loan::query()->whereDoesntHave('member')->count();
        $orphanInstallments = LoanInstallment::query()->whereDoesntHave('loan')->count();
        $orphanAccountsByMember = Account::query()->whereNotNull('member_id')->whereDoesntHave('member')->count();
        $orphanAccountsByLoan = Account::query()->whereNotNull('loan_id')->whereDoesntHave('loan')->count();
        $orphanTransactions = AccountTransaction::query()->whereDoesntHave('account')->count();
        $invalidTransactionSources = AccountTransaction::query()
            ->where(function ($q): void {
                $q->whereNull('source_type')->orWhereNull('source_id');
            })
            ->count();

        return [
            $this->buildCheck('ref.members.user_exists', 'error', $orphanMembers === 0, '0 orphan members', $orphanMembers),
            $this->buildCheck('ref.membership_applications.user_exists', 'error', $orphanApps === 0, '0 orphan membership applications', $orphanApps),
            $this->buildCheck('ref.contributions.member_exists', 'error', $orphanContributions === 0, '0 orphan contributions', $orphanContributions),
            $this->buildCheck('ref.loans.member_exists', 'error', $orphanLoans === 0, '0 orphan loans', $orphanLoans),
            $this->buildCheck('ref.loan_installments.loan_exists', 'error', $orphanInstallments === 0, '0 orphan installments', $orphanInstallments),
            $this->buildCheck('ref.accounts.member_exists', 'error', $orphanAccountsByMember === 0, '0 orphan member accounts', $orphanAccountsByMember),
            $this->buildCheck('ref.accounts.loan_exists', 'error', $orphanAccountsByLoan === 0, '0 orphan loan accounts', $orphanAccountsByLoan),
            $this->buildCheck('ref.account_transactions.account_exists', 'error', $orphanTransactions === 0, '0 orphan account transactions', $orphanTransactions),
            $this->buildCheck('ref.account_transactions.source_present', 'warning', $invalidTransactionSources === 0, '0 transactions with null source', $invalidTransactionSources),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusDistributionChecks(): array
    {
        $userStatuses = User::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $appStatuses = MembershipApplication::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $memberStatuses = Member::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $loanStatuses = Loan::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            $this->buildCheck('status.users.approved_present', 'error', ((int) ($userStatuses['approved'] ?? 0)) >= 1, '>= 1 approved', (int) ($userStatuses['approved'] ?? 0)),
            $this->buildCheck('status.users.pending_present', 'warning', ((int) ($userStatuses['pending'] ?? 0)) >= 1, '>= 1 pending', (int) ($userStatuses['pending'] ?? 0)),
            $this->buildCheck('status.users.rejected_present', 'warning', ((int) ($userStatuses['rejected'] ?? 0)) >= 1, '>= 1 rejected', (int) ($userStatuses['rejected'] ?? 0)),

            $this->buildCheck('status.membership_applications.approved_present', 'error', ((int) ($appStatuses['approved'] ?? 0)) >= 1, '>= 1 approved', (int) ($appStatuses['approved'] ?? 0)),
            $this->buildCheck('status.membership_applications.pending_present', 'warning', ((int) ($appStatuses['pending'] ?? 0)) >= 1, '>= 1 pending', (int) ($appStatuses['pending'] ?? 0)),
            $this->buildCheck('status.membership_applications.rejected_present', 'warning', ((int) ($appStatuses['rejected'] ?? 0)) >= 1, '>= 1 rejected', (int) ($appStatuses['rejected'] ?? 0)),

            $this->buildCheck('status.members.active_present', 'error', ((int) ($memberStatuses['active'] ?? 0)) >= 1, '>= 1 active', (int) ($memberStatuses['active'] ?? 0)),
            $this->buildCheck('status.members.suspended_present', 'warning', ((int) ($memberStatuses['suspended'] ?? 0)) >= 1, '>= 1 suspended', (int) ($memberStatuses['suspended'] ?? 0)),
            $this->buildCheck('status.members.terminated_present', 'warning', ((int) ($memberStatuses['terminated'] ?? 0)) >= 1, '>= 1 terminated', (int) ($memberStatuses['terminated'] ?? 0)),

            $this->buildCheck('status.loans.pending_present', 'warning', ((int) ($loanStatuses['pending'] ?? 0)) >= 1, '>= 1 pending', (int) ($loanStatuses['pending'] ?? 0)),
            $this->buildCheck('status.loans.active_present', 'error', ((int) ($loanStatuses['active'] ?? 0)) >= 1, '>= 1 active', (int) ($loanStatuses['active'] ?? 0)),
            $this->buildCheck('status.loans.completed_present', 'warning', ((int) ($loanStatuses['completed'] ?? 0)) >= 1, '>= 1 completed', (int) ($loanStatuses['completed'] ?? 0)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioMarkerChecks(): array
    {
        $requiredUsers = [
            'admin@fundflow.sa',
            'member.primary@fundflow.sa',
            'member.dependent@fundflow.sa',
            'member.independent@fundflow.sa',
            'member.suspended@fundflow.sa',
            'member.terminated@fundflow.sa',
        ];

        $missingUsers = collect($requiredUsers)
            ->reject(fn(string $email): bool => User::query()->where('email', $email)->exists())
            ->values()
            ->all();

        $requiredMembers = ['M0001', 'M0002', 'M0003', 'M0004', 'M0005'];
        $missingMembers = collect($requiredMembers)
            ->reject(fn(string $number): bool => Member::query()->where('member_number', $number)->exists())
            ->values()
            ->all();

        return [
            $this->buildCheck(
                'scenario.required_users_present',
                'error',
                $missingUsers === [],
                'all required seeded emails present',
                $missingUsers === [] ? 'ok' : implode(', ', $missingUsers)
            ),
            $this->buildCheck(
                'scenario.required_members_present',
                'error',
                $missingMembers === [],
                'all required member numbers present',
                $missingMembers === [] ? 'ok' : implode(', ', $missingMembers)
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheck(string $name, string $severity, bool $passed, mixed $expected, mixed $actual): array
    {
        return [
            'name' => $name,
            'severity' => $severity,
            'passed' => $passed,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }
}
