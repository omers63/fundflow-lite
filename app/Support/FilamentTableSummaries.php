<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;

final class FilamentTableSummaries
{
    /**
     * Table footer summaries for a single numeric/money column: row count (non-null values),
     * sum, and average. Uses {@see Count} / {@see Sum} / {@see Average} summarizers on the filtered query.
     *
     * @return array<int, Average|Count|Sum>
     */
    public static function countSumAverageMoney(string $currency = 'SAR'): array
    {
        return [
            Count::make(),
            Sum::make()->money($currency),
            Average::make()->money($currency),
        ];
    }

    /**
     * @return array<int, Average|Count|Sum>
     */
    public static function countSumAverageNumeric(int $decimalPlaces = 2): array
    {
        return [
            Count::make(),
            Sum::make()->numeric($decimalPlaces),
            Average::make()->numeric($decimalPlaces),
        ];
    }

    /**
     * Single footer total for amounts where count/average rows would mislead (sparse nulls or tiered SAR values).
     *
     * @return array<int, Sum>
     */
    public static function sumMoney(string $currency = 'SAR'): array
    {
        return [
            Sum::make()->money($currency),
        ];
    }

    /**
     * Filament column width applies to header cells only; table body cells otherwise grow with content.
     * Pair with TextColumn::extraCellAttributes(['style' => ...]) so the column stays visually narrow.
     */
    public static function narrowFixedCellStyle(string $cssLength): string
    {
        return sprintf(
            'max-width: %1$s; width: %1$s; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;',
            $cssLength,
        );
    }

    /**
     * Member display name columns: prioritize full visibility (wrap, no ellipsis).
     */
    public static function memberDisplayNameCellStyle(): string
    {
        return 'min-width: 11rem; word-break: break-word; white-space: normal; overflow: visible; text-overflow: clip; vertical-align: top;';
    }

    /** Member number / ID columns: show full value when possible (wrap instead of ellipsis). */
    public static function memberNumberCellStyle(): string
    {
        return 'min-width: 5.5rem; word-break: break-word; white-space: normal; overflow: visible; text-overflow: clip; vertical-align: top;';
    }
}
