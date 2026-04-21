<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BankTransactionResource;
use App\Models\BankTransaction;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BankTransactionsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return BankTransactionResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        BankTransactionResource::configureTable($table);

        return $table
            ->recordUrl(fn(BankTransaction $record): string => BankTransactionResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                CreateAction::make()
                    ->label(__('New transaction'))
                    ->icon('heroicon-o-plus-circle')
                    ->url(BankTransactionResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
