<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | First fiscal year (calendar basis)
    |--------------------------------------------------------------------------
    |
    | FiscalYearSeeder creates FY{year} from this calendar year onward (optionally through the current year).
    | Fiscal year closing pre-selects the latest open FY, falling back to this code if none are open.
    |
    | FISCAL_YEAR_INITIAL (.env). FISCAL_YEAR_SEED_THROUGH_CURRENT seeds closed FY rows up to calendar now.
    |
    */

    'initial_calendar_year' => max(1900, (int) env('FISCAL_YEAR_INITIAL', 2014)),

    'seed_fiscal_years_through_current' => filter_var(
        env('FISCAL_YEAR_SEED_THROUGH_CURRENT', false),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | Per–fiscal year SQLite archives
    |--------------------------------------------------------------------------
    |
    | Relative segment under database_path(), e.g. database_path('archives/fy_1_fy2025.sqlite').
    |
    */

    'archive_fiscal_years_directory' => env('ARCHIVE_FY_DATABASE_SUBDIR', 'archives'),

];
