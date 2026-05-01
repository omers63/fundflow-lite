<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\MemberNumberService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Login Credentials'))
                ->description(__('These credentials will be used by the member to log in to the member portal.'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Full Name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email Address'))
                        ->email()
                        ->required()
                        ->unique(User::class, 'email')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone Number'))
                        ->tel()
                        ->maxLength(20)
                        ->placeholder('+966 5x xxx xxxx'),
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
                    Forms\Components\TextInput::make('parent_pin')
                        ->label(__('Parent PIN (4 digits, for profile access)'))
                        ->password()
                        ->revealable()
                        ->rules(['nullable', 'digits:4'])
                        ->visible(fn(callable $get) => blank($get('parent_id')))
                        ->dehydrated(false),
                ])->columns(2),

            Section::make(__('Membership Details'))
                ->schema([
                    Forms\Components\DatePicker::make('joined_at')
                        ->label(__('Join Date'))
                        ->default(now()->toDateString())
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => __('Active'),
                            'suspended' => __('Suspended'),
                            'delinquent' => __('Delinquent'),
                        ])
                        ->default('active')
                        ->required(),
                ])->columns(2),

            Section::make(__('Contribution & Sponsorship'))
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label(__('Monthly Contribution Amount'))
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText(__('Multiples of SAR 500, from SAR 500 to SAR 3,000.')),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('Parent Member (Sponsor)'))
                        ->options(fn() => Member::with('user')
                            ->whereNull('parent_id')
                            ->get()
                            ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                        ->searchable()
                        ->nullable()
                        ->placeholder(__('None (independent member)'))
                        ->helperText(__('The parent member can fund this member\'s cash account.')),
                ])->columns(2),

            Section::make(__('Opening balances'))
                ->description(__('Optional. Same paired master + member postings as CSV import (ledger transactions). Cash cannot be negative; fund may be negative.'))
                ->schema([
                    Forms\Components\TextInput::make('opening_cash_balance')
                        ->label(__('Cash account (SAR)'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix('SAR'),
                    Forms\Components\TextInput::make('opening_fund_balance')
                        ->label(__('Fund account (SAR)'))
                        ->numeric()
                        ->default(0)
                        ->step(0.01)
                        ->suffix('SAR')
                        ->helperText(__('Negative values debit master and member fund together (e.g. master-funded loan).')),
                ])->columns(2),
        ]);
    }

    /**
     * Override creation to build both User and Member in a single transaction.
     * Filament's default CreateRecord tries to create the resource model directly;
     * here we intercept that to do the full two-table creation ourselves.
     */
    protected function handleRecordCreation(array $data): Member
    {
        $openingCash = round((float) ($data['opening_cash_balance'] ?? 0), 2);
        $openingFund = round((float) ($data['opening_fund_balance'] ?? 0), 2);

        if ($openingCash < 0) {
            throw new \InvalidArgumentException(__('Opening cash balance cannot be negative.'));
        }

        return DB::transaction(function () use ($data, $openingCash, $openingFund) {
            // 1. Create the User account
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'member',
                'status' => 'approved',
            ]);

            // 2. Auto-generate the member number
            $memberNumber = app(MemberNumberService::class)->generate();

            // 3. Create the Member record
            $member = Member::create([
                'user_id' => $user->id,
                'member_number' => $memberNumber,
                'joined_at' => $data['joined_at'],
                'status' => $data['status'],
                'monthly_contribution_amount' => $data['monthly_contribution_amount'],
                'parent_id' => $data['parent_id'] ?? null,
                'household_email' => $data['parent_id']
                    ? Member::query()->find((int) $data['parent_id'])?->household_email
                    : $data['email'],
                'is_separated' => false,
                'direct_login_enabled' => false,
                'portal_pin' => blank($data['parent_id']) && filled($data['parent_pin'] ?? null)
                    ? Hash::make((string) $data['parent_pin'])
                    : null,
            ]);

            // 4. Ensure the member's virtual accounts are provisioned
            app(AccountingService::class)->ensureMemberAccounts($member);

            // 5. Opening balances (same as CSV import)
            app(AccountingService::class)->applyImportedBalanceAdjustments($member, $openingCash, $openingFund);

            return $member;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->getRecord();

        return Notification::make()
            ->title(__('Member Created'))
            ->body(__('Member :name has been created with number :number.', ['name' => $record->user->name, 'number' => $record->member_number]))
            ->success();
    }
}
