<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BankImportSessionResource;
use App\Models\BankImportSession;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BankImportSessionsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return BankImportSessionResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        BankImportSessionResource::configureTable($table);

        return $table
            ->recordUrl(fn(BankImportSession $record): string => BankImportSessionResource::getUrl('view', ['record' => $record]));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
