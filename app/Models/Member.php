<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::restored(function (Member $member): void {
            $user = User::withTrashed()->find($member->user_id);
            if ($user !== null && $user->trashed()) {
                $user->restore();
            }
        });
    }

    /** Allowed monthly contribution amounts (multiples of 500, 500–3000). */
    public const CONTRIBUTION_STEPS = [500, 1000, 1500, 2000, 2500, 3000];

    protected $fillable = [
        'user_id',
        'parent_id',
        'household_email',
        'is_separated',
        'direct_login_enabled',
        'portal_pin',
        'member_number',
        'monthly_contribution_amount',
        'late_contributions_count',
        'late_contributions_amount',
        'late_repayment_count',
        'late_repayment_amount',
        'joined_at',
        'status',
        'delinquency_suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'delinquency_suspended_at' => 'datetime',
            'monthly_contribution_amount' => 'integer',
            'is_separated' => 'boolean',
            'direct_login_enabled' => 'boolean',
            'late_contributions_count' => 'integer',
            'late_contributions_amount' => 'decimal:2',
            'late_repayment_count' => 'integer',
            'late_repayment_amount' => 'decimal:2',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The member who sponsors/parents this member. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_id');
    }

    /** Members for whom this member is the parent. */
    public function dependents(): HasMany
    {
        return $this->hasMany(Member::class, 'parent_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Admin/member direct messages associated with this member's login user.
     */
    public function directMessages(): HasMany
    {
        return $this->hasMany(DirectMessage::class, 'from_user_id', 'user_id')
            ->orWhere('to_user_id', $this->user_id);
    }

    /** Parent→dependent cash allocations received by this member (tagged by contribution cycle). */
    public function dependentCashAllocationsReceived(): HasMany
    {
        return $this->hasMany(DependentCashAllocation::class, 'dependent_member_id');
    }

    /** Parent→dependent cash allocations sent from this member to their dependents. */
    public function dependentCashAllocationsSent(): HasMany
    {
        return $this->hasMany(DependentCashAllocation::class, 'parent_member_id');
    }

    /** Audit log of all monthly-allocation changes where this member is the dependent. */
    public function allocationChangesReceived(): HasMany
    {
        return $this->hasMany(DependentAllocationChange::class, 'dependent_member_id');
    }

    /** Audit log of all monthly-allocation changes initiated by this member as parent. */
    public function allocationChangesSent(): HasMany
    {
        return $this->hasMany(DependentAllocationChange::class, 'parent_member_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(MonthlyStatement::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** All membership application rows for this member's login user. */
    public function membershipApplications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class, 'user_id', 'user_id');
    }

    /**
     * Latest application row for this member's user (query; avoids stale relation cache in Livewire).
     */
    public function latestMembershipApplication(): ?MembershipApplication
    {
        return $this->membershipApplications()->orderByDesc('id')->first();
    }

    /**
     * Earliest date used for loan tenure rules: minimum of application membership date and member.joined_at.
     * Avoids relying on joined_at alone when the application carries the canonical membership date.
     */
    public function loanEligibilityStartDate(): ?Carbon
    {
        $this->loadMissing('user');
        $candidates = [];

        if ($this->joined_at !== null) {
            $candidates[] = Carbon::parse($this->joined_at)->startOfDay();
        }

        foreach ($this->membershipApplications()->whereNotNull('membership_date')->cursor() as $app) {
            $candidates[] = Carbon::parse($app->membership_date)->startOfDay();
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)
            ->sortBy(fn(Carbon $d) => $d->timestamp)
            ->first();
    }

    // -----------------------------------------------------------------------
    // Account shortcuts
    // -----------------------------------------------------------------------

    public function cashAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_CASH)->first();
    }

    public function fundAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_FUND)->first();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDelinquent(): bool
    {
        return $this->status === 'delinquent';
    }

    public function isParent(): bool
    {
        return $this->dependents()->exists();
    }

    /** Validate that the given amount is an allowed contribution step. */
    public static function isValidContributionAmount(int $amount): bool
    {
        return in_array($amount, self::CONTRIBUTION_STEPS, true);
    }

    /** Return the select-friendly options array for contribution amounts. */
    public static function contributionAmountOptions(): array
    {
        return array_combine(
            self::CONTRIBUTION_STEPS,
            array_map(fn($v) => __('SAR') . ' ' . number_format($v), self::CONTRIBUTION_STEPS)
        );
    }

    public function getTotalContributionsAttribute(): float
    {
        return (float) $this->contributions()->sum('amount');
    }

    /**
     * Recompute denormalized late stats from active (non-deleted) contributions.
     */
    public function refreshLateContributionStats(): void
    {
        $row = DB::table('contributions')
            ->where('member_id', $this->id)
            ->where('is_late', true)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as total')
            ->first();

        $this->forceFill([
            'late_contributions_count' => (int) ($row->c ?? 0),
            'late_contributions_amount' => (float) ($row->total ?? 0),
        ])->saveQuietly();
    }

    /**
     * Live aggregates for contributions flagged late (is_late), matching the contributions table.
     * Prefer over denormalized columns when those may be stale.
     */
    public function contributionsMarkedLateCount(): int
    {
        return (int) $this->contributions()->where('is_late', true)->count();
    }

    public function contributionsMarkedLateAmount(): float
    {
        return (float) $this->contributions()->where('is_late', true)->sum('amount');
    }

    public function getActiveLoansAttribute()
    {
        return $this->loans()->where('status', 'active')->get();
    }

    /** True if the member has any in-progress loan (pending / approved / active). */
    public function hasActiveLoan(): bool
    {
        return $this->loans()->whereIn('status', ['pending', 'approved', 'disbursed', 'active'])->exists();
    }

    /** True if the member is currently exempt from contributions (active loan in progress). */
    public function isExemptFromContributions(): bool
    {
        return $this->loans()->whereIn('status', ['approved', 'disbursed', 'active'])->exists();
    }

    public function getCashBalanceAttribute(): float
    {
        return (float) ($this->cashAccount()?->balance ?? 0);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDelinquent($query)
    {
        return $query->where('status', 'delinquent');
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', 'terminated');
    }
}
