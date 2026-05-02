<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;

class MyProfilePage extends Page
{
    protected string $view = 'filament.member.pages.my-profile';

    protected static ?string $navigationLabel = 'My Profile';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('app.member.my_profile');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'account';
    }

    public function getTitle(): string
    {
        return __('app.member.my_profile');
    }

    public function mount(): void
    {
        if (($u = auth()->user()) instanceof User) {
            auth()->setUser($u->fresh());
        }
    }

    protected function currentMember(): ?Member
    {
        return Member::where('user_id', auth()->id())->with(['user', 'accounts'])->first();
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('download_certificate')
                ->label(__('app.member.download_certificate'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('member.certificate'))
                ->openUrlInNewTab(),

            Action::make('edit_profile')
                ->label(__('app.member.edit_profile'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn() => EditMyProfilePage::getUrl())
                ->color('primary'),
        ];
    }
}
