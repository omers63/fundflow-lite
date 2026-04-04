<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SmsImportSessionResource;
use App\Models\SmsImportSession;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class SmsImportSessionsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return SmsImportSessionResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        SmsImportSessionResource::configureTable($table);

        return $table
            ->recordUrl(fn(SmsImportSession $record): string => SmsImportSessionResource::getUrl('view', ['record' => $record]));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
