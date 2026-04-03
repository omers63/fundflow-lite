<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipApplications extends ListRecords
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
