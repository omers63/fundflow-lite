<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyLoansResource\Pages;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanEligibilityService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MyLoansResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'My Loans';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.my_finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('amount_requested')
                ->label('Loan Amount (SAR)')
                ->numeric()
                ->required()
                ->minValue(100),
            Forms\Components\Select::make('installments_count')
                ->label('Repayment Period')
                ->options(array_combine(range(1, 24), array_map(fn ($n) => "{$n} months", range(1, 24))))
                ->default(12)
                ->required(),
            Forms\Components\Textarea::make('purpose')
                ->label('Purpose of Loan')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => Loan::whereHas('member', fn ($q) => $q->where('user_id', auth()->id())))
            ->columns([
                Tables\Columns\TextColumn::make('amount_requested')
                    ->label('Requested')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')
                    ->label('Approved')
                    ->money('SAR')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Months'),
                Tables\Columns\TextColumn::make('purpose')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('applied_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->headerActions([
                Action::make('apply_loan')
                    ->label('Apply for Loan')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->schema([
                        Forms\Components\TextInput::make('amount_requested')
                            ->label('Loan Amount (SAR)')
                            ->numeric()
                            ->required()
                            ->minValue(100),
                        Forms\Components\Select::make('installments_count')
                            ->label('Repayment Period')
                            ->options(array_combine(range(1, 24), array_map(fn ($n) => "{$n} months", range(1, 24))))
                            ->default(12)
                            ->required(),
                        Forms\Components\Textarea::make('purpose')
                            ->label('Purpose')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        $member = Member::where('user_id', auth()->id())->first();

                        if (! $member) {
                            Notification::make()->title('Member record not found')->danger()->send();
                            return;
                        }

                        $eligibility = app(LoanEligibilityService::class);
                        if (! $eligibility->isEligible($member)) {
                            Notification::make()
                                ->title('Not Eligible')
                                ->body('You have overdue installments or are not an active member. Please clear dues before applying.')
                                ->warning()
                                ->send();
                            return;
                        }

                        Loan::create([
                            'member_id' => $member->id,
                            'amount_requested' => $data['amount_requested'],
                            'purpose' => $data['purpose'],
                            'installments_count' => $data['installments_count'],
                            'status' => 'pending',
                            'applied_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Loan application submitted')
                            ->body('Your application is under review. You will be notified once it is processed.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyLoans::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
