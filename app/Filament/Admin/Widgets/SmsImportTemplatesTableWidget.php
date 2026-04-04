<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SmsImportTemplateResource;
use App\Models\SmsImportTemplate;
use Filament\Actions\CreateAction;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class SmsImportTemplatesTableWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return SmsImportTemplateResource::getEloquentQuery();
    }

    public function table(Table $table): Table
    {
        SmsImportTemplateResource::configureTable($table);

        return $table
            ->recordUrl(fn(SmsImportTemplate $record): string => SmsImportTemplateResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make()
                    ->label('New SMS template')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->url(SmsImportTemplateResource::getUrl('create')),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->heading(null)
            ->paginationMode(PaginationMode::Default);
    }
}
