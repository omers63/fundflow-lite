<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MembershipApplicationResource\Pages;
use App\Filament\Admin\Widgets\ApplicationStatsWidget;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\MemberNumberService;
use App\Services\MembershipApplicationImportService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

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
            Tabs::make()
                ->contained(false)
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Account')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Section::make('Applicant account')
                                ->icon('heroicon-o-user')
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
                        ]),

                    Tab::make('Details')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Section::make('Profile')
                                ->icon('heroicon-o-identification')
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

                            Section::make('Identity & address')
                                ->icon('heroicon-o-map-pin')
                                ->schema([
                                    Forms\Components\TextInput::make('national_id')
                                        ->label('National ID')
                                        ->required()
                                        ->maxLength(20),
                                    Forms\Components\DatePicker::make('date_of_birth')
                                        ->label('Date of Birth')
                                        ->required()
                                        ->native(false)
                                        ->maxDate(now()),
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

                            Section::make('Contact')
                                ->icon('heroicon-o-phone')
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
                                ->icon('heroicon-o-building-office')
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

                            Section::make('Employment')
                                ->icon('heroicon-o-briefcase')
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

                            Section::make('Banking')
                                ->icon('heroicon-o-building-library')
                                ->schema([
                                    Forms\Components\TextInput::make('bank_account_number')
                                        ->label('Bank account number')
                                        ->maxLength(50),
                                    Forms\Components\TextInput::make('iban')
                                        ->label('IBAN')
                                        ->maxLength(34)
                                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
                                ])->columns(2),

                            Section::make('Next of kin')
                                ->icon('heroicon-o-user-group')
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

                            Section::make('Review status')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->description('Use Approve / Reject on the list to change status. You can edit the rejection reason below for corrections.')
                                ->schema([
                                    Forms\Components\TextInput::make('status')
                                        ->label('Current status')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->formatStateUsing(fn(?string $state): string => match ($state) {
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
                        ]),

                    Tab::make('Form Upload')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make()
                                ->description(new HtmlString(view('filament.admin.membership-application-form-upload-notice', [
                                    'downloadUrl' => route('downloads.membership-application-form-template'),
                                ])->render()))
                                ->schema([
                                    Forms\Components\FileUpload::make('application_form_path')
                                        ->label('Signed application form')
                                        ->disk('public')
                                        ->directory('membership-applications')
                                        ->downloadable()
                                        ->openable()
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                        ->maxSize(10240)
                                        ->helperText('PDF or image, max 10 MB. Replace the file if the applicant sends a corrected document.'),
                                ]),
                        ]),
                ]),
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
                    ->color(fn(string $state) => match ($state) {
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
                Tables\Filters\SelectFilter::make('application_type')
                    ->options(MembershipApplication::applicationTypeOptions()),
                Tables\Filters\SelectFilter::make('gender')
                    ->options(MembershipApplication::genderOptions()),
                Tables\Filters\SelectFilter::make('marital_status')
                    ->label('Marital status')
                    ->options(MembershipApplication::maritalStatusOptions()),
                Tables\Filters\Filter::make('city')
                    ->schema([
                        Forms\Components\TextInput::make('value')->label('City contains'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn(Builder $q) => $q->where('city', 'like', '%' . $data['value'] . '%')
                    )),
                Tables\Filters\TernaryFilter::make('reviewed')
                    ->label('Reviewed')
                    ->trueLabel('Reviewed')
                    ->falseLabel('Not reviewed')
                    ->queries(
                        true: fn(Builder $q) => $q->whereNotNull('reviewed_at'),
                        false: fn(Builder $q) => $q->whereNull('reviewed_at'),
                    ),
                Tables\Filters\SelectFilter::make('reviewed_by')
                    ->label('Reviewer')
                    ->options(fn() => User::query()->whereIn('id', MembershipApplication::query()->whereNotNull('reviewed_by')->pluck('reviewed_by'))->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\Filter::make('created_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Applied from'),
                        Forms\Components\DatePicker::make('until')->label('Applied until'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn(Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('importApplications')
                    ->label('Import Applications')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn(): bool => static::canCreate() || (bool) auth()->user()?->can('Update:MembershipApplication'))
                    ->modalHeading('Import applications from CSV')
                    ->modalDescription(fn(): HtmlString => new HtmlString(
                        view('filament.admin.membership-application-import-csv-help')->render()
                    ))
                    ->modalWidth('2xl')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('membership-application-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                        Forms\Components\TextInput::make('default_password')
                            ->label('Default password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->helperText('Used when the password column is empty or shorter than 8 characters. Applicants should change it after first login.'),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $relative = $data['csv_file'];
                        $fullPath = Storage::disk('local')->path($relative);

                        try {
                            $result = app(MembershipApplicationImportService::class)->import($fullPath, $data['default_password']);
                        } finally {
                            Storage::disk('local')->delete($relative);
                        }

                        $body = "Created: {$result['created']} · Skipped: {$result['skipped']} · Failed: {$result['failed']}";

                        if ($result['errors'] !== []) {
                            $preview = implode("\n", array_slice($result['errors'], 0, 8));
                            if (count($result['errors']) > 8) {
                                $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                            }
                            $body .= "\n\n" . $preview;
                        }

                        Notification::make()
                            ->title('Application import finished')
                            ->body($body)
                            ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                            ->persistent()
                            ->send();

                        static::dispatchApplicationStatsRefresh($livewire);
                    }),
                CreateAction::make()
                    ->label('New Application')
                    ->icon('heroicon-o-plus-circle')
                    ->url(static::getUrl('create'))
                    ->visible(fn(): bool => static::canCreate()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(MembershipApplication $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Membership Application')
                    ->modalDescription('Are you sure you want to approve this application? The applicant will be notified via email, SMS, and WhatsApp.')
                    ->action(function (MembershipApplication $record, Component $livewire) {
                        $memberNumber = static::approvePendingApplication($record);

                        Notification::make()
                            ->title('Application Approved')
                            ->body("Member {$record->user->name} has been approved with number {$memberNumber}.")
                            ->success()
                            ->send();

                        static::dispatchApplicationStatsRefresh($livewire);
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(MembershipApplication $record) => $record->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for rejecting this application...'),
                    ])
                    ->action(function (MembershipApplication $record, array $data, Component $livewire) {
                        static::rejectPendingApplication($record, $data['rejection_reason']);

                        Notification::make()
                            ->title('Application Rejected')
                            ->body("Application for {$record->user->name} has been rejected.")
                            ->warning()
                            ->send();

                        static::dispatchApplicationStatsRefresh($livewire);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve selected applications')
                        ->modalDescription('Each selected row that is still pending will be approved: a member record and member number are created, the login is activated, and the applicant is notified (email, SMS, and WhatsApp where configured). Rows that are not pending are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, Component $livewire): void {
                            $pending = $records->filter(fn(MembershipApplication $r) => $r->status === 'pending')->values();
                            $ignored = $records->count() - $pending->count();

                            $approved = 0;
                            $failed = 0;

                            foreach ($pending as $record) {
                                try {
                                    DB::transaction(function () use ($record) {
                                        /** @var MembershipApplication $fresh */
                                        $fresh = MembershipApplication::query()->whereKey($record->getKey())->lockForUpdate()->first();
                                        if ($fresh === null || $fresh->status !== 'pending') {
                                            return;
                                        }
                                        static::approvePendingApplication($fresh);
                                    });
                                    if ($record->fresh()->status === 'approved') {
                                        $approved++;
                                    }
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = "Approved: {$approved}. Failed: {$failed}. Skipped (not pending): {$ignored}.";

                            Notification::make()
                                ->title('Bulk approve finished')
                                ->body($body)
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();

                            static::dispatchApplicationStatsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason for rejection')
                                ->required()
                                ->rows(3)
                                ->placeholder('This message is saved on each application and sent to every selected pending applicant.'),
                        ])
                        ->modalHeading('Reject selected applications')
                        ->modalDescription('The reason below is applied to each selected row that is still pending, stored on the record, and included in notifications. Rows that are not pending are skipped.')
                        ->authorizeIndividualRecords('update')
                        ->action(function (EloquentCollection $records, array $data, Component $livewire): void {
                            $reason = $data['rejection_reason'];
                            $pending = $records->filter(fn(MembershipApplication $r) => $r->status === 'pending')->values();
                            $ignored = $records->count() - $pending->count();

                            $rejected = 0;
                            $failed = 0;

                            foreach ($pending as $record) {
                                try {
                                    DB::transaction(function () use ($record, $reason) {
                                        /** @var MembershipApplication $fresh */
                                        $fresh = MembershipApplication::query()->whereKey($record->getKey())->lockForUpdate()->first();
                                        if ($fresh === null || $fresh->status !== 'pending') {
                                            return;
                                        }
                                        static::rejectPendingApplication($fresh, $reason);
                                    });
                                    if ($record->fresh()->status === 'rejected') {
                                        $rejected++;
                                    }
                                } catch (\Throwable $e) {
                                    $failed++;
                                    report($e);
                                }
                            }

                            $body = "Rejected: {$rejected}. Failed: {$failed}. Skipped (not pending): {$ignored}.";

                            Notification::make()
                                ->title('Bulk reject finished')
                                ->body($body)
                                ->color($failed > 0 ? 'danger' : 'warning')
                                ->persistent()
                                ->send();

                            static::dispatchApplicationStatsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->after(function (Component $livewire): void {
                            static::dispatchApplicationStatsRefresh($livewire);
                        }),
                    RestoreBulkAction::make()
                        ->after(function (Component $livewire): void {
                            static::dispatchApplicationStatsRefresh($livewire);
                        }),
                    ForceDeleteBulkAction::make()
                        ->after(function (Component $livewire): void {
                            static::dispatchApplicationStatsRefresh($livewire);
                        }),
                ]),
            ]);
    }

    protected static function dispatchApplicationStatsRefresh(?Component $livewire): void
    {
        // Child header widgets are separate Livewire roots; a bare dispatch() only bubbles
        // from the page element upward, so it never reaches nested widgets.
        $livewire?->dispatch('refresh-application-stats')->to(ApplicationStatsWidget::class);
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    /**
     * Approve a pending application: updates application and user, creates member, sends best-effort notifications.
     *
     * @throws \Throwable
     */
    public static function approvePendingApplication(MembershipApplication $record): string
    {
        $record->loadMissing('user');

        $memberNumber = app(MemberNumberService::class)->generate();

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
            // notifications are best-effort
        }

        return $memberNumber;
    }

    public static function rejectPendingApplication(MembershipApplication $record, string $rejectionReason): void
    {
        $record->loadMissing('user');

        $record->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        $record->user->update(['status' => 'rejected']);

        try {
            $record->user->notify(new MembershipRejectedNotification($rejectionReason));
        } catch (\Throwable $e) {
            // notifications are best-effort
        }
    }
}
