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

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        self::CATEGORY_GENERAL_INQUIRY => 'General Inquiry',
        self::CATEGORY_CASH_DEPOSIT => 'Cash Deposit Request',
        self::CATEGORY_LOAN_INQUIRY => 'Loan Inquiry',
        self::CATEGORY_CONTRIBUTION_QUERY => 'Contribution Query',
        self::CATEGORY_BALANCE_QUERY => 'Balance / Account Query',
        self::CATEGORY_COMPLAINT => 'Complaint',
        self::CATEGORY_OTHER => 'Other',
    ];

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
        return self::CATEGORY_LABELS[$category] ?? $category;
    }
}
