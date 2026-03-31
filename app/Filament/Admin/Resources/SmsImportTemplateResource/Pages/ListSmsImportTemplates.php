<?php

namespace App\Filament\Admin\Resources\SmsImportTemplateResource\Pages;

use App\Filament\Admin\Resources\SmsImportTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSmsImportTemplates extends ListRecords
{
    protected static string $resource = SmsImportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
