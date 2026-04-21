<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    public const CATEGORY_GENERAL_INQUIRY = 'general_inquiry';

    public const CATEGORY_CASH_DEPOSIT = 'cash_deposit';

    public const CATEGORY_LOAN_INQUIRY = 'loan_inquiry';

    public const CATEGORY_CONTRIBUTION_QUERY = 'contribution_query';

    public const CATEGORY_BALANCE_QUERY = 'balance_query';

    public const CATEGORY_COMPLAINT = 'complaint';

    public const CATEGORY_OTHER = 'other';

    /** @return array<string, string> category value => localized label */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_GENERAL_INQUIRY => __('General Inquiry'),
            self::CATEGORY_CASH_DEPOSIT => __('Cash Deposit Request'),
            self::CATEGORY_LOAN_INQUIRY => __('Loan Inquiry'),
            self::CATEGORY_CONTRIBUTION_QUERY => __('Contribution Query'),
            self::CATEGORY_BALANCE_QUERY => __('Balance / Account Query'),
            self::CATEGORY_COMPLAINT => __('Complaint'),
            self::CATEGORY_OTHER => __('Other'),
        ];
    }

    protected $fillable = [
        'user_id',
        'member_id',
        'category',
        'subject',
        'message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function categoryLabel(string $category): string
    {
        return self::categoryOptions()[$category] ?? $category;
    }
}
