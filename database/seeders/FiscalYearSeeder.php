<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds calendar fiscal years (Jan 1 – Dec 31) starting at {@see config fundflow.initial_calendar_year}.
 *
 * When `fundflow.seed_fiscal_years_through_current` is true, creates every year through the current calendar
 * year; earlier years are `closed` with `closed_at` at FY end — only the current year stays `open`.
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $start = (int) config('fundflow.initial_calendar_year');

        if (! config('fundflow.seed_fiscal_years_through_current')) {
            FiscalYear::query()->updateOrCreate(
                ['code' => 'FY'.$start],
                [
                    'start_date' => sprintf('%04d-01-01', $start),
                    'end_date' => sprintf('%04d-12-31', $start),
                    'status' => 'open',
                    'closed_at' => null,
                    'closed_by_id' => null,
                ]
            );

            return;
        }

        $currentCalendarYear = (int) Carbon::now()->timezone(config('app.timezone'))->format('Y');
        $endYear = max($start, $currentCalendarYear);

        foreach (range($start, $endYear) as $year) {
            $isHistorical = $year < $endYear;

            FiscalYear::query()->updateOrCreate(
                ['code' => 'FY'.$year],
                [
                    'start_date' => sprintf('%04d-01-01', $year),
                    'end_date' => sprintf('%04d-12-31', $year),
                    'status' => $isHistorical ? 'closed' : 'open',
                    'closed_at' => $isHistorical
                        ? Carbon::parse(sprintf('%04d-12-31', $year), config('app.timezone'))->endOfDay()
                        : null,
                    'closed_by_id' => null,
                ]
            );
        }
    }
}
