<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\SmsImportSession;
use App\Models\SmsImportTemplate;
use App\Models\SmsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuickPostWorkflowService
{
    public function __construct(
        private readonly AccountingService $accounting,
    ) {
    }

    /**
     * Create a bank transaction and run the full posting workflow.
     *
     * @param  array{
     *     bank_id: int,
     *     transaction_date: string,
     *     amount: float,
     *     transaction_type: 'credit'|'debit',
     *     reference: ?string,
     *     description: ?string,
     *     member_id: int,
     *     apply?: 'both'|'contribution'|'repayment',
     *     raw_data?: array<string, mixed>,
     * } $data
     * @return array{tx: BankTransaction, steps: array<int, array{label: string, done: bool, note: string}>}
     */
    public function runForBank(array $data): array
    {
        $member = Member::with(['user', 'dependents.user', 'dependents.accounts'])->findOrFail($data['member_id']);
        $bank = Bank::findOrFail($data['bank_id']);

        return DB::transaction(function () use ($data, $member, $bank) {
            $steps = [];

            // ── Step 1: Create the bank transaction ───────────────────────
            $session = $this->getOrCreateManualBankSession($bank);
            $tx = BankTransaction::create([
                'bank_id' => $bank->id,
                'import_session_id' => $session->id,
                'transaction_date' => $data['transaction_date'],
                'amount' => $data['amount'],
                'transaction_type' => $data['transaction_type'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'member_id' => $member->id,
                'is_duplicate' => false,
                'raw_data' => array_merge(
                    ['source' => 'quick_post', 'created_by_user_id' => auth()->id()],
                    is_array($data['raw_data'] ?? null) ? $data['raw_data'] : []
                ),
            ]);
            $steps[] = ['label' => 'Transaction created', 'done' => true, 'note' => "#{$tx->id} · SAR " . number_format($data['amount'], 2)];

            // ── Step 2: Post to Master Bank (Cash) Account ─────────────────
            $this->accounting->postBankTransactionToCash($tx, $member);
            $steps[] = [
                'label' => 'Posted to Master Cash Account',
                'done' => true,
                'note' => ucfirst($data['transaction_type']) . ' · SAR ' . number_format($data['amount'], 2),
            ];

            if ($data['transaction_type'] === 'debit') {
                $steps[] = ['label' => 'Debit detected — no further allocation', 'done' => true, 'note' => 'Workflow complete for debit transactions'];

                return ['tx' => $tx, 'steps' => $steps];
            }

            // ── Credit path only ───────────────────────────────────────────
            // Step 3 already done inside postBankTransactionToCash (member cash credited).
            $steps[] = ['label' => "Posted to {$member->user->name}'s Cash Account", 'done' => true, 'note' => 'Member cash balance updated'];

            // ── Step 4: Fund dependents' cash accounts from parent's cash ──
            $dependents = $member->dependents()->with(['user', 'accounts'])->get();
            if ($dependents->isNotEmpty()) {
                $funded = 0;
                foreach ($dependents as $dep) {
                    $needed = $this->neededCashForDependent($dep);
                    if ($needed > 0) {
                        try {
                            $this->accounting->fundDependentCashAccount($member, $dep, $needed, 'Auto-funded from quick-post');
                            $funded++;
                        } catch (\RuntimeException) {
                            // parent ran out of cash — skip remaining dependents
                            break;
                        }
                    }
                }
                $steps[] = [
                    'label' => 'Dependent allocations',
                    'done' => true,
                    'note' => $funded > 0 ? "Funded {$funded} dependent(s)" : 'No allocation needed',
                ];
            } else {
                $steps[] = ['label' => 'Dependent allocations', 'done' => true, 'note' => 'No dependents'];
            }

            // ── Step 5: Settle contributions & installments (member + dependents) ──
            $allMembers = $dependents->prepend($member);
            $apply = (string) ($data['apply'] ?? 'both');
            $contributions = 0;
            $installments = 0;

            foreach ($allMembers as $m) {
                if (in_array($apply, ['both', 'contribution'], true)) {
                    $contributions += $this->settleContribution($m);
                }
                if (in_array($apply, ['both', 'repayment'], true)) {
                    $installments += $this->settleInstallments($m);
                }
            }

            if (in_array($apply, ['both', 'contribution'], true)) {
                $steps[] = [
                    'label' => 'Contributions settled',
                    'done' => true,
                    'note' => "{$contributions} contribution(s) applied",
                ];
            }
            if (in_array($apply, ['both', 'repayment'], true)) {
                $steps[] = [
                    'label' => 'Loan installments settled',
                    'done' => true,
                    'note' => "{$installments} installment(s) applied",
                ];
            }

            return ['tx' => $tx, 'steps' => $steps];
        });
    }

    /**
     * Create an SMS transaction and run the full posting workflow.
     *
     * @param  array{
     *     bank_id: ?int,
     *     transaction_date: string,
     *     amount: float,
     *     transaction_type: 'credit'|'debit',
     *     reference: ?string,
     *     raw_sms: string,
     *     member_id: int,
     *     apply?: 'both'|'contribution'|'repayment',
     *     raw_data?: array<string, mixed>,
     * } $data
     * @return array{tx: SmsTransaction, steps: array<int, array{label: string, done: bool, note: string}>}
     */
    public function runForSms(array $data): array
    {
        $member = Member::with(['user', 'dependents.user', 'dependents.accounts'])->findOrFail($data['member_id']);

        return DB::transaction(function () use ($data, $member) {
            $steps = [];

            // ── Step 1: Create the SMS transaction ────────────────────────
            $session = $this->getOrCreateManualSmsSession($data['bank_id'] ?? null);
            $tx = SmsTransaction::create([
                'bank_id' => $data['bank_id'] ?? null,
                'import_session_id' => $session->id,
                'transaction_date' => $data['transaction_date'],
                'amount' => $data['amount'],
                'transaction_type' => $data['transaction_type'],
                'reference' => $data['reference'] ?? null,
                'raw_sms' => $data['raw_sms'] ?? '(manual)',
                'member_id' => $member->id,
                'is_duplicate' => false,
                'raw_data' => array_merge(
                    ['source' => 'quick_post', 'created_by_user_id' => auth()->id()],
                    is_array($data['raw_data'] ?? null) ? $data['raw_data'] : []
                ),
            ]);
            $steps[] = ['label' => 'SMS transaction created', 'done' => true, 'note' => "#{$tx->id} · SAR " . number_format($data['amount'], 2)];

            // ── Step 2 ─────────────────────────────────────────────────────
            $this->accounting->postSmsTransactionToCash($tx, $member);
            $steps[] = [
                'label' => 'Posted to Master Cash Account',
                'done' => true,
                'note' => ucfirst($data['transaction_type']) . ' · SAR ' . number_format($data['amount'], 2),
            ];

            if ($data['transaction_type'] === 'debit') {
                $steps[] = ['label' => 'Debit detected — no further allocation', 'done' => true, 'note' => 'Workflow complete for debit transactions'];

                return ['tx' => $tx, 'steps' => $steps];
            }

            $steps[] = ['label' => "Posted to {$member->user->name}'s Cash Account", 'done' => true, 'note' => 'Member cash balance updated'];

            // Steps 4–5 same as bank path ──────────────────────────────────
            $dependents = $member->dependents()->with(['user', 'accounts'])->get();
            if ($dependents->isNotEmpty()) {
                $funded = 0;
                foreach ($dependents as $dep) {
                    $needed = $this->neededCashForDependent($dep);
                    if ($needed > 0) {
                        try {
                            $this->accounting->fundDependentCashAccount($member, $dep, $needed, 'Auto-funded from quick-post');
                            $funded++;
                        } catch (\RuntimeException) {
                            break;
                        }
                    }
                }
                $steps[] = ['label' => 'Dependent allocations', 'done' => true, 'note' => $funded > 0 ? "Funded {$funded} dependent(s)" : 'No allocation needed'];
            } else {
                $steps[] = ['label' => 'Dependent allocations', 'done' => true, 'note' => 'No dependents'];
            }

            $allMembers = $dependents->prepend($member);
            $apply = (string) ($data['apply'] ?? 'both');
            $contributions = 0;
            $installments = 0;
            foreach ($allMembers as $m) {
                if (in_array($apply, ['both', 'contribution'], true)) {
                    $contributions += $this->settleContribution($m);
                }
                if (in_array($apply, ['both', 'repayment'], true)) {
                    $installments += $this->settleInstallments($m);
                }
            }

            if (in_array($apply, ['both', 'contribution'], true)) {
                $steps[] = ['label' => 'Contributions settled', 'done' => true, 'note' => "{$contributions} contribution(s) applied"];
            }
            if (in_array($apply, ['both', 'repayment'], true)) {
                $steps[] = ['label' => 'Loan installments settled', 'done' => true, 'note' => "{$installments} installment(s) applied"];
            }

            return ['tx' => $tx, 'steps' => $steps];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getOrCreateManualBankSession(Bank $bank): BankImportSession
    {
        $template = $bank->defaultTemplate() ?? $bank->importTemplates()->first();
        if ($template === null) {
            throw new \RuntimeException("Bank \"{$bank->name}\" has no import templates configured.");
        }

        return BankImportSession::firstOrCreate(
            ['bank_id' => $bank->id, 'filename' => '__manual_entry__'],
            [
                'template_id' => $template->id,
                'imported_by' => auth()->id(),
                'file_path' => 'manual',
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );
    }

    private function getOrCreateManualSmsSession(?int $bankId): SmsImportSession
    {
        $template = SmsImportTemplate::when($bankId, fn($q) => $q->where('bank_id', $bankId))
            ->where('is_default', true)->first()
            ?? SmsImportTemplate::when($bankId, fn($q) => $q->where('bank_id', $bankId))->first()
            ?? SmsImportTemplate::first();

        if ($template === null) {
            throw new \RuntimeException('No SMS import templates found. Create at least one under Banking → SMS → Templates.');
        }

        return SmsImportSession::firstOrCreate(
            [
                'bank_id' => $bankId,
                'filename' => '__manual_entry__',
            ],
            [
                'template_id' => $template->id,
                'imported_by' => auth()->id(),
                'file_path' => 'manual',
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );
    }

    /** How much cash the dependent still needs to cover dues this month. */
    private function neededCashForDependent(Member $dep): float
    {
        $cashBalance = (float) ($dep->cashAccount()?->balance ?? 0);
        $due = $this->monthlyDueForMember($dep);

        return max(0, $due - $cashBalance);
    }

    /** Sum of contribution + pending installments due this month. */
    private function monthlyDueForMember(Member $member): float
    {
        $due = (float) $member->monthly_contribution_amount;

        $installmentDue = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')
            ->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year)
            ->sum('amount');

        return $due + (float) $installmentDue;
    }

    /**
     * Debit cash and create a Contribution for the member for the current month
     * if not already paid.
     */
    private function settleContribution(Member $member): int
    {
        if ($member->isExemptFromContributions()) {
            return 0;
        }

        $now = Carbon::now();
        $already = Contribution::where('member_id', $member->id)
            ->where('month', $now->month)
            ->where('year', $now->year)
            ->exists();

        if ($already) {
            return 0;
        }

        $amount = (float) $member->monthly_contribution_amount;
        if ($amount <= 0) {
            return 0;
        }

        $cash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->first();

        $cycle = app(ContributionCycleService::class);
        $lateFees = app(LateFeeService::class);
        $deadline = $cycle->deadline((int) $now->month, (int) $now->year);
        $days = $lateFees->daysPastDue($deadline, $now);
        $lateFee = $lateFees->contributionLateFeeForDays($days);
        $isLate = $days >= 1;
        $required = $amount + $lateFee;

        if (!$cash || (float) $cash->balance < $required) {
            return 0;
        }

        Contribution::create([
            'member_id' => $member->id,
            'amount' => $amount,
            'month' => $now->month,
            'year' => $now->year,
            'paid_at' => $now,
            'payment_method' => Contribution::PAYMENT_METHOD_CASH_ACCOUNT,
            'reference_number' => 'QP-' . now()->format('YmdHis'),
            'is_late' => $isLate,
            'late_fee_amount' => $lateFee > 0 ? $lateFee : null,
        ]);

        return 1;
    }

    /**
     * Pay all due + overdue installments for this month where cash is sufficient.
     */
    private function settleInstallments(Member $member): int
    {
        $cash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->first();

        if (!$cash) {
            return 0;
        }

        $pending = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id)
            ->where('status', 'active'))
            ->whereIn('status', ['pending', 'overdue'])
            ->where('due_date', '<=', now()->endOfMonth())
            ->orderBy('due_date')
            ->get();

        $cycleSvc = app(ContributionCycleService::class);
        $lateFees = app(LateFeeService::class);
        $settled = 0;
        foreach ($pending as $installment) {
            if (!$installment instanceof LoanInstallment) {
                continue;
            }
            $due = $installment->due_date;
            $m = (int) $due->month;
            $y = (int) $due->year;
            $deadline = $cycleSvc->deadline($m, $y);
            $days = $lateFees->daysPastDue($deadline, now());
            $lateFee = $lateFees->repaymentLateFeeForDays($days);
            $isLate = $days >= 1;
            $need = (float) $installment->amount + $lateFee;

            if ((float) $cash->fresh()->balance < $need) {
                break;
            }

            $this->accounting->debitCashForRepayment($member, $installment, $lateFee);

            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'is_late' => $isLate,
                'late_fee_amount' => $lateFee > 0 ? $lateFee : null,
            ]);

            $settled++;
        }

        return $settled;
    }
}
