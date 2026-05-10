<?php

namespace App\Filament\Admin\Resources\SmsImportTemplateResource\Pages;

use App\Filament\Admin\Resources\SmsImportTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListSmsImportTemplates extends ListRecords
{
    protected static string $resource = SmsImportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
