<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MembershipApplicationResource\Pages;
use App\Filament\Admin\Widgets\ApplicationStatsWidget;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\AccountingService;
use App\Services\MemberNumberService;
use App\Support\PhoneDisplay;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
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
use Illuminate\Support\HtmlString;
use Livewire\Component;

class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Applications');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'membership';
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
                    Tab::make(__('Account'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Section::make(__('Applicant account'))
                                ->icon('heroicon-o-user')
                                ->description(__('Login identity is on the user account. Name and email are read-only. Mobile phone is stored on the application and synced to the user for SMS/WhatsApp.'))
                                ->schema([
                                    Forms\Components\TextInput::make('_display_user_name')
                                        ->label(__('Full Name'))
                                        ->disabled()
                                        ->dehydrated(false),
                                    Forms\Components\TextInput::make('_display_user_email')
                                        ->label(__('Email (login)'))
                                        ->disabled()
                                        ->dehydrated(false),
                                ])->columns(2),
                        ]),

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
                                        ->helperText(__('Used for SMS and WhatsApp; also updates the user login account.')),
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

                            Section::make(__('Review status'))
                                ->icon('heroicon-o-clipboard-document-check')
                                ->description(__('Use Approve / Reject on the list to change status. You can edit the rejection reason below for corrections.'))
                                ->schema([
                                    Forms\Components\TextInput::make('status')
                                        ->label(__('Current status'))
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->formatStateUsing(fn(?string $state): string => match ($state) {
                                            'pending' => __('Pending'),
                                            'approved' => __('Approved'),
                                            'rejected' => __('Rejected'),
                                            default => $state ? __($state) : __('—'),
                                        }),
                                    Forms\Components\Textarea::make('rejection_reason')
                                        ->label(__('Rejection reason'))
                                        ->rows(3)
                                        ->columnSpanFull(),
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
                                        ->helperText(__('PDF or image, max 10 MB. Replace the file if the applicant sends a corrected document.')),
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
                    ->label(__('Applicant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobile_phone')
                    ->label(__('Mobile'))
                    ->formatStateUsing(fn (?string $state): \Illuminate\Support\HtmlString => PhoneDisplay::toHtml($state)),
                Tables\Columns\TextColumn::make('application_type')
                    ->label(__('Type'))
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return __('—');
                        }

                        return MembershipApplication::applicationTypeOptions()[$state] ?? $state;
                    })
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('membership_fee_amount')
                    ->label(__('App fee (SAR)'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('membership_fee_transfer_reference')
                    ->label(__('Fee transfer ref'))
                    ->limit(24)
                    ->tooltip(fn($record) => $record->membership_fee_transfer_reference)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('membership_fee_posted_at')
                    ->label(__('Fee posted'))
                    ->dateTime('d M Y H:i')
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label(__('Applied'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')]),
                Tables\Filters\SelectFilter::make('application_type')
                    ->options(MembershipApplication::applicationTypeOptions()),
                Tables\Filters\SelectFilter::make('gender')
                    ->options(MembershipApplication::genderOptions()),
                Tables\Filters\SelectFilter::make('marital_status')
                    ->label(__('Marital status'))
                    ->options(MembershipApplication::maritalStatusOptions()),
                Tables\Filters\Filter::make('city')
                    ->schema([
                        Forms\Components\TextInput::make('value')->label(__('City contains')),
                    ])
                    ->query(fn(Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn(Builder $q) => $q->where('city', 'like', '%' . $data['value'] . '%')
                    )),
                Tables\Filters\TernaryFilter::make('reviewed')
                    ->label(__('Reviewed'))
                    ->trueLabel(__('Reviewed'))
                    ->falseLabel(__('Not reviewed'))
                    ->queries(
                        true: fn(Builder $q) => $q->whereNotNull('reviewed_at'),
                        false: fn(Builder $q) => $q->whereNull('reviewed_at'),
                    ),
                Tables\Filters\SelectFilter::make('reviewed_by')
                    ->label(__('Reviewer'))
                    ->options(fn() => User::query()->whereIn('id', MembershipApplication::query()->whereNotNull('reviewed_by')->pluck('reviewed_by'))->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\Filter::make('created_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label(__('Applied from')),
                        Forms\Components\DatePicker::make('until')->label(__('Applied until')),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn(Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('approve')
                        ->label(__('Approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(MembershipApplication $record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->modalHeading(__('Approve Membership Application'))
                        ->modalDescription(__('Are you sure you want to approve this application? The applicant will be notified via email, SMS, and WhatsApp.'))
                        ->action(function (MembershipApplication $record, Component $livewire) {
                            $memberNumber = static::approvePendingApplication($record);

                            Notification::make()
                                ->title(__('Application Approved'))
                                ->body(__('Member :name has been approved with number :number.', ['name' => $record->user->name, 'number' => $memberNumber]))
                                ->success()
                                ->send();

                            static::dispatchApplicationStatsRefresh($livewire);
                        }),
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(MembershipApplication $record) => $record->status === 'pending')
                        ->schema([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label(__('Reason for Rejection'))
                                ->required()
                                ->rows(3)
                                ->placeholder(__('Please provide a reason for rejecting this application...')),
                        ])
                        ->action(function (MembershipApplication $record, array $data, Component $livewire) {
                            static::rejectPendingApplication($record, $data['rejection_reason']);

                            Notification::make()
                                ->title(__('Application Rejected'))
                                ->body(__('Application for :name has been rejected.', ['name' => $record->user->name]))
                                ->warning()
                                ->send();

                            static::dispatchApplicationStatsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->after(fn(Component $livewire) => static::dispatchApplicationStatsRefresh($livewire)),
                    RestoreAction::make()
                        ->after(fn(Component $livewire) => static::dispatchApplicationStatsRefresh($livewire)),
                    ForceDeleteAction::make()
                        ->after(fn(Component $livewire) => static::dispatchApplicationStatsRefresh($livewire)),
                ])
                    ->tooltip(__('Actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('Approve selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('Approve selected applications'))
                        ->modalDescription(__('Each selected row that is still pending will be approved: a member record and member number are created, the login is activated, and the applicant is notified (email, SMS, and WhatsApp where configured). Rows that are not pending are skipped.'))
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

                            $body = __('Approved: :approved. Failed: :failed. Skipped (not pending): :skipped.', ['approved' => $approved, 'failed' => $failed, 'skipped' => $ignored]);

                            Notification::make()
                                ->title(__('Bulk approve finished'))
                                ->body($body)
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();

                            static::dispatchApplicationStatsRefresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label(__('Reject selected'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label(__('Reason for rejection'))
                                ->required()
                                ->rows(3)
                                ->placeholder(__('This message is saved on each application and sent to every selected pending applicant.')),
                        ])
                        ->modalHeading(__('Reject selected applications'))
                        ->modalDescription(__('The reason below is applied to each selected row that is still pending, stored on the record, and included in notifications. Rows that are not pending are skipped.'))
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

                            $body = __('Rejected: :rejected. Failed: :failed. Skipped (not pending): :skipped.', ['rejected' => $rejected, 'failed' => $failed, 'skipped' => $ignored]);

                            Notification::make()
                                ->title(__('Bulk reject finished'))
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

    public static function dispatchApplicationStatsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        // Header widgets are separate Livewire roots. Defer so the widget is mounted.
        // Use $wire.$refresh() instead of dispatchTo(event): missing event detail becomes `{}` and
        // breaks __dispatch (empty property path / PublicPropertyNotFoundException).
        $targetName = json_encode(
            app('livewire.factory')->resolveComponentName(ApplicationStatsWidget::class),
            JSON_THROW_ON_ERROR
        );

        $livewire->js(
            'setTimeout(() => window.Livewire.getByName(' . $targetName . ').forEach(w => w.$refresh()), 0)'
        );
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

        $member = Member::create([
            'user_id' => $record->user_id,
            'member_number' => $memberNumber,
            'joined_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        app(AccountingService::class)->ensureMemberAccounts($member);

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
