<?php

namespace App\Services;

use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
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

        $lines = str_getcsv($content, "\n");
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

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
                $amount = $credit;
                $type = 'credit';
            } else {
                $amount = $debit;
                $type = 'debit';
            }
        } else {
            $raw = $this->parseAmount($get($template->amount_column));
            $amount = abs($raw);
            $type = 'credit';

            if ($template->type_column) {
                $indicator = trim((string) $get($template->type_column));
                $type = strcasecmp($indicator, $template->debit_indicator ?? 'DR') === 0
                    ? 'debit'
                    : 'credit';
            } elseif ($raw < 0) {
                $type = 'debit';
            }
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'balance' => $template->balance_column ? $this->parseAmount($get($template->balance_column)) : null,
            'type' => $type,
            'description' => $template->description_column ? trim((string) $get($template->description_column)) : null,
            'reference' => $template->reference_column ? trim((string) $get($template->reference_column)) : null,
            'optional' => $this->mapOptionalColumns($row, $template),
        ];
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

        // Remove currency symbols, spaces, commas used as thousands separators
        $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $value));

        return (float) $clean;
    }

    private function findDuplicate(array $mapped, BankImportTemplate $template, BankImportSession $session): ?BankTransaction
    {
        $allowedOptionalKeys = $this->optionalColumnKeys($template);
        $fields = $this->normalizeDuplicateMatchFields($template->duplicate_match_fields);

        if (! $this->duplicateMatchFieldsAreEffective($fields, $allowedOptionalKeys)) {
            $fields = ['date', 'amount', 'reference'];
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

            if ($field === 'type') {
                $query->where('transaction_type', $mapped['type']);

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

            if ($field === 'balance') {
                $balance = $mapped['balance'] ?? null;
                if ($balance === null) {
                    $query->whereNull('running_balance');
                } else {
                    $this->applyMoneyColumnDuplicateMatch($query, 'running_balance', (float) $balance);
                }

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
    private function normalizeDuplicateMatchFields(?array $fields): array
    {
        if ($fields === null || $fields === []) {
            return ['date', 'amount', 'reference'];
        }

        return array_values(array_unique($fields));
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
        $core = ['date', 'amount', 'type', 'reference', 'description', 'balance'];

        foreach ($fields as $field) {
            if (in_array($field, $core, true)) {
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
