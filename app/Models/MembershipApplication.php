<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipApplication extends Model
{
    protected $fillable = [
        'user_id',
        'application_type',
        'gender',
        'marital_status',
        'national_id',
        'date_of_birth',
        'address',
        'city',
        'home_phone',
        'work_phone',
        'mobile_phone',
        'occupation',
        'employer',
        'work_place',
        'residency_place',
        'monthly_income',
        'bank_account_number',
        'iban',
        'membership_date',
        'next_of_kin_name',
        'next_of_kin_phone',
        'application_form_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'monthly_income' => 'decimal:2',
            'membership_date' => 'date',
        ];
    }

    /** @return array<string, string> */
    public static function applicationTypeOptions(): array
    {
        return [
            'new' => 'New',
            'resume' => 'Resume',
            'renew' => 'Renew',
        ];
    }

    /** @return array<string, string> */
    public static function genderOptions(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function maritalStatusOptions(): array
    {
        return [
            'single' => 'Single',
            'married' => 'Married',
            'divorced' => 'Divorced',
            'widowed' => 'Widowed',
            'other' => 'Other',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
