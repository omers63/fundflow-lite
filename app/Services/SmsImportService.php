<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SmsImportSession;
use App\Models\SmsImportTemplate;
use App\Models\SmsTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SmsImportService
{
    public function import(SmsImportSession $session): void
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
                    $parsed = $this->parseRow($row, $template);

                    if ($parsed === null) {
                        continue;
                    }

                    $duplicate = $this->findDuplicate($parsed, $template, $session->bank_id);

                    // Auto-match member from SMS text if template has a member pattern
                    $matchedMemberId = $this->matchMember($parsed['raw_sms'], $template);

                    SmsTransaction::create([
                        'bank_id'          => $session->bank_id,
                        'import_session_id'=> $session->id,
                        'member_id'        => $matchedMemberId,
                        'transaction_date' => $parsed['date'],
                        'amount'           => $parsed['amount'],
                        'transaction_type' => $parsed['type'],
                        'reference'        => $parsed['reference'] ?? null,
                        'raw_sms'          => $parsed['raw_sms'],
                        'raw_data'         => $row,
                        'is_duplicate'     => $duplicate !== null,
                        'duplicate_of_id'  => $duplicate?->id,
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
                'status'          => match (true) {
                    $errorCount > 0 && $importedCount === 0 => 'failed',
                    $errorCount > 0                         => 'partially_completed',
                    default                                 => 'completed',
                },
                'total_rows'      => $totalRows,
                'imported_count'  => $importedCount,
                'duplicate_count' => $duplicateCount,
                'error_count'     => $errorCount,
                'error_log'       => $errors ?: null,
                'completed_at'    => now(),
            ]);
        } catch (Throwable $e) {
            $session->update([
                'status'       => 'failed',
                'error_log'    => [$e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // CSV parsing (reuses the same logic as BankImportService)
    // -----------------------------------------------------------------------

    private function parseCsv(string $filePath, SmsImportTemplate $template): array
    {
        $fullPath = Storage::path($filePath);
        $content  = file_get_contents($fullPath);

        if ($template->encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $template->encoding);
        }

        $delimiter = $template->delimiter === '\t' ? "\t" : $template->delimiter;
        $lines     = str_getcsv($content, "\n");
        $rows      = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        if ($template->skip_rows > 0) {
            $rows = array_slice($rows, $template->skip_rows);
        }

        if (empty($rows)) {
            return [];
        }

        if ($template->has_header) {
            $headers = array_map('trim', array_shift($rows));

            return array_map(function (array $row) use ($headers) {
                $assoc = [];
                foreach ($headers as $i => $header) {
                    $assoc[$header] = $row[$i] ?? null;
                }
                return $assoc;
            }, $rows);
        }

        return $rows;
    }

    // -----------------------------------------------------------------------
    // Row → structured transaction
    // -----------------------------------------------------------------------

    private function parseRow(array $row, SmsImportTemplate $template): ?array
    {
        $get = fn (string $col) => $this->getColumn($row, $col, $template->has_header);

        $rawSms = trim((string) $get($template->sms_column));

        if (blank($rawSms)) {
            return null;
        }

        // Amount
        $amount = $this->extractAmount($rawSms, $template->amount_pattern);

        // Date — prefer the dedicated date column; fall back to regex extraction
        $date = null;
        if (filled($template->date_column)) {
            $raw = trim((string) $get($template->date_column));
            if (filled($raw)) {
                $date = $this->parseDate($raw, $template->date_format);
            }
        }

        if ($date === null && filled($template->date_pattern)) {
            $extracted = $this->regexCapture($rawSms, $template->date_pattern, 'date');
            if (filled($extracted)) {
                $date = $this->parseDate($extracted, $template->date_pattern_format ?? $template->date_format);
            }
        }

        // Reference
        $reference = null;
        if (filled($template->reference_pattern)) {
            $reference = $this->regexCapture($rawSms, $template->reference_pattern, 'reference');
        }

        // Transaction type
        $type = $this->detectType($rawSms, $template);

        return [
            'raw_sms'   => $rawSms,
            'amount'    => $amount,
            'date'      => $date,
            'type'      => $type,
            'reference' => $reference,
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getColumn(array $row, string $column, bool $hasHeader): mixed
    {
        if (is_numeric($column) && !$hasHeader) {
            return $row[(int) $column] ?? null;
        }
        return $row[$column] ?? null;
    }

    private function extractAmount(string $text, ?string $pattern): ?float
    {
        if (blank($pattern)) {
            return null;
        }

        $value = $this->regexCapture($text, $pattern, 'amount');

        if (blank($value)) {
            return null;
        }

        // Remove thousands separators, keep decimal point
        $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $value));

        return filled($clean) ? (float) $clean : null;
    }

    private function parseDate(string $raw, ?string $format): ?string
    {
        if (blank($format)) {
            return Carbon::parse($raw)->format('Y-m-d');
        }

        try {
            return Carbon::createFromFormat($format, trim($raw))->format('Y-m-d');
        } catch (Throwable) {
            return Carbon::parse(trim($raw))->format('Y-m-d');
        }
    }

    /**
     * Run a regex with a named capture group and return the captured value.
     * The pattern may or may not be wrapped with delimiters — we add them if absent.
     */
    private function regexCapture(string $subject, string $pattern, string $group): ?string
    {
        $delimited = $this->ensureDelimiters($pattern);

        if (@preg_match($delimited, $subject, $matches) && isset($matches[$group])) {
            return trim($matches[$group]);
        }

        return null;
    }

    private function ensureDelimiters(string $pattern): string
    {
        $first = substr(ltrim($pattern), 0, 1);

        if (in_array($first, ['/', '#', '~', '@', '!'])) {
            return $pattern;
        }

        return '/' . str_replace('/', '\/', $pattern) . '/ui';
    }

    private function detectType(string $text, SmsImportTemplate $template): string
    {
        $textLower = mb_strtolower($text);

        foreach ((array) ($template->credit_keywords ?? []) as $keyword) {
            if (str_contains($textLower, mb_strtolower($keyword))) {
                return 'credit';
            }
        }

        foreach ((array) ($template->debit_keywords ?? []) as $keyword) {
            if (str_contains($textLower, mb_strtolower($keyword))) {
                return 'debit';
            }
        }

        return $template->default_transaction_type ?? 'credit';
    }

    // -----------------------------------------------------------------------
    // Member auto-matching
    // -----------------------------------------------------------------------

    private function matchMember(string $smsText, SmsImportTemplate $template): ?int
    {
        if (blank($template->member_match_pattern)) {
            return null;
        }

        $value = $this->regexCapture($smsText, $template->member_match_pattern, 'member');

        if (blank($value)) {
            return null;
        }

        $field = $template->member_match_field ?? 'member_number';

        if ($field === 'member_number') {
            $member = Member::where('member_number', trim($value))->first();
            return $member?->id;
        }

        if ($field === 'user_name') {
            $user = User::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->first();
            return $user ? Member::where('user_id', $user->id)->value('id') : null;
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Duplicate detection
    // -----------------------------------------------------------------------

    private function findDuplicate(array $parsed, SmsImportTemplate $template, ?int $bankId): ?SmsTransaction
    {
        $fields    = $template->duplicate_match_fields ?? ['date', 'amount', 'reference'];
        $tolerance = $template->duplicate_date_tolerance ?? 0;

        $query = SmsTransaction::where('is_duplicate', false);

        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        if (in_array('date', $fields) && filled($parsed['date'])) {
            $date = Carbon::parse($parsed['date']);
            $query->whereBetween('transaction_date', [
                $date->copy()->subDays($tolerance)->toDateString(),
                $date->copy()->addDays($tolerance)->toDateString(),
            ]);
        }

        if (in_array('amount', $fields) && filled($parsed['amount'])) {
            $query->where('amount', $parsed['amount']);
        }

        if (in_array('type', $fields)) {
            $query->where('transaction_type', $parsed['type']);
        }

        if (in_array('reference', $fields) && filled($parsed['reference'])) {
            $query->where('reference', $parsed['reference']);
        }

        if (in_array('raw_sms', $fields) && filled($parsed['raw_sms'])) {
            $query->where('raw_sms', $parsed['raw_sms']);
        }

        return $query->first();
    }
}
