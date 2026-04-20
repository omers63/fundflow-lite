<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberRecordInsightsWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return __('Full member profile — financial standing, activity, and history.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MemberRecordInsightsWidget::class,
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
