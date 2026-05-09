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
}
