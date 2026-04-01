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
            Section::make('Login Credentials')
                ->description('These credentials will be used by the member to log in to the member portal.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->maxLength(20)
                        ->placeholder('+966 5x xxx xxxx'),
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

            Section::make('Membership Details')
                ->schema([
                    Forms\Components\DatePicker::make('joined_at')
                        ->label('Join Date')
                        ->default(now()->toDateString())
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active'     => 'Active',
                            'suspended'  => 'Suspended',
                            'delinquent' => 'Delinquent',
                        ])
                        ->default('active')
                        ->required(),
                ])->columns(2),

            Section::make('Contribution & Sponsorship')
                ->schema([
                    Forms\Components\Select::make('monthly_contribution_amount')
                        ->label('Monthly Contribution Amount')
                        ->options(Member::contributionAmountOptions())
                        ->default(500)
                        ->required()
                        ->helperText('Multiples of SAR 500, from SAR 500 to SAR 3,000.'),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Member (Sponsor)')
                        ->options(fn () => Member::with('user')
                            ->whereNull('parent_id')
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                        ->searchable()
                        ->nullable()
                        ->placeholder('None (independent member)')
                        ->helperText('The parent member can fund this member\'s cash account.'),
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
        return DB::transaction(function () use ($data) {
            // 1. Create the User account
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role'     => 'member',
                'status'   => 'approved',
            ]);

            // 2. Auto-generate the member number
            $memberNumber = app(MemberNumberService::class)->generate();

            // 3. Create the Member record
            $member = Member::create([
                'user_id'                      => $user->id,
                'member_number'                => $memberNumber,
                'joined_at'                    => $data['joined_at'],
                'status'                       => $data['status'],
                'monthly_contribution_amount'  => $data['monthly_contribution_amount'],
                'parent_id'                    => $data['parent_id'] ?? null,
            ]);

            // 4. Ensure the member's virtual accounts are provisioned
            app(AccountingService::class)->ensureMemberAccounts($member);

            return $member;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->getRecord();

        return Notification::make()
            ->title('Member Created')
            ->body("Member {$record->user->name} has been created with number {$record->member_number}.")
            ->success();
    }
}
