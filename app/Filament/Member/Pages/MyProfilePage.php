<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
        return __('app.nav.group.account');
    }

    public function getTitle(): string
    {
        return __('app.member.my_profile');
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

            Action::make('update_phone')
                ->label(__('app.member.update_phone'))
                ->icon('heroicon-o-phone')
                ->color('info')
                ->fillForm(['phone' => auth()->user()?->phone])
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app.field.phone'))
                        ->tel()
                        ->required()
                        ->maxLength(50)
                        ->helperText(__('app.member.phone_helper')),
                ])
                ->action(function (array $data): void {
                    auth()->user()->update(['phone' => $data['phone']]);
                    Notification::make()->title(__('app.member.phone_updated'))->success()->send();
                }),

            Action::make('change_password')
                ->label(__('app.member.change_password'))
                ->icon('heroicon-o-lock-closed')
                ->color('primary')
                ->schema([
                    Forms\Components\TextInput::make('current_password')
                        ->label(__('app.member.current_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->rules([
                            fn() => function (string $attribute, mixed $value, \Closure $fail) {
                                if (!Hash::check($value, auth()->user()->password)) {
                                    $fail(__('app.member.current_password_incorrect'));
                                }
                            },
                        ]),
                    Forms\Components\TextInput::make('password')
                        ->label(__('app.member.new_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->rules([Password::min(8)->mixedCase()->numbers()])
                        ->helperText(__('app.member.new_password_helper')),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label(__('app.member.confirm_new_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->same('password'),
                ])
                ->action(function (array $data): void {
                    auth()->user()->update(['password' => Hash::make($data['password'])]);
                    Notification::make()
                        ->title(__('app.member.password_changed'))
                        ->body(__('app.member.password_changed_body'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
