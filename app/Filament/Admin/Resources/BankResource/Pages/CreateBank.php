<?php

namespace App\Filament\Admin\Resources\BankResource\Pages;

use App\Filament\Admin\Resources\BankResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateBank extends CreateRecord
{
    protected static string $resource = BankResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
