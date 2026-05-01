<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Services\HouseholdAccessService;
use App\Support\StorageFilename;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

            Action::make('update_profile')
                ->label(__('Update Profile'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->fillForm(function (): array {
                    $member = $this->currentMember();
                    $user = auth()->user();

                    return [
                        'name' => $user?->name,
                        'phone' => $user?->phone,
                        'email' => $user?->email,
                        'avatar' => $user?->avatar_path,
                        'remove_avatar' => false,
                        'set_parent_pin' => $member?->parent_id === null,
                    ];
                })
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Full Name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app.field.phone'))
                        ->tel()
                        ->required()
                        ->maxLength(50)
                        ->helperText(__('app.member.phone_helper')),
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email Address'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('Dependents that use a unique login email become separated (direct login enabled). Using household email rejoins and disables direct login.')),
                    Forms\Components\FileUpload::make('avatar')
                        ->label(__('Avatar'))
                        ->image()
                        ->disk('public')
                        ->directory('avatars')
                        ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                            return StorageFilename::make('avatar', $file->getClientOriginalName(), [
                                auth()->user()?->name,
                                auth()->id(),
                            ]);
                        })
                        ->imageEditor()
                        ->maxSize(2048),
                    Forms\Components\Toggle::make('remove_avatar')
                        ->label(__('Remove Avatar'))
                        ->helperText(__('Enable to remove existing avatar image.')),
                    Forms\Components\TextInput::make('current_password')
                        ->label(__('app.member.current_password'))
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->helperText(__('Required only when changing password.')),
                    Forms\Components\TextInput::make('new_password')
                        ->label(__('app.member.new_password'))
                        ->password()
                        ->revealable()
                        ->rules([Password::min(8)->mixedCase()->numbers()])
                        ->dehydrated(false)
                        ->helperText(__('Leave blank to keep current password.')),
                    Forms\Components\TextInput::make('new_password_confirmation')
                        ->label(__('app.member.confirm_new_password'))
                        ->password()
                        ->revealable()
                        ->same('new_password')
                        ->dehydrated(false),
                    Forms\Components\Toggle::make('set_parent_pin')
                        ->label(__('Set Parent PIN'))
                        ->visible(fn() => $this->currentMember()?->parent_id === null),
                    Forms\Components\TextInput::make('pin')
                        ->label(__('4-digit PIN'))
                        ->password()
                        ->rules(['nullable', 'digits:4'])
                        ->visible(fn($get) => (bool) $get('set_parent_pin')),
                    Forms\Components\TextInput::make('pin_confirmation')
                        ->label(__('Confirm PIN'))
                        ->password()
                        ->same('pin')
                        ->visible(fn($get) => (bool) $get('set_parent_pin')),
                ])
                ->action(function (array $data): void {
                    $member = $this->currentMember();
                    $user = auth()->user();
                    if (!$member || !$user) {
                        return;
                    }

                    $oldAvatarPath = $user->avatar_path;
                    $newAvatarPath = $data['avatar'] ?? null;

                    $user->update([
                        'name' => (string) $data['name'],
                        'phone' => (string) $data['phone'],
                    ]);

                    $newEmail = (string) $data['email'];
                    if ($newEmail !== (string) $user->email) {
                        try {
                            app(HouseholdAccessService::class)->updateMemberLoginEmail($member, $user, $newEmail);
                        } catch (\InvalidArgumentException) {
                            Notification::make()
                                ->title(__('Email already in use.'))
                                ->body(__('Choose a unique email, or use your household email to rejoin.'))
                                ->danger()
                                ->send();

                            return;
                        }
                    }

                    if ((bool) ($data['remove_avatar'] ?? false)) {
                        if (filled($user->avatar_path)) {
                            Storage::disk('public')->delete($user->avatar_path);
                        }
                        $user->update(['avatar_path' => null]);
                    } elseif (filled($newAvatarPath)) {
                        $user->update(['avatar_path' => (string) $newAvatarPath]);
                        if (filled($oldAvatarPath) && $oldAvatarPath !== $newAvatarPath) {
                            Storage::disk('public')->delete($oldAvatarPath);
                        }
                    }

                    $newPassword = (string) ($data['new_password'] ?? '');
                    if ($newPassword !== '') {
                        $currentPassword = (string) ($data['current_password'] ?? '');
                        if (!Hash::check($currentPassword, (string) $user->password)) {
                            Notification::make()
                                ->title(__('app.member.current_password_incorrect'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $user->update(['password' => Hash::make($newPassword)]);
                    }

                    $shouldSetPin = (bool) ($data['set_parent_pin'] ?? false);
                    $pin = (string) ($data['pin'] ?? '');
                    if ($member->parent_id === null && $shouldSetPin && $pin !== '') {
                        $member->update(['portal_pin' => Hash::make($pin)]);
                    }

                    Notification::make()->title(__('Profile updated successfully.'))->success()->send();
                }),

        ];
    }
}
