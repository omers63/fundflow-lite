<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Contribution extends Model
{
    use SoftDeletes;

    /** Recorded via Finance → Contributions create form (not user-picked). */
    public const PAYMENT_METHOD_ADMIN = 'admin';

    /** Monthly cycle / member cash deduction before fund credit. */
    public const PAYMENT_METHOD_CASH_ACCOUNT = 'cash_account';

    /** Import flow: explicit cash-to-fund posting chain using paid_at as ledger timestamp. */
    public const PAYMENT_METHOD_IMPORT_CSV = 'import_csv';

    /** Synthetic rows shown in contribution table for paid loan installments. */
    public const PAYMENT_METHOD_LOAN_REPAYMENT = 'loan_repayment';

    /** @return array<string, string> */
    public static function paymentMethodOptions(): array
    {
        return [
            'cash_account' => 'Cash account (cycle)',
            'admin' => 'Admin entry',
            'import_csv' => 'CSV import',
            'loan_repayment' => 'Loan repayment',
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online',
        ];
    }

    public static function paymentMethodLabel(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return static::paymentMethodOptions()[$method] ?? $method;
    }

    protected static function booted(): void
    {
        static::creating(function (Contribution $contribution): void {
            $member = Member::query()->find((int) $contribution->member_id);
            if ($member && $member->isExemptFromContributions()) {
                throw ValidationException::withMessages([
                    'member_id' => ['This member has an active loan with pending repayments. Contributions are not applied; keep funds in the member cash account until installments are paid.'],
                ]);
            }

            if (
                static::activePeriodExists(
                    (int) $contribution->member_id,
                    (int) $contribution->month,
                    (int) $contribution->year,
                )
            ) {
                throw static::duplicateCycleValidationException(
                    (int) $contribution->month,
                    (int) $contribution->year,
                );
            }

            if (
                static::hasPaidScheduledLoanInstallmentOnActiveLoan(
                    (int) $contribution->member_id,
                    (int) $contribution->month,
                    (int) $contribution->year,
                )
            ) {
                throw static::scheduledRepaymentPrecludesContributionValidationException(
                    (int) $contribution->month,
                    (int) $contribution->year,
                );
            }
        });

        static::updating(function (Contribution $contribution): void {
            if ($contribution->isDirty('member_id')) {
                $member = Member::query()->find((int) $contribution->member_id);
                if ($member && $member->isExemptFromContributions()) {
                    throw ValidationException::withMessages([
                        'member_id' => ['This member has an active loan with pending repayments. Contributions are not applied; keep funds in the member cash account until installments are paid.'],
                    ]);
                }
            }

            if (!$contribution->isDirty(['member_id', 'month', 'year'])) {
                return;
            }

            if (
                static::activePeriodExists(
                    (int) $contribution->member_id,
                    (int) $contribution->month,
                    (int) $contribution->year,
                    (int) $contribution->getKey(),
                )
            ) {
                throw static::duplicateCycleValidationException(
                    (int) $contribution->month,
                    (int) $contribution->year,
                );
            }

            if (
                static::hasPaidScheduledLoanInstallmentOnActiveLoan(
                    (int) $contribution->member_id,
                    (int) $contribution->month,
                    (int) $contribution->year,
                )
            ) {
                throw static::scheduledRepaymentPrecludesContributionValidationException(
                    (int) $contribution->month,
                    (int) $contribution->year,
                );
            }
        });
    }

    /** User-facing copy when a second row would violate the one-record-per-cycle rule. */
    public static function duplicateCycleMessage(int $month, int $year): string
    {
        $period = date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;

        return "Duplicate contribution: this member already has a record for {$period}. Choose a different month/year, or edit the existing contribution.";
    }

    public static function duplicateCycleValidationException(int $month, int $year): ValidationException
    {
        $message = static::duplicateCycleMessage($month, $year);

        return ValidationException::withMessages([
            'year' => [$message],
        ]);
    }

    /**
     * True when a paid installment on an **active** loan falls in this calendar month/year (due_date).
     * Used so a cycle cannot hold both a contribution and a scheduled loan repayment; early-settled /
     * completed loans are ignored so members can contribute again in those calendar periods.
     */
    public static function hasPaidScheduledLoanInstallmentOnActiveLoan(int $memberId, int $month, int $year): bool
    {
        return LoanInstallment::query()
            ->whereHas('loan', fn($q) => $q
                ->where('member_id', $memberId)
                ->where('status', 'active'))
            ->where('status', 'paid')
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->exists();
    }

    /** User-facing copy when a contribution would duplicate a cycle that already has a scheduled repayment. */
    public static function scheduledRepaymentPrecludesContributionMessage(int $month, int $year): string
    {
        $period = date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;

        return "This member already has a loan repayment recorded for {$period} on an active loan. A cycle may have either a contribution or a repayment, not both.";
    }

    public static function scheduledRepaymentPrecludesContributionValidationException(int $month, int $year): ValidationException
    {
        return ValidationException::withMessages([
            'year' => [static::scheduledRepaymentPrecludesContributionMessage($month, $year)],
        ]);
    }

    /** Contribution already stored for this member/cycle — cannot apply a repayment for the same period. */
    public static function contributionExistsBlocksRepaymentMessage(int $month, int $year): string
    {
        $period = date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;

        return "A contribution already exists for {$period}. Only one of contribution or loan repayment is allowed per cycle.";
    }

    /** Non-trashed row for the same member + calendar period (matches partial DB unique when present). */
    public static function activePeriodExists(int $memberId, int $month, int $year, ?int $exceptId = null): bool
    {
        return static::query()
            ->when($exceptId !== null, fn($q) => $q->whereKeyNot($exceptId))
            ->where('member_id', $memberId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    protected $fillable = [
        'member_id',
        'amount',
        'month',
        'year',
        'paid_at',
        'payment_method',
        'reference_number',
        'notes',
        'is_late',
        'late_fee_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'is_late' => 'boolean',
            'late_fee_amount' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
