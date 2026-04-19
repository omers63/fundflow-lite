<?php

namespace App\Services;

use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
use App\Support\CsvStringParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BankImportService
{
    public function import(BankImportSession $session): void
    {
        $session->update(['status' => 'processing']);

        $template = $session->template;

        try {
            $rows = $this->parseCsv($session->file_path, $template);
            $errors = [];

            $totalRows = count($rows);
            $importedCount = 0;
            $duplicateCount = 0;
            $errorCount = 0;

            foreach ($rows as $lineNumber => $row) {
                try {
                    $mapped = $this->mapRow($row, $template);

                    if ($mapped === null) {
                        continue;
                    }

                    $duplicate = $this->findDuplicate($mapped, $template, $session);

                    $rawData = is_array($row) ? $row : [];
                    if (($mapped['optional'] ?? []) !== []) {
                        $rawData['_optional'] = $mapped['optional'];
                    }

                    BankTransaction::create([
                        'bank_id' => $session->bank_id,
                        'import_session_id' => $session->id,
                        'transaction_date' => $mapped['date'],
                        'amount' => $mapped['amount'],
                        'running_balance' => $mapped['balance'],
                        'transaction_type' => $mapped['type'],
                        'description' => $mapped['description'] ?? null,
                        'reference' => $mapped['reference'] ?? null,
                        'is_duplicate' => $duplicate !== null,
                        'duplicate_of_id' => $duplicate?->id,
                        'raw_data' => $rawData,
                    ]);

                    if ($duplicate) {
                        $duplicateCount++;
                    } else {
                        $importedCount++;
                    }
                } catch (Throwable $e) {
                    $errorCount++;
                    $errors[] = "Row {$lineNumber}: ".$e->getMessage();
                }
            }

            $session->update([
                'status' => $errorCount > 0 && $importedCount === 0 ? 'failed' : ($errorCount > 0 ? 'partially_completed' : 'completed'),
                'total_rows' => $totalRows,
                'imported_count' => $importedCount,
                'duplicate_count' => $duplicateCount,
                'error_count' => $errorCount,
                'error_log' => $errors ?: null,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $session->update([
                'status' => 'failed',
                'error_log' => [$e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    private function parseCsv(string $filePath, BankImportTemplate $template): array
    {
        $fullPath = Storage::path($filePath);

        $content = file_get_contents($fullPath);

        if ($template->encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $template->encoding);
        }

        $delimiter = $template->delimiter === '\t' ? "\t" : $template->delimiter;

        $rows = CsvStringParser::parseRows($content, $delimiter);

        // Skip leading rows (e.g. bank report headers before the data)
        if ($template->skip_rows > 0) {
            $rows = array_slice($rows, $template->skip_rows);
        }

        if (empty($rows)) {
            return [];
        }

        // Build header map when has_header is true
        if ($template->has_header) {
            $headers = array_map('trim', array_shift($rows));
            $headerMap = array_flip($headers);

            return array_map(function (array $row) use ($headers) {
                $assoc = [];
                foreach ($headers as $i => $header) {
                    $assoc[$header] = $row[$i] ?? null;
                }

                return $assoc;
            }, $rows);
        }

        // No header — use numeric indices as keys
        return $rows;
    }

    private function mapRow(array $row, BankImportTemplate $template): ?array
    {
        $get = fn (string $col) => $this->getColumn($row, $col, $template->has_header);

        // Date
        $rawDate = $get($template->date_column);
        if (blank($rawDate)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat($template->date_format, trim($rawDate))->format('Y-m-d');
        } catch (Throwable) {
            $date = Carbon::parse(trim($rawDate))->format('Y-m-d');
        }

        // Amount & type
        if ($template->amount_type === 'split') {
            $credit = $this->parseAmount($get($template->credit_column));
            $debit = $this->parseAmount($get($template->debit_column));

            if ($credit > 0) {
                $amount = abs($credit);
                $type = 'credit';
            } else {
                // Debit column may be negative (outflow) or positive; store magnitude only.
                $amount = abs($debit);
                $type = 'debit';
            }
        } else {
            $raw = $this->parseAmount($get($template->amount_column));
            $amount = abs($raw);
            $type = $raw < 0 ? 'debit' : 'credit';
        }

        $optional = $this->mapOptionalColumns($row, $template);

        $reference = $this->optionalFieldString($optional, 'reference');
        $description = $this->optionalFieldString($optional, 'description');
        $balance = $this->optionalFieldBalance($optional, 'balance');

        return [
            'date' => $date,
            'amount' => $amount,
            'balance' => $balance,
            'type' => $type,
            'description' => $description,
            'reference' => $reference,
            'optional' => $optional,
        ];
    }

    /**
     * @param  array<string, mixed>  $optional
     */
    private function optionalFieldString(array $optional, string $key): ?string
    {
        if (! array_key_exists($key, $optional)) {
            return null;
        }

        $v = $optional[$key];

        return $v === null || $v === '' ? null : trim((string) $v);
    }

    /**
     * @param  array<string, mixed>  $optional
     */
    private function optionalFieldBalance(array $optional, string $key): ?float
    {
        if (! array_key_exists($key, $optional)) {
            return null;
        }

        $v = $optional[$key];
        if ($v === null || $v === '') {
            return null;
        }

        return $this->parseAmount($v);
    }

    private function mapOptionalColumns(array $row, BankImportTemplate $template): array
    {
        $definitions = is_array($template->optional_columns) ? $template->optional_columns : [];
        if ($definitions === []) {
            return [];
        }

        $optional = [];

        foreach ($definitions as $def) {
            if (! is_array($def)) {
                continue;
            }

            $key = trim((string) ($def['key'] ?? ''));
            $column = trim((string) ($def['column'] ?? ''));

            if ($key === '' || $column === '') {
                continue;
            }

            $value = $this->getColumn($row, $column, $template->has_header);
            if (is_string($value)) {
                $value = trim($value);
            }

            $optional[$key] = $value;
        }

        return $optional;
    }

    private function getColumn(array $row, string $column, bool $hasHeader): mixed
    {
        // Column can be a header name (string) or a 0-based numeric index
        if (is_numeric($column) && ! $hasHeader) {
            return $row[(int) $column] ?? null;
        }

        return $row[$column] ?? null;
    }

    private function parseAmount(mixed $value): float
    {
        if (blank($value)) {
            return 0.0;
        }

        $raw = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if ($raw === '') {
            return 0.0;
        }

        // Non-breaking space and similar
        $raw = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $raw);

        // Strip leading currency words / symbols before the numeric part (e.g. "SAR 1,234.50", "USD  500")
        $raw = preg_replace('/^[^\d\-−+]+/u', '', $raw);
        $raw = trim($raw);

        // Spaces inside the number (e.g. "1 234.50")
        $raw = preg_replace('/\s+/u', '', $raw);

        // Thousands separators
        $raw = str_replace(',', '', $raw);

        $clean = preg_replace('/[^\d.\-]/', '', $raw);

        if ($clean === '' || $clean === '.' || $clean === '-') {
            return 0.0;
        }

        return (float) $clean;
    }

    private function findDuplicate(array $mapped, BankImportTemplate $template, BankImportSession $session): ?BankTransaction
    {
        $allowedOptionalKeys = $this->optionalColumnKeys($template);
        $fields = $this->normalizeDuplicateMatchFields($template->duplicate_match_fields, $template);

        if (! $this->duplicateMatchFieldsAreEffective($fields, $allowedOptionalKeys)) {
            $fields = $this->defaultDuplicateMatchFieldList($template);
        }

        $tolerance = $template->duplicate_date_tolerance ?? 0;

        $query = BankTransaction::query()
            ->where('bank_id', $session->bank_id)
            ->where('is_duplicate', false)
            ->orderBy('id');

        foreach ($fields as $field) {
            if ($field === 'date') {
                $date = Carbon::parse($mapped['date'])->startOfDay();
                $from = $date->copy()->subDays($tolerance)->startOfDay();
                $to = $date->copy()->addDays($tolerance)->endOfDay();
                $query->whereBetween('transaction_date', [$from, $to]);

                continue;
            }

            if ($field === 'amount') {
                $this->applyMoneyColumnDuplicateMatch($query, 'amount', (float) $mapped['amount']);

                continue;
            }

            if ($field === 'reference') {
                $this->applyNullableTextDuplicateMatch($query, 'reference', $mapped['reference'] ?? null);

                continue;
            }

            if ($field === 'description') {
                $this->applyNullableTextDuplicateMatch($query, 'description', $mapped['description'] ?? null);

                continue;
            }

            if (str_starts_with($field, 'optional:')) {
                $key = substr($field, strlen('optional:'));
                if ($key === '' || ! in_array($key, $allowedOptionalKeys, true)) {
                    continue;
                }
                $this->applyOptionalDuplicateMatch($query, $key, $mapped['optional'][$key] ?? null);
            }
        }

        return $query->first();
    }

    /**
     * @param  array<int, string>|null  $fields
     * @return list<string>
     */
    private function normalizeDuplicateMatchFields(?array $fields, BankImportTemplate $template): array
    {
        $allowedKeys = $this->optionalColumnKeys($template);

        if ($fields === null || $fields === []) {
            return $this->defaultDuplicateMatchFieldList($template);
        }

        $fields = array_values(array_unique($fields));
        $fields = array_values(array_diff($fields, ['type', 'balance']));

        if (! in_array('reference', $allowedKeys, true)) {
            $fields = array_values(array_diff($fields, ['reference']));
        }
        if (! in_array('description', $allowedKeys, true)) {
            $fields = array_values(array_diff($fields, ['description']));
        }

        $fields = array_values(array_filter($fields, function (string $f) use ($allowedKeys): bool {
            if (! str_starts_with($f, 'optional:')) {
                return true;
            }
            $key = substr($f, strlen('optional:'));

            return $key !== '' && in_array($key, $allowedKeys, true);
        }));

        if ($fields === []) {
            return $this->defaultDuplicateMatchFieldList($template);
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function defaultDuplicateMatchFieldList(BankImportTemplate $template): array
    {
        $fields = ['date', 'amount'];
        if (in_array('reference', $this->optionalColumnKeys($template), true)) {
            $fields[] = 'reference';
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function optionalColumnKeys(BankImportTemplate $template): array
    {
        $definitions = is_array($template->optional_columns) ? $template->optional_columns : [];
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

    private function applyMoneyColumnDuplicateMatch(Builder $query, string $column, float $value): void
    {
        $rounded = round($value, 2);
        // Half-cent window: avoids float noise and SQLite quirks with ROUND(...) = bound float.
        $epsilon = 0.00499;

        $query->whereBetween($column, [$rounded - $epsilon, $rounded + $epsilon]);
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $allowedOptionalKeys
     */
    private function duplicateMatchFieldsAreEffective(array $fields, array $allowedOptionalKeys): bool
    {
        foreach ($fields as $field) {
            if ($field === 'date' || $field === 'amount') {
                return true;
            }
            if ($field === 'reference' && in_array('reference', $allowedOptionalKeys, true)) {
                return true;
            }
            if ($field === 'description' && in_array('description', $allowedOptionalKeys, true)) {
                return true;
            }
            if (str_starts_with($field, 'optional:')) {
                $key = substr($field, strlen('optional:'));
                if ($key !== '' && in_array($key, $allowedOptionalKeys, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function applyNullableTextDuplicateMatch(Builder $query, string $column, mixed $value): void
    {
        if (filled($value)) {
            $query->where($column, is_string($value) ? trim($value) : $value);

            return;
        }

        $query->where(function (Builder $q) use ($column) {
            $q->whereNull($column)->orWhere($column, '');
        });
    }

    private function applyOptionalDuplicateMatch(Builder $query, string $key, mixed $incoming): void
    {
        $path = 'raw_data->_optional->'.$key;

        if (! filled($incoming)) {
            $query->where(function (Builder $q) use ($path) {
                $q->whereNull($path)->orWhere($path, '');
            });

            return;
        }

        $normalized = is_string($incoming) ? trim($incoming) : $incoming;

        $query->where(function (Builder $q) use ($path, $normalized) {
            $q->where($path, $normalized);
            if (is_numeric($normalized)) {
                $q->orWhere($path, (float) $normalized + 0.0);
            }
        });
    }
}
