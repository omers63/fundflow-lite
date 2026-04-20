<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Pages\LoanQueuePage;
use App\Filament\Admin\Resources\LoanResource;
use App\Filament\Admin\Widgets\LoanStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderWidgets(): array
    {
        return [LoanStatsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getSubheading(): ?string
    {
        return 'Track the full lifecycle of every loan — from application through disbursement to settlement.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loanQueue')
                ->label('Loan Queue')
                ->icon('heroicon-o-queue-list')
                ->url(LoanQueuePage::getUrl())
                ->color('primary'),
        ];
    }
}
