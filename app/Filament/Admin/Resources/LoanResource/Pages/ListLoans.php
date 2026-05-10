<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Pages\LoanQueuePage;
use App\Filament\Admin\Resources\LoanResource;
use App\Filament\Admin\Widgets\LoanStatsWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    public function getTitle(): string
    {
        return __('Loans');
    }

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
        return __('app.loan.list_subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loanQueue')
                ->label(__('app.loan.queue'))
                ->icon('heroicon-o-queue-list')
                ->url(LoanQueuePage::getUrl())
                ->color('primary'),
        ];
    }
}
