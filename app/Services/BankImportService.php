<?php

namespace App\Services;

use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BankImportService
{
    public function import(BankImportSession $session): void
    {
        $session->update(['status' => 'processing']);

        $template = $session->template;

        try {
            $rows   = $this->parseCsv($session->file_path, $template);
            $errors = [];

            $totalRows      = count($rows);
            $importedCount  = 0;
            $duplicateCount = 0;
            $errorCount     = 0;

            foreach ($rows as $lineNumber => $row) {
                try {
                    $mapped = $this->mapRow($row, $template);

                    if ($mapped === null) {
                        continue;
                    }

                    $duplicate = $this->findDuplicate($mapped, $template);

                    BankTransaction::create([
                        'bank_id'          => $session->bank_id,
                        'import_session_id'=> $session->id,
                        'transaction_date' => $mapped['date'],
                        'amount'           => $mapped['amount'],
                        'transaction_type' => $mapped['type'],
                        'description'      => $mapped['description'] ?? null,
                        'reference'        => $mapped['reference'] ?? null,
                        'is_duplicate'     => $duplicate !== null,
                        'duplicate_of_id'  => $duplicate?->id,
                        'raw_data'         => $row,
                    ]);

                    if ($duplicate) {
                        $duplicateCount++;
                    } else {
                        $importedCount++;
                    }
                } catch (Throwable $e) {
                    $errorCount++;
                    $errors[] = "Row {$lineNumber}: " . $e->getMessage();
                }
            }

            $session->update([
                'status'          => $errorCount > 0 && $importedCount === 0 ? 'failed' : ($errorCount > 0 ? 'partially_completed' : 'completed'),
                'total_rows'      => $totalRows,
                'imported_count'  => $importedCount,
                'duplicate_count' => $duplicateCount,
                'error_count'     => $errorCount,
                'error_log'       => $errors ?: null,
                'completed_at'    => now(),
            ]);
        } catch (Throwable $e) {
            $session->update([
                'status'    => 'failed',
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
        $rows  = [];

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

            return array_map(function (array $row) use ($headers, $headerMap) {
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
            $debit  = $this->parseAmount($get($template->debit_column));

            if ($credit > 0) {
                $amount = $credit;
                $type   = 'credit';
            } else {
                $amount = $debit;
                $type   = 'debit';
            }
        } else {
            $raw    = $this->parseAmount($get($template->amount_column));
            $amount = abs($raw);
            $type   = 'credit';

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
            'date'        => $date,
            'amount'      => $amount,
            'type'        => $type,
            'description' => $template->description_column ? trim((string) $get($template->description_column)) : null,
            'reference'   => $template->reference_column ? trim((string) $get($template->reference_column)) : null,
        ];
    }

    private function getColumn(array $row, string $column, bool $hasHeader): mixed
    {
        // Column can be a header name (string) or a 0-based numeric index
        if (is_numeric($column) && !$hasHeader) {
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

    private function findDuplicate(array $mapped, BankImportTemplate $template): ?BankTransaction
    {
        $fields    = $template->duplicate_match_fields ?? ['date', 'amount', 'reference'];
        $tolerance = $template->duplicate_date_tolerance ?? 0;

        $query = BankTransaction::where('bank_id', $template->bank_id)
            ->where('is_duplicate', false);

        if (in_array('date', $fields)) {
            $date = Carbon::parse($mapped['date']);
            $query->whereBetween('transaction_date', [
                $date->copy()->subDays($tolerance)->toDateString(),
                $date->copy()->addDays($tolerance)->toDateString(),
            ]);
        }

        if (in_array('amount', $fields)) {
            $query->where('amount', $mapped['amount']);
        }

        if (in_array('type', $fields)) {
            $query->where('transaction_type', $mapped['type']);
        }

        if (in_array('reference', $fields) && filled($mapped['reference'])) {
            $query->where('reference', $mapped['reference']);
        }

        if (in_array('description', $fields) && filled($mapped['description'])) {
            $query->where('description', $mapped['description']);
        }

        return $query->first();
    }
}
