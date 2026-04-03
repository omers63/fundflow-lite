<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use App\Models\MembershipApplication;
use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateMembershipApplication extends CreateRecord
{
    protected static string $resource = MembershipApplicationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Applicant account')
                ->description('Creates a member login with pending status, same as the public apply flow.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('email')
                        ->label('Email (login)')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->same('password_confirmation'),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label('Confirm Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ])->columns(2),

            Section::make('Application type & profile')
                ->schema([
                    Forms\Components\Select::make('application_type')
                        ->label('Application type')
                        ->options(MembershipApplication::applicationTypeOptions())
                        ->required()
                        ->default('new'),
                    Forms\Components\Select::make('gender')
                        ->options(MembershipApplication::genderOptions())
                        ->placeholder('—'),
                    Forms\Components\Select::make('marital_status')
                        ->label('Marital status')
                        ->options(MembershipApplication::maritalStatusOptions())
                        ->placeholder('—'),
                    Forms\Components\DatePicker::make('membership_date')
                        ->label('Membership date')
                        ->native(false),
                ])->columns(2),

            Section::make('Application — identity & address')
                ->schema([
                    Forms\Components\TextInput::make('national_id')
                        ->label('National ID')
                        ->required()
                        ->maxLength(20),
                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->required()
                        ->native(false)
                        ->maxDate(now()->subYears(18)),
                    Forms\Components\TextInput::make('city')
                        ->label('City')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Textarea::make('address')
                        ->label('Address')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(3),

            Section::make('Contact numbers')
                ->schema([
                    Forms\Components\TextInput::make('mobile_phone')
                        ->label('Mobile phone')
                        ->tel()
                        ->required()
                        ->maxLength(30)
                        ->helperText('Used for SMS and WhatsApp; also set as the user account phone.'),
                    Forms\Components\TextInput::make('home_phone')
                        ->label('Home phone')
                        ->tel()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('work_phone')
                        ->label('Work phone')
                        ->tel()
                        ->maxLength(30),
                ])->columns(3),

            Section::make('Work & residency')
                ->schema([
                    Forms\Components\TextInput::make('work_place')
                        ->label('Work place')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('residency_place')
                        ->label('Residency place')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('Banking')
                ->schema([
                    Forms\Components\TextInput::make('bank_account_number')
                        ->label('Bank account number')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('iban')
                        ->label('IBAN')
                        ->maxLength(34)
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
                ])->columns(2),

            Section::make('Application — employment')
                ->schema([
                    Forms\Components\TextInput::make('occupation')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('employer')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('monthly_income')
                        ->label('Monthly Income (SAR)')
                        ->numeric()
                        ->prefix('SAR')
                        ->minValue(0),
                ])->columns(3),

            Section::make('Application — next of kin')
                ->schema([
                    Forms\Components\TextInput::make('next_of_kin_name')
                        ->label('Name')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('next_of_kin_phone')
                        ->label('Phone')
                        ->tel()
                        ->required()
                        ->maxLength(30),
                ])->columns(2),

            Section::make('Application document')
                ->schema([
                    Forms\Components\FileUpload::make('application_form_path')
                        ->label('Uploaded form')
                        ->disk('public')
                        ->directory('membership-applications')
                        ->downloadable()
                        ->openable()
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->helperText('PDF or image, max 10 MB. Optional if you will add it later from edit.'),
                ]),
        ]);
    }

    protected function handleRecordCreation(array $data): MembershipApplication
    {
        return DB::transaction(function () use ($data) {
            $optionalString = static fn (array $d, string $key): ?string => filled($d[$key] ?? null) ? $d[$key] : null;

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
            ->title('Application created')
            ->body("Pending application for {$record->user->name} has been created.")
            ->success();
    }
}
