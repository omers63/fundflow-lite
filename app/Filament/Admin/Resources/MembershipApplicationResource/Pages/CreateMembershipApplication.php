<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\MembershipApplication;
use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class CreateMembershipApplication extends CreateRecord
{
    protected static string $resource = MembershipApplicationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make()
                ->contained(false)
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('Account'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('Full Name'))
                                ->required()
                                ->maxLength(150),
                            Forms\Components\TextInput::make('email')
                                ->label(__('Email (login)'))
                                ->email()
                                ->required()
                                ->unique(User::class, 'email')
                                ->maxLength(255)
                                ->helperText(__('Creates a member login with pending status, same as the public apply flow.')),
                            Forms\Components\TextInput::make('password')
                                ->label(__('Password'))
                                ->password()
                                ->revealable()
                                ->required()
                                ->minLength(8)
                                ->same('password_confirmation'),
                            Forms\Components\TextInput::make('password_confirmation')
                                ->label(__('Confirm Password'))
                                ->password()
                                ->revealable()
                                ->required()
                                ->dehydrated(false),
                        ])->columns(2),

                    Tab::make(__('Details'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Section::make(__('Profile'))
                                ->icon('heroicon-o-identification')
                                ->schema([
                                    Forms\Components\Select::make('application_type')
                                        ->label(__('Application type'))
                                        ->options(MembershipApplication::applicationTypeOptions())
                                        ->required()
                                        ->default('new'),
                                    Forms\Components\Select::make('gender')
                                        ->options(MembershipApplication::genderOptions())
                                        ->placeholder(__('—')),
                                    Forms\Components\Select::make('marital_status')
                                        ->label(__('Marital status'))
                                        ->options(MembershipApplication::maritalStatusOptions())
                                        ->placeholder(__('—')),
                                    Forms\Components\DatePicker::make('membership_date')
                                        ->label(__('Membership date'))
                                        ->native(false),
                                ])->columns(2),

                            Section::make(__('Identity & address'))
                                ->icon('heroicon-o-map-pin')
                                ->schema([
                                    Forms\Components\TextInput::make('national_id')
                                        ->label(__('National ID'))
                                        ->required()
                                        ->maxLength(20),
                                    Forms\Components\DatePicker::make('date_of_birth')
                                        ->label(__('Date of Birth'))
                                        ->required()
                                        ->native(false)
                                        ->maxDate(now()),
                                    Forms\Components\TextInput::make('city')
                                        ->label(__('City'))
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\Textarea::make('address')
                                        ->label(__('Address'))
                                        ->required()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])->columns(3),

                            Section::make(__('Contact'))
                                ->icon('heroicon-o-phone')
                                ->schema([
                                    Forms\Components\TextInput::make('mobile_phone')
                                        ->label(__('Mobile phone'))
                                        ->tel()
                                        ->required()
                                        ->maxLength(30)
                                        ->helperText(__('Used for SMS and WhatsApp; also set as the user account phone.')),
                                    Forms\Components\TextInput::make('home_phone')
                                        ->label(__('Home phone'))
                                        ->tel()
                                        ->maxLength(30),
                                    Forms\Components\TextInput::make('work_phone')
                                        ->label(__('Work phone'))
                                        ->tel()
                                        ->maxLength(30),
                                ])->columns(3),

                            Section::make(__('Work & residency'))
                                ->icon('heroicon-o-building-office')
                                ->schema([
                                    Forms\Components\TextInput::make('work_place')
                                        ->label(__('Work place'))
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('residency_place')
                                        ->label(__('Residency place'))
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                ]),

                            Section::make(__('Employment'))
                                ->icon('heroicon-o-briefcase')
                                ->schema([
                                    Forms\Components\TextInput::make('occupation')
                                        ->maxLength(150),
                                    Forms\Components\TextInput::make('employer')
                                        ->maxLength(150),
                                    Forms\Components\TextInput::make('monthly_income')
                                        ->label(__('Monthly Income (SAR)'))
                                        ->numeric()
                                        ->prefix('SAR')
                                        ->minValue(0),
                                ])->columns(3),

                            Section::make(__('Banking'))
                                ->icon('heroicon-o-building-library')
                                ->schema([
                                    Forms\Components\TextInput::make('bank_account_number')
                                        ->label(__('Bank account number'))
                                        ->maxLength(50),
                                    Forms\Components\TextInput::make('iban')
                                        ->label(__('IBAN'))
                                        ->maxLength(34)
                                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
                                ])->columns(2),

                            Section::make(__('Next of kin'))
                                ->icon('heroicon-o-user-group')
                                ->schema([
                                    Forms\Components\TextInput::make('next_of_kin_name')
                                        ->label(__('Name'))
                                        ->required()
                                        ->maxLength(150),
                                    Forms\Components\TextInput::make('next_of_kin_phone')
                                        ->label(__('Phone'))
                                        ->tel()
                                        ->required()
                                        ->maxLength(30),
                                ])->columns(2),
                        ]),

                    Tab::make(__('Form Upload'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make()
                                ->description(new HtmlString(view('filament.admin.membership-application-form-upload-notice', [
                                    'downloadUrl' => route('downloads.membership-application-form-template'),
                                ])->render()))
                                ->schema([
                                    Forms\Components\FileUpload::make('application_form_path')
                                        ->label(__('Signed application form'))
                                        ->disk('public')
                                        ->directory('membership-applications')
                                        ->downloadable()
                                        ->openable()
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                        ->maxSize(10240)
                                        ->helperText(__('PDF or image, max 10 MB. Optional if you will add it later from edit.')),
                                ]),
                        ]),
                ]),
        ]);
    }

    protected function handleRecordCreation(array $data): MembershipApplication
    {
        return DB::transaction(function () use ($data) {
            $optionalString = static fn(array $d, string $key): ?string => filled($d[$key] ?? null) ? $d[$key] : null;

            $mobile = $data['mobile_phone'] ?? null;

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => filled($mobile) ? $mobile : null,
                'role' => 'member',
                'status' => 'pending',
                'password' => Hash::make($data['password']),
            ]);

            return MembershipApplication::create([
                'user_id' => $user->id,
                'application_type' => $data['application_type'] ?? 'new',
                'gender' => $optionalString($data, 'gender'),
                'marital_status' => $optionalString($data, 'marital_status'),
                'national_id' => $data['national_id'],
                'date_of_birth' => $data['date_of_birth'],
                'address' => $data['address'],
                'city' => $data['city'],
                'home_phone' => $optionalString($data, 'home_phone'),
                'work_phone' => $optionalString($data, 'work_phone'),
                'mobile_phone' => filled($mobile) ? $mobile : null,
                'occupation' => $data['occupation'] ?: null,
                'employer' => $data['employer'] ?: null,
                'work_place' => $optionalString($data, 'work_place'),
                'residency_place' => $optionalString($data, 'residency_place'),
                'monthly_income' => filled($data['monthly_income'] ?? null) ? $data['monthly_income'] : null,
                'bank_account_number' => $optionalString($data, 'bank_account_number'),
                'iban' => filled($data['iban'] ?? null) ? strtoupper((string) $data['iban']) : null,
                'membership_date' => filled($data['membership_date'] ?? null) ? $data['membership_date'] : null,
                'next_of_kin_name' => $data['next_of_kin_name'],
                'next_of_kin_phone' => $data['next_of_kin_phone'],
                'application_form_path' => $data['application_form_path'] ?? null,
                'status' => 'pending',
            ]);
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->getRecord();

        return Notification::make()
            ->title(__('Application created'))
            ->body(__('Pending application for :name has been created.', ['name' => $record->user->name]))
            ->success();
    }

    /** After a normal create, go to the applications list (not the record view). */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
