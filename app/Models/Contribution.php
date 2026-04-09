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

    /** @return array<string, string> */
    public static function paymentMethodOptions(): array
    {
        return [
            'cash_account' => 'Cash account (cycle)',
            'admin' => 'Admin entry',
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
        });

        static::updating(function (Contribution $contribution): void {
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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'is_late' => 'boolean',
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
