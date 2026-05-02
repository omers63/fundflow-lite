<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\User;
use App\Services\HouseholdAccessService;
use App\Support\StorageFilename;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property-read Schema $form
 */
class EditMyProfilePage extends Page
{
    protected static ?string $slug = 'edit-profile';

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('app.member.edit_profile');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'account';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('app.member.edit_profile');
    }

    public function getSubheading(): ?string
    {
        return __('app.member.edit_profile_subheading');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->member !== null;
    }

    protected function currentMember(): ?Member
    {
        return Member::where('user_id', auth()->id())->first();
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $user = auth()->user();
        $member = $this->currentMember();
        if (!$user instanceof User) {
            return;
        }

        $this->form->fill([
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'preferred_locale' => in_array((string) ($user->preferred_locale), ['en', 'ar'], true)
                ? $user->preferred_locale
                : config('app.locale', 'en'),
            'avatar' => filled($user->avatar_path)
                ? (User::normalizePublicDiskRelativePath($user->avatar_path) ?? $user->avatar_path)
                : null,
            'remove_avatar' => false,
            'set_parent_pin' => $member?->parent_id === null,
            'current_password' => null,
            'new_password' => null,
            'new_password_confirmation' => null,
            'pin' => null,
            'pin_confirmation' => null,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.member.profile_edit_section'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Full Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
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
                            ->columnSpanFull()
                            ->helperText(__('Dependents that use a unique login email become separated (direct login enabled). Using household email rejoins and disables direct login.')),
                        Forms\Components\Select::make('preferred_locale')
                            ->label(__('app.member.preferred_language'))
                            ->helperText(__('app.member.preferred_language_helper'))
                            ->options([
                                'en' => __('English'),
                                'ar' => __('Arabic'),
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\FileUpload::make('avatar')
                            ->label(__('Avatar'))
                            ->avatar()
                            ->alignCenter()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                                return StorageFilename::make('avatar', $file->getClientOriginalName(), [
                                    auth()->user()?->name,
                                    auth()->id(),
                                ]);
                            })
                            ->maxSize(2048)
                            ->columnSpanFull(),
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
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->fullWidth(false)
                    ->sticky(false)
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('cancel')
                ->label(__('Cancel'))
                ->url(fn() => MyProfilePage::getUrl())
                ->color('gray'),
        ];
    }

    public function save(): void
    {
        $member = $this->currentMember();
        $user = auth()->user();
        if (!$member || !$user instanceof User) {
            return;
        }

        $data = $this->form->getState();

        $oldAvatarPath = $user->avatar_path;

        $rawAvatar = $data['avatar'] ?? null;
        if (is_array($rawAvatar)) {
            $rawAvatar = Arr::first(array_filter(Arr::wrap($rawAvatar), fn($v) => filled($v)));
        }
        $newAvatarPath = filled($rawAvatar) ? (string) $rawAvatar : null;
        $newAvatarPath = User::normalizePublicDiskRelativePath($newAvatarPath) ?? $newAvatarPath;

        // Incomplete Livewire upload — keep previous avatar; do not use `exists()` here (path can be valid
        // while checks fail due to timing or normalization differences after Filament stores the file).
        if ($newAvatarPath !== null && str_contains($newAvatarPath, 'livewire-tmp')) {
            $newAvatarPath = User::normalizePublicDiskRelativePath((string) $oldAvatarPath) ?? $oldAvatarPath;
        }

        $preferredLocale = $data['preferred_locale'] ?? $user->preferred_locale;
        if (!in_array((string) $preferredLocale, ['en', 'ar'], true)) {
            $preferredLocale = $user->preferred_locale;
        }

        $user->update([
            'name' => (string) $data['name'],
            'phone' => (string) $data['phone'],
            'preferred_locale' => (string) $preferredLocale,
        ]);

        session()->put('locale', $user->fresh()->preferredLocale());

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
            if (filled($oldAvatarPath)) {
                $del = User::normalizePublicDiskRelativePath((string) $oldAvatarPath) ?? $oldAvatarPath;
                Storage::disk('public')->delete($del);
            }
            $user->forceFill(['avatar_path' => null])->save();
        } elseif (filled($newAvatarPath)) {
            $user->forceFill(['avatar_path' => $newAvatarPath])->save();
            $oldNorm = User::normalizePublicDiskRelativePath((string) ($oldAvatarPath ?? ''));
            if (filled($oldAvatarPath) && $oldNorm !== $newAvatarPath) {
                Storage::disk('public')->delete($oldNorm ?? $oldAvatarPath);
            }
        }

        $user->refresh();
        if (auth()->id() === $user->id) {
            auth()->setUser($user);
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

        $url = MyProfilePage::getUrl();
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            MyProfilePage::getRouteName(),
        ];
    }
}
