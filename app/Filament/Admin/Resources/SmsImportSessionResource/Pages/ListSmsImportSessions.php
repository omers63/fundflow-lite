<?php

namespace App\Filament\Admin\Resources\SmsImportSessionResource\Pages;

use App\Filament\Admin\Resources\SmsImportSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListSmsImportSessions extends ListRecords
{
    protected static string $resource = SmsImportSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
