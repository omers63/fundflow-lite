<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberResource\Pages;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\AccountsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\ContributionsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\DependentsRelationManager;
use App\Filament\Admin\Resources\MemberResource\RelationManagers\LoansRelationManager;
use App\Models\Member;
use App\Models\MembershipApplication;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            // ── 1. Member Status ─────────────────────────────────────────────
            Section::make('Membership')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\TextInput::make('member_number')
                        ->label('Member Number')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'suspended' => 'Suspended',
                            'delinquent' => 'Delinquent',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('joined_at')
                        ->label('Membership Date')
                        ->required()
                        ->native(false)
                        ->helperText('Affects loan eligibility (minimum 1-year membership).'),
                ])->columns(3),

            // ── 2. User Account ──────────────────────────────────────────────
            // Section::make('User Account')
            //     ->icon('heroicon-o-user')
            //     ->description('Name and mobile phone can be updated. Email is the login credential. Mobile is stored on the membership application and used for SMS/WhatsApp.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_user_name')
            //             ->label('Full Name')
            //             ->required()
            //             ->maxLength(255),
            //         Forms\Components\TextInput::make('_user_email')
            //             ->label('Email (Login)')
            //             ->email()
            //             ->disabled()
            //             ->helperText('Email cannot be changed here. Contact system admin.'),
            //         Forms\Components\TextInput::make('_app_mobile_phone')
            //             ->label('Mobile phone')
            //             ->tel()
            //             ->maxLength(30),
            //     ])->columns(3),

            // ── 3. Membership application — personal details (edit on this page) ─
            // Section::make('Membership application — personal details')
            //     ->icon('heroicon-o-document-text')
            //     ->description('There is no separate “application” screen. Change these fields here, then click Save at the top of the page.')
            //     ->schema([
            //         Forms\Components\Select::make('_app_application_type')
            //             ->label('Application type')
            //             ->options(MembershipApplication::applicationTypeOptions())
            //             ->default('new'),
            //         Forms\Components\Select::make('_app_gender')
            //             ->options(MembershipApplication::genderOptions())
            //             ->placeholder('—'),
            //         Forms\Components\Select::make('_app_marital_status')
            //             ->label('Marital status')
            //             ->options(MembershipApplication::maritalStatusOptions())
            //             ->placeholder('—'),
            //         Forms\Components\DatePicker::make('_app_membership_date')
            //             ->label('Membership date')
            //             ->native(false),
            //         Forms\Components\TextInput::make('_app_national_id')
            //             ->label('National ID')
            //             ->maxLength(20),
            //         Forms\Components\DatePicker::make('_app_date_of_birth')
            //             ->label('Date of Birth')
            //             ->native(false)
            //             ->maxDate(now()),
            //         Forms\Components\TextInput::make('_app_city')
            //             ->label('City')
            //             ->maxLength(100),
            //         Forms\Components\Textarea::make('_app_address')
            //             ->label('Address')
            //             ->rows(2)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_occupation')
            //             ->label('Occupation')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_employer')
            //             ->label('Employer')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_monthly_income')
            //             ->label('Monthly Income (SAR)')
            //             ->numeric()
            //             ->prefix('SAR')
            //             ->minValue(0),
            //     ])->columns(3),

            // Section::make('Membership application — contact & banking')
            //     ->icon('heroicon-o-phone')
            //     ->collapsed()
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_home_phone')
            //             ->label('Home phone')
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_phone')
            //             ->label('Work phone')
            //             ->tel()
            //             ->maxLength(30),
            //         Forms\Components\TextInput::make('_app_work_place')
            //             ->label('Work place')
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_residency_place')
            //             ->label('Residency place')
            //             ->maxLength(255)
            //             ->columnSpanFull(),
            //         Forms\Components\TextInput::make('_app_bank_account_number')
            //             ->label('Bank account number')
            //             ->maxLength(50),
            //         Forms\Components\TextInput::make('_app_iban')
            //             ->label('IBAN')
            //             ->maxLength(34)
            //             ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
            //     ])->columns(3),

            // ── 4. Next of Kin (same application record) ─────────────────────
            // Section::make('Membership application — next of kin')
            //     ->icon('heroicon-o-users')
            //     ->description('Part of the same membership application; saved when you save the member.')
            //     ->schema([
            //         Forms\Components\TextInput::make('_app_next_of_kin_name')
            //             ->label('Name')
            //             ->maxLength(150),
            //         Forms\Components\TextInput::make('_app_next_of_kin_phone')
            //             ->label('Phone')
            //             ->tel()
            //             ->maxLength(30),
            //     ])->columns(2),

            // ── 5. Contribution & Sponsorship ────────────────────────────────
            Section::make('Contribution & Sponsorship')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly Contribution Amount')
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000.'),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Member (Sponsor)')
                        ->options(fn(?Member $record) => Member::with('user')
                            ->whereNull('parent_id')
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->get()
                            ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                        ->searchable()
                        ->nullable()
                        ->placeholder('None (independent member)')
                        ->disabled(fn(?Member $record) => $record && $record->dependents()->exists())
                        ->helperText(fn(?Member $record) => $record && $record->dependents()->exists()
                            ? 'This member has dependents and cannot be assigned a parent.'
                            : 'The parent member can fund this member\'s cash account.'),
                ])->columns(2),

            // ── 6. Active Loan — Guarantor & Witnesses ───────────────────────
            Section::make('Active Loan — Guarantor & Witnesses')
                ->icon('heroicon-o-shield-check')
                ->description('Guarantor and witnesses linked to the most recent active loan.')
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('_active_loan_ref')
                        ->label('Loan Reference')
                        ->content(function (?Member $record) {
                            $loan = $record?->loans()
                                ->whereIn('status', ['approved', 'active', 'disbursed'])
                                ->latest('applied_at')->first();
                            if (!$loan) {
                                return '— No active loan';
                            }

                            return "Loan #{$loan->id} · SAR " . number_format((float) $loan->amount_approved, 2)
                                . " · Status: {$loan->status}";
                        })
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('_guarantor')
                        ->label('Guarantor Member')
                        ->content(function (?Member $record) {
                            $loan = $record?->loans()
                                ->whereIn('status', ['approved', 'active', 'disbursed'])
                                ->latest('applied_at')->first();
                            if (!$loan?->guarantor) {
                                return '—';
                            }
                            $g = $loan->guarantor->load('user');

                            return "{$g->member_number} – {$g->user->name}"
                                . ($g->user->phone ? '  ·  ' . $g->user->phone : '');
                        }),
                    Forms\Components\Placeholder::make('_guarantor_released')
                        ->label('Guarantor Released?')
                        ->content(function (?Member $record) {
                            $loan = $record?->loans()
                                ->whereIn('status', ['approved', 'active', 'disbursed'])
                                ->latest('applied_at')->first();
                            if (!$loan) {
                                return '—';
                            }

                            return $loan->guarantor_released_at
                                ? '✅ Released on ' . Carbon::parse($loan->guarantor_released_at)->format('d M Y')
                                : '⏳ Not yet released';
                        }),
                    Forms\Components\Placeholder::make('_witness1')
                        ->label('Witness 1')
                        ->content(function (?Member $record) {
                            $loan = $record?->loans()
                                ->whereIn('status', ['approved', 'active', 'disbursed'])
                                ->latest('applied_at')->first();
                            if (!$loan?->witness1_name) {
                                return '—';
                            }

                            return $loan->witness1_name
                                . ($loan->witness1_phone ? '  ·  ' . $loan->witness1_phone : '');
                        }),
                    Forms\Components\Placeholder::make('_witness2')
                        ->label('Witness 2')
                        ->content(function (?Member $record) {
                            $loan = $record?->loans()
                                ->whereIn('status', ['approved', 'active', 'disbursed'])
                                ->latest('applied_at')->first();
                            if (!$loan?->witness2_name) {
                                return '—';
                            }

                            return $loan->witness2_name
                                . ($loan->witness2_phone ? '  ·  ' . $loan->witness2_phone : '');
                        }),
                ])->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'delinquent' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('monthly_contribution_amount')
                    ->label('Monthly Alloc.')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label('Parent')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('contributions_sum_amount')
                    ->label('Total Contributions')
                    ->money('SAR')
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->contributions()->sum('amount')),
                Tables\Columns\TextColumn::make('late_contributions_count')
                    ->label('Late #')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('late_contributions_amount')
                    ->label('Late Amount')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended', 'delinquent' => 'Delinquent']),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContributionsRelationManager::class,
            LoansRelationManager::class,
            AccountsRelationManager::class,
            DependentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'view' => Pages\ViewMember::route('/{record}'),
        ];
    }
}
