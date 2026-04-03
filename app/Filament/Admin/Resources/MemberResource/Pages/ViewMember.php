<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberAccountStatsWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [MemberAccountStatsWidget::class];
    }

    protected function getWidgetsData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
