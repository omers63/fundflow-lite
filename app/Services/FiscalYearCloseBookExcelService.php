<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalYearCloseBookExcelService
{
    public function __construct(
        protected FiscalYearClosingService $closingService
    ) {}

    /**
     * Multi-sheet workbook: Summary + one sheet per archived table (same date scope as close).
     */
    public function downloadResponse(FiscalYear $fiscalYear): StreamedResponse
    {
        $filename = 'close-book-'.$fiscalYear->code.'-'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($fiscalYear): void {
            $spreadsheet = $this->buildSpreadsheet($fiscalYear);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function buildSpreadsheet(FiscalYear $fiscalYear): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $this->fillSummarySheet($summary, $fiscalYear);

        $sheetIndex = 1;
        foreach ($this->closingService->archiveTables() as $item) {
            $table = $item['table'];
            $dateColumn = $item['date_column'];
            $safeName = $this->excelSheetTitle($table, $sheetIndex);

            $dataSheet = new Worksheet($spreadsheet, $safeName);
            $spreadsheet->addSheet($dataSheet, $sheetIndex);
            $this->fillTableSheet($dataSheet, $fiscalYear, $table, $dateColumn);
            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function fillSummarySheet(Worksheet $sheet, FiscalYear $fy): void
    {
        $sheet->setCellValue('A1', 'Fiscal year close book (primary DB snapshot)');
        $sheet->setCellValue('A2', 'Code');
        $sheet->setCellValue('B2', $fy->code);
        $sheet->setCellValue('A3', 'Start');
        $sheet->setCellValue('B3', $fy->start_date?->toDateString() ?? '');
        $sheet->setCellValue('A4', 'End');
        $sheet->setCellValue('B4', $fy->end_date?->toDateString() ?? '');
        $sheet->setCellValue('A5', 'Status');
        $sheet->setCellValue('B5', $fy->status);
        $sheet->setCellValue('A6', 'Generated at');
        $sheet->setCellValue('B6', now()->toDateTimeString());

        $sheet->setCellValue('A8', 'Table');
        $sheet->setCellValue('B8', 'Date column');
        $sheet->setCellValue('C8', 'Row count (in range)');

        $row = 9;
        foreach ($this->closingService->archiveTables() as $item) {
            $table = $item['table'];
            $dateColumn = $item['date_column'];
            $count = $this->closingService->scopedSourceQuery($table, $dateColumn, $fy)->count();
            $sheet->setCellValue('A'.$row, $table);
            $sheet->setCellValue('B'.$row, $dateColumn);
            $sheet->setCellValue('C'.$row, $count);
            $row++;
        }

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function fillTableSheet(Worksheet $sheet, FiscalYear $fy, string $table, string $dateColumn): void
    {
        if (! Schema::hasTable($table)) {
            $sheet->setCellValue('A1', 'Table missing: '.$table);

            return;
        }

        $columns = Schema::getColumnListing($table);
        if ($columns === []) {
            $sheet->setCellValue('A1', '(no columns)');

            return;
        }

        $colIndex = 1;
        foreach ($columns as $colName) {
            $coord = Coordinate::stringFromColumnIndex($colIndex).'1';
            $sheet->setCellValue($coord, $colName);
            $colIndex++;
        }

        $excelRow = 2;
        $query = $this->closingService->scopedSourceQuery($table, $dateColumn, $fy)->orderBy('id');

        $query->chunkById(1000, function ($rows) use ($sheet, $columns, &$excelRow): void {
            foreach ($rows as $row) {
                $arr = (array) $row;
                $colIndex = 1;
                foreach ($columns as $colName) {
                    $val = $arr[$colName] ?? null;
                    $cell = Coordinate::stringFromColumnIndex($colIndex).$excelRow;
                    if (is_string($val) || is_numeric($val) || $val === null) {
                        $sheet->setCellValue($cell, $val);
                    } elseif (is_object($val) && method_exists($val, '__toString')) {
                        $sheet->setCellValue($cell, (string) $val);
                    } else {
                        try {
                            $sheet->setCellValue($cell, json_encode($val, JSON_THROW_ON_ERROR));
                        } catch (\Throwable) {
                            $sheet->setCellValue($cell, '');
                        }
                    }
                    $colIndex++;
                }
                $excelRow++;
            }
        });

        $sheet->freezePane('A2');
    }

    private function excelSheetTitle(string $table, int $index): string
    {
        $name = preg_replace('/[^A-Za-z0-9 _-]/', '_', $table) ?? $table;
        $name = substr($name, 0, 28);

        return $name !== '' ? $name : 'Sheet'.$index;
    }
}
