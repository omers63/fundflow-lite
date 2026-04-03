<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MembershipApplicationResource\Pages;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Notifications\MembershipApprovedNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\MemberNumberService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) MembershipApplication::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Applicant account')
                ->description('Login identity is on the user account. Name and email are read-only. Mobile phone is stored on the application and synced to the user for SMS/WhatsApp.')
                ->schema([
                    Forms\Components\TextInput::make('_display_user_name')
                        ->label('Full Name')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('_display_user_email')
                        ->label('Email (login)')
                        ->disabled()
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

            Section::make('Submitted application — identity & address')
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
                        ->helperText('Used for SMS and WhatsApp; also updates the user login account.'),
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

            Section::make('Submitted application — employment')
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

            Section::make('Submitted application — next of kin')
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
                        ->helperText('PDF or image, max 10 MB. Replace the file if the applicant sends a corrected document.'),
                ]),

            Section::make('Review status')
                ->description('Use Approve / Reject on the list to change status. You can edit the rejection reason below for corrections.')
                ->schema([
                    Forms\Components\TextInput::make('status')
                        ->label('Current status')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default => $state ?? '—',
                        }),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection reason')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobile_phone')
                    ->label('Mobile'),
                Tables\Columns\TextColumn::make('application_type')
                    ->label('Type')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        return MembershipApplication::applicationTypeOptions()[$state] ?? $state;
                    })
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MembershipApplication $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Membership Application')
                    ->modalDescription('Are you sure you want to approve this application? The applicant will be notified via email, SMS, and WhatsApp.')
                    ->action(function (MembershipApplication $record) {
                        $memberNumberService = app(MemberNumberService::class);
                        $memberNumber = $memberNumberService->generate();

                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        $record->user->update(['status' => 'approved']);

                        Member::create([
                            'user_id' => $record->user_id,
                            'member_number' => $memberNumber,
                            'joined_at' => now()->toDateString(),
                            'status' => 'active',
                        ]);

                        try {
                            $record->user->notify(new MembershipApprovedNotification($memberNumber));
                        } catch (\Throwable $e) {
                            // notifications are best-effort; log silently
                        }

                        Notification::make()
                            ->title('Application Approved')
                            ->body("Member {$record->user->name} has been approved with number {$memberNumber}.")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MembershipApplication $record) => $record->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for rejecting this application...'),
                    ])
                    ->action(function (MembershipApplication $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        $record->user->update(['status' => 'rejected']);

                        try {
                            $record->user->notify(new MembershipRejectedNotification($data['rejection_reason']));
                        } catch (\Throwable $e) {
                            // notifications are best-effort
                        }

                        Notification::make()
                            ->title('Application Rejected')
                            ->body("Application for {$record->user->name} has been rejected.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembershipApplications::route('/'),
            'create' => Pages\CreateMembershipApplication::route('/create'),
            'view' => Pages\ViewMembershipApplication::route('/{record}'),
            'edit' => Pages\EditMembershipApplication::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}
