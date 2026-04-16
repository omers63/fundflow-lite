<?php

namespace App\Filament\Admin\Resources\ContributionResource\Pages;

use App\Filament\Admin\Pages\ContributionCyclePage;
use App\Filament\Admin\Resources\ContributionResource;
use App\Filament\Admin\Widgets\ContributionStatsWidget;
use App\Models\Contribution;
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
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $filename = 'contributions-' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, [
                            'id', 'member_number', 'member_name',
                            'month', 'year', 'period',
                            'amount', 'is_late', 'recorded_at',
                        ]);

                        Contribution::with('member.user')
                            ->orderByDesc('year')
                            ->orderByDesc('month')
                            ->orderBy('id')
                            ->each(function (Contribution $c) use ($handle) {
                                fputcsv($handle, [
                                    $c->id,
                                    $c->member?->member_number,
                                    $c->member?->user?->name,
                                    $c->month,
                                    $c->year,
                                    date('F', mktime(0, 0, 0, $c->month, 1)) . ' ' . $c->year,
                                    number_format((float) $c->amount, 2, '.', ''),
                                    $c->is_late ? 'Yes' : 'No',
                                    $c->created_at?->toDateTimeString(),
                                ]);
                            });

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),

            Actions\Action::make('contributionCycle')
                ->label('Run Contribution Cycle')
                ->icon('heroicon-o-arrow-path')
                ->url(ContributionCyclePage::getUrl())
                ->color('primary'),
        ];
    }
}
