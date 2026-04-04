<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\LoanTiersResource;
use App\Models\LoanTier;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LoanTiersTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return LoanTiersResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        LoanTiersResource::configureTable($table);

        return $table
            ->recordUrl(fn (LoanTier $record): string => LoanTiersResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make()
                    ->url(LoanTiersResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
