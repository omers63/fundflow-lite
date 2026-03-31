<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Notifications\LoanApprovedNotification;
use App\Services\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Loan::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Loan Details')
                ->schema([
                    Forms\Components\Select::make('member_id')
                        ->label('Member')
                        ->options(fn () => Member::with('user')->get()->pluck('user.name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('amount_requested')
                        ->label('Requested Amount (SAR)')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('amount_approved')
                        ->label('Approved Amount (SAR)')
                        ->numeric()
                        ->nullable(),
                    Forms\Components\Textarea::make('purpose')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('installments_count')
                        ->label('Installments')
                        ->options(array_combine(range(1, 24), array_map(fn ($n) => "{$n} months", range(1, 24))))
                        ->default(12)
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'active' => 'Active',
                            'completed' => 'Completed',
                        ])
                        ->disabled(fn ($record) => $record === null),
                    Forms\Components\Textarea::make('rejection_reason')->nullable()->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_requested')
                    ->label('Requested')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('amount_approved')
                    ->label('Approved')
                    ->money('SAR')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Months'),
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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'active' => 'Active', 'completed' => 'Completed', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve_loan')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Loan $record) => $record->status === 'pending')
                    ->schema([
                        Forms\Components\TextInput::make('amount_approved')
                            ->label('Approved Amount (SAR)')
                            ->numeric()
                            ->required(),
                        Forms\Components\Select::make('installments_count')
                            ->label('Number of Installments')
                            ->options(array_combine(range(1, 24), array_map(fn ($n) => "{$n} months", range(1, 24))))
                            ->default(12)
                            ->required(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $record->update([
                            'status' => 'active',
                            'amount_approved' => $data['amount_approved'],
                            'installments_count' => $data['installments_count'],
                            'approved_at' => now(),
                            'approved_by_id' => auth()->id(),
                            'disbursed_at' => now(),
                            'due_date' => now()->addMonths($data['installments_count'])->toDateString(),
                        ]);

                        $count       = $data['installments_count'];
                        $baseAmount  = round($data['amount_approved'] / $count, 2);
                        $remainder   = round($data['amount_approved'] - ($baseAmount * $count), 2);

                        for ($i = 1; $i <= $count; $i++) {
                            $amount = ($i === $count)
                                ? round($baseAmount + $remainder, 2)
                                : $baseAmount;

                            LoanInstallment::create([
                                'loan_id'            => $record->id,
                                'installment_number' => $i,
                                'amount'             => $amount,
                                'due_date'           => now()->addMonths($i)->toDateString(),
                                'status'             => 'pending',
                            ]);
                        }

                        // Post disbursement to Fund Account + member accounts + loan account
                        try {
                            app(AccountingService::class)->postLoanDisbursement($record);
                        } catch (\Throwable $e) {
                            logger()->error('Failed to post loan disbursement', ['loan_id' => $record->id, 'error' => $e->getMessage()]);
                        }

                        try {
                            $record->member->user->notify(new LoanApprovedNotification(
                                amount: $data['amount_approved'],
                                installments: $data['installments_count'],
                                dueDate: now()->addMonths($data['installments_count'])->format('d M Y')
                            ));
                        } catch (\Throwable $e) {
                            // best-effort
                        }

                        Notification::make()
                            ->title('Loan Approved')
                            ->body("Loan approved for {$record->member->user->name}. {$data['installments_count']} installments created.")
                            ->success()
                            ->send();
                    }),
                Action::make('reject_loan')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Loan $record) => $record->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Loan Rejected')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),
        ];
    }
}
