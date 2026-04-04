<?php

namespace App\Filament\Admin\Resources\MonthlyStatementResource\Pages;

use App\Filament\Admin\Resources\MonthlyStatementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyStatements extends ListRecords
{
    protected static string $resource = MonthlyStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
