<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Filament\Admin\Widgets\ApplicationStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipApplications extends ListRecords
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ApplicationStatsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return 'Review new membership applications, track approval rates, and manage the onboarding pipeline.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus-circle'),
        ];
    }
}
