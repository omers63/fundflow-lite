<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;

class AdminProfilePage extends Page
{
    protected string $view = 'filament.admin.pages.admin-profile';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'my-profile-page';

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('app.admin.my_profile');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'account';
    }

    public function getTitle(): string
    {
        return __('app.admin.my_profile');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function mount(): void
    {
        if (($u = auth()->user()) instanceof User) {
            auth()->setUser($u->fresh());
        }
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('edit_profile')
                ->label(__('app.admin.edit_profile'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn() => EditAdminProfilePage::getUrl())
                ->color('primary'),
        ];
    }
}
