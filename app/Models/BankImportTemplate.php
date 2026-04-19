<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankImportTemplate extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (BankImportTemplate $template): void {
            $template->duplicate_match_fields = self::sanitizeDuplicateMatchFields(
                $template->duplicate_match_fields,
                $template->optional_columns
            );
        });
    }

    /**
     * Align duplicate match selections with optional column keys (reference / description only when mapped).
     *
     * @param  array<int, string>|null  $fields
     * @param  array<int, mixed>|null  $optionalColumnsDefinitions
     * @return list<string>
     */
    public static function sanitizeDuplicateMatchFields(?array $fields, ?array $optionalColumnsDefinitions): array
    {
        $optionalKeys = self::optionalColumnKeysFromDefinitions($optionalColumnsDefinitions);

        if (! is_array($fields) || $fields === []) {
            return self::defaultDuplicateMatchFieldsForOptionalKeys($optionalKeys);
        }

        $fields = array_values(array_unique($fields));
        $fields = array_values(array_diff($fields, ['type', 'balance']));
        if (! in_array('reference', $optionalKeys, true)) {
            $fields = array_values(array_diff($fields, ['reference']));
        }
        if (! in_array('description', $optionalKeys, true)) {
            $fields = array_values(array_diff($fields, ['description']));
        }

        $fields = array_values(array_filter($fields, function (mixed $f) use ($optionalKeys): bool {
            $f = (string) $f;
            if (! str_starts_with($f, 'optional:')) {
                return true;
            }
            $key = substr($f, strlen('optional:'));

            return $key !== '' && in_array($key, $optionalKeys, true);
        }));

        if ($fields === []) {
            return self::defaultDuplicateMatchFieldsForOptionalKeys($optionalKeys);
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private static function defaultDuplicateMatchFieldsForOptionalKeys(array $optionalKeys): array
    {
        $fields = ['date', 'amount'];
        if (in_array('reference', $optionalKeys, true)) {
            $fields[] = 'reference';
        }

        return $fields;
    }

    /**
     * @param  array<int, mixed>|null  $definitions
     * @return list<string>
     */
    private static function optionalColumnKeysFromDefinitions(?array $definitions): array
    {
        if (! is_array($definitions)) {
            return [];
        }
        $keys = [];
        foreach ($definitions as $def) {
            if (! is_array($def)) {
                continue;
            }
            $key = trim((string) ($def['key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    protected $fillable = [
        'bank_id',
        'name',
        'is_default',
        'delimiter',
        'encoding',
        'has_header',
        'skip_rows',
        'date_column',
        'date_format',
        'amount_type',
        'amount_column',
        'credit_column',
        'debit_column',
        'optional_columns',
        'duplicate_match_fields',
        'duplicate_date_tolerance',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'has_header' => 'boolean',
        'skip_rows' => 'integer',
        'optional_columns' => 'array',
        'duplicate_match_fields' => 'array',
        'duplicate_date_tolerance' => 'integer',
    ];

    protected $attributes = [
        'duplicate_match_fields' => '["date","amount"]',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(BankImportSession::class, 'template_id');
    }

    /** Human-readable delimiter label */
    public function getDelimiterLabelAttribute(): string
    {
        return match ($this->delimiter) {
            ',' => 'Comma (,)',
            ';' => 'Semicolon (;)',
            "\t" => 'Tab',
            '|' => 'Pipe (|)',
            default => $this->delimiter,
        };
    }
}
