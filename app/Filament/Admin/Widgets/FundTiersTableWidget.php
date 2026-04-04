<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\FundTiersResource;
use App\Models\FundTier;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FundTiersTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return FundTiersResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        FundTiersResource::configureTable($table);

        return $table
            ->recordUrl(fn (FundTier $record): string => FundTiersResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make()
                    ->url(FundTiersResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
