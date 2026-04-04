<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BankImportTemplateResource;
use App\Models\BankImportTemplate;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BankImportTemplatesTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return BankImportTemplateResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        BankImportTemplateResource::configureTable($table);

        return $table
            ->recordUrl(fn(BankImportTemplate $record): string => BankImportTemplateResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make()->url(BankImportTemplateResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
