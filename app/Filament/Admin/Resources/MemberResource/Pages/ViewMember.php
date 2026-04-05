<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberAccountStatsWidget;
use App\Filament\Admin\Widgets\MemberActivityWidget;
use App\Filament\Admin\Widgets\MemberProfileWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    public function getSubheading(): ?string
    {
        return 'Full member profile — financial standing, activity, and history.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MemberAccountStatsWidget::class,
            MemberProfileWidget::class,
            MemberActivityWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getWidgetsData(): array
    {
        return [
            'record' => $this->record,
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->unsetRelation('user');
        $this->record->load('user');
        $app = $this->record->latestMembershipApplication();

        if ($app?->membership_date) {
            $data['joined_at'] = $app->membership_date->toDateString();
        }

        return $data;
    }
}
