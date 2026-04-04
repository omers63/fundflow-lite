<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SmsTransactionResource;
use App\Models\SmsTransaction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class SmsTransactionsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return SmsTransactionResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        SmsTransactionResource::configureTable($table);

        return $table
            ->recordUrl(fn(SmsTransaction $record): string => SmsTransactionResource::getUrl('view', ['record' => $record]));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
