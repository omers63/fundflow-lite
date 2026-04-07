<?php

namespace App\Filament\Admin\Pages;

use App\Models\MembershipApplication;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;

class PublicMembershipSettingsPage extends Page
{
    protected string $view = 'filament.admin.pages.public-membership-settings';

    protected static ?string $navigationLabel = 'Public membership';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public function getTitle(): string
    {
        return 'Public membership';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save_settings')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->fillForm([
                    'max_pending_public' => Setting::maxPublicApplications(),
                ])
                ->schema([
                    Section::make('Application capacity')
                        ->description('Controls the public “Apply for membership” page at /apply. Login is unchanged: existing members and applicants can still sign in.')
                        ->schema([
                            Forms\Components\TextInput::make('max_pending_public')
                                ->label('Maximum applications (public apply)')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default(0)
                                ->helperText('Counts all membership applications (any status). When this number is reached, new visitors cannot submit the public form. Use 0 for no limit.'),
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::set('membership.max_pending_public', max(0, (int) $data['max_pending_public']));

                    Notification::make()
                        ->title('Public membership settings saved')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTotalApplicationsCount(): int
    {
        return MembershipApplication::query()->count();
    }
}
