<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BankResource;
use App\Models\Bank;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BanksTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return BankResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        BankResource::configureTable($table);

        return $table
            ->recordUrl(fn(Bank $record): string => BankResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make()
                    ->label(__('New bank'))
                    ->icon('heroicon-o-building-office-2')
                    ->url(BankResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
