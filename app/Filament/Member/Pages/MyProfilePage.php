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

    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.account');
    }

    public function getTitle(): string
    {
        return 'My Profile';
    }

    protected function currentMember(): ?Member
    {
        return Member::where('user_id', auth()->id())->with(['user', 'accounts'])->first();
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('update_phone')
                ->label('Update Phone')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->fillForm(['phone' => auth()->user()?->phone])
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->required()
                        ->maxLength(50)
                        ->helperText('Used for SMS and WhatsApp notifications.'),
                ])
                ->action(function (array $data): void {
                    auth()->user()->update(['phone' => $data['phone']]);
                    Notification::make()->title('Phone updated')->success()->send();
                }),

            Action::make('change_password')
                ->label('Change Password')
                ->icon('heroicon-o-lock-closed')
                ->color('primary')
                ->schema([
                    Forms\Components\TextInput::make('current_password')
                        ->label('Current Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rules([
                            fn() => function (string $attribute, mixed $value, \Closure $fail) {
                                if (!Hash::check($value, auth()->user()->password)) {
                                    $fail('Your current password is incorrect.');
                                }
                            },
                        ]),
                    Forms\Components\TextInput::make('password')
                        ->label('New Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rules([Password::min(8)->mixedCase()->numbers()])
                        ->helperText('Minimum 8 characters, mix of upper/lower case and numbers.'),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->same('password'),
                ])
                ->action(function (array $data): void {
                    auth()->user()->update(['password' => Hash::make($data['password'])]);
                    Notification::make()
                        ->title('Password changed')
                        ->body('Your password has been updated. Use the new password on your next login.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
