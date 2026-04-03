<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'member_id',
        'loan_tier_id',
        'fund_tier_id',
        'queue_position',
        'amount_requested',
        'amount_approved',
        'member_portion',
        'master_portion',
        'repaid_to_master',
        'purpose',
        'installments_count',
        'status',
        'applied_at',
        'approved_at',
        'approved_by_id',
        'disbursed_at',
        'settled_at',
        'due_date',
        'guarantor_member_id',
        'guarantor_released_at',
        'witness1_name',
        'witness1_phone',
        'witness2_name',
        'witness2_phone',
        'exempted_month',
        'exempted_year',
        'first_repayment_month',
        'first_repayment_year',
        'settlement_threshold',
        'late_repayment_count',
        'late_repayment_amount',
        'rejection_reason',
        'cancellation_reason',
        'is_emergency',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'amount_approved' => 'decimal:2',
            'member_portion' => 'decimal:2',
            'master_portion' => 'decimal:2',
            'repaid_to_master' => 'decimal:2',
            'late_repayment_amount' => 'decimal:2',
            'settlement_threshold' => 'decimal:4',
            'is_emergency' => 'boolean',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'settled_at' => 'datetime',
            'guarantor_released_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loanTier(): BelongsTo
    {
        return $this->belongsTo(LoanTier::class);
    }

    public function fundTier(): BelongsTo
    {
        return $this->belongsTo(FundTier::class);
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function account(): ?Account
    {
        return Account::where('loan_id', $this->id)->where('type', Account::TYPE_LOAN)->first();
    }

    // -----------------------------------------------------------------------
    // Status helpers
    // -----------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'early_settled']);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'rejected']);
    }

    /** True when this loan exempts the member from monthly contributions. */
    public function isExemptingContributions(): bool
    {
        return in_array($this->status, ['approved', 'active']);
    }

    // -----------------------------------------------------------------------
    // Guarantor helpers
    // -----------------------------------------------------------------------

    public function isGuarantorReleased(): bool
    {
        return $this->guarantor_released_at !== null;
    }

    /**
     * Release the guarantor when master_portion is fully repaid.
     * Called by LoanDefaultService / LoanRepaymentService after each repayment.
     */
    public function releaseGuarantorIfDue(): void
    {
        if (! $this->isGuarantorReleased() && $this->guarantor_member_id &&
            (float) $this->repaid_to_master >= (float) $this->master_portion) {
            $this->update(['guarantor_released_at' => now()]);
        }
    }

    // -----------------------------------------------------------------------
    // Settlement helpers
    // -----------------------------------------------------------------------

    /**
     * True when both settlement conditions are met:
     *  1. Master portion fully repaid.
     *  2. Member's fund account balance ≥ settlement_threshold × amount_approved.
     */
    public function isReadyToSettle(): bool
    {
        if ((float) $this->repaid_to_master < (float) $this->master_portion) {
            return false;
        }

        $fundBalance = (float) ($this->member->fundAccount()?->balance ?? 0);
        $required = (float) $this->amount_approved * (float) $this->settlement_threshold;

        return $fundBalance >= $required;
    }

    // -----------------------------------------------------------------------
    // Installment helpers
    // -----------------------------------------------------------------------

    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->installments()->whereIn('status', ['pending', 'overdue'])->sum('amount');
    }

    public function hasOverdueInstallments(): bool
    {
        return $this->installments()->where('status', 'overdue')->exists();
    }

    // -----------------------------------------------------------------------
    // Repayment cycle: contribution exemption logic
    // -----------------------------------------------------------------------

    /**
     * Compute the number of monthly installments needed to fully repay the loan.
     *
     * Formula:
     *   installments = ceil( (master_portion + settlement_threshold × loan_amount)
     *                        / min_monthly_installment )
     *
     * Where:
     *   master_portion  = loan_amount − min(member_fund_balance, loan_amount)
     *   settlement_threshold × loan_amount = the extra 16% (configurable) the
     *     member must accumulate in their fund account to achieve full settlement.
     */
    public static function computeInstallmentsCount(
        float $loanAmount,
        float $memberFundBalance,
        float $minMonthlyInstallment,
        float $settlementThresholdPct,
    ): int {
        $memberPortion = min($memberFundBalance, $loanAmount);
        $masterPortion = $loanAmount - $memberPortion;
        $settlementAmt = $loanAmount * $settlementThresholdPct;
        $totalToRepay = $masterPortion + $settlementAmt;

        return max(1, (int) ceil($totalToRepay / max(1, $minMonthlyInstallment)));
    }

    /**
     * Same repayment horizon as {@see computeInstallmentsCount} but using known portions (e.g. CSV import).
     */
    public static function computeInstallmentsCountFromPortions(
        float $loanAmount,
        float $memberPortion,
        float $minMonthlyInstallment,
        float $settlementThresholdPct,
    ): int {
        $masterPortion = $loanAmount - $memberPortion;
        $settlementAmt = $loanAmount * $settlementThresholdPct;
        $totalToRepay = $masterPortion + $settlementAmt;

        return max(1, (int) ceil($totalToRepay / max(1, $minMonthlyInstallment)));
    }

    /**
     * Determine which contribution cycle is exempted and when repayments start
     * based on the disbursement date.
     */
    public static function computeExemptionAndFirstRepayment(Carbon $disbursedAt): array
    {
        // If disbursed on or before the 5th: exempt PREVIOUS month's contribution
        // If disbursed after the 5th: exempt CURRENT month's contribution
        if ($disbursedAt->day <= 5) {
            $exempted = $disbursedAt->copy()->subMonthNoOverflow();
            $first = $disbursedAt->copy(); // repayment starts this month
        } else {
            $exempted = $disbursedAt->copy();
            $first = $disbursedAt->copy()->addMonthNoOverflow();
        }

        return [
            'exempted_month' => (int) $exempted->month,
            'exempted_year' => (int) $exempted->year,
            'first_repayment_month' => (int) $first->month,
            'first_repayment_year' => (int) $first->year,
        ];
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'approved', 'active']);
    }
}
