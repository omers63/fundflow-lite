<?php

namespace App\Filament\Admin\Resources\ContributionResource\Pages;

use App\Filament\Admin\Pages\ContributionCyclePage;
use App\Filament\Admin\Resources\ContributionResource;
use App\Filament\Admin\Widgets\ContributionStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContributions extends ListRecords
{
    protected static string $resource = ContributionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ContributionStatsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return 'Monitor monthly contributions, compliance rates, and fund inflows across all active members.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('contributionCycle')
                ->label('Contribution cycle')
                ->icon('heroicon-o-arrow-path')
                ->url(ContributionCyclePage::getUrl())
                ->color('primary'),
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
