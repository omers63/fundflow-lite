<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MemberResource\Concerns\UsesAlpineRelationManagerTabs;
use App\Filament\Admin\Widgets\MemberAccountStatsWidget;
use App\Filament\Admin\Widgets\MemberActivityWidget;
use App\Filament\Admin\Widgets\MemberProfileWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    use UsesAlpineRelationManagerTabs;

    protected static string $resource = MemberResource::class;

    /**
     * Merge the read-only member form into the first tab with relation managers.
     * Tab switching uses Alpine ({@see UsesAlpineRelationManagerTabs}) because Livewire
     * $set('activeRelationManager') + #[Url] often fails to update these tabs in the DOM.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Membershop';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

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
