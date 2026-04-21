<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyInstallmentsResource\Pages;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Services\LoanRepaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MyInstallmentsResource extends Resource
{
    protected static ?string $model = LoanInstallment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'My Installments';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('My Installments');
    }

    public static function getModelLabel(): string
    {
        return __('Installment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Installments');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'loans';
    }

    public static function getNavigationBadge(): ?string
    {
        $member = auth()->user()?->member;
        if (! $member) {
            return null;
        }
        $count = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $member = auth()->user()?->member;

                return LoanInstallment::whereHas(
                    'loan',
                    fn ($q) => $q->where('member_id', $member?->id ?? 0)
                );
            })
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label(__('Loan #'))
                    ->visibleFrom('sm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment_number')
                    ->visibleFrom('md')
                    ->label(__('#')),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('app.field.amount'))
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('app.field.due_date'))
                    ->formatStateUsing(fn ($state) => $state instanceof \Carbon\CarbonInterface
                        ? $state->locale(app()->getLocale())->translatedFormat('d M Y')
                        : '')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('app.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => __('Pending'),
                        'paid' => __('Paid'),
                        'overdue' => __('Overdue'),
                        default => __(ucfirst($state)),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('app.field.paid_at'))
                    ->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => $state instanceof \Carbon\CarbonInterface
                        ? $state->locale(app()->getLocale())->translatedFormat('d M Y')
                        : '')
                    ->placeholder(__('—')),
            ])
            ->defaultSort('due_date', 'asc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('pay_installment')
                        ->label(__('Pay Now'))
                        ->icon('heroicon-o-credit-card')
                        ->color('success')
                        ->visible(function () {
                            $member = Member::where('user_id', auth()->id())->first();

                            return $member && app(LoanRepaymentService::class)->shouldOfferOpenPeriodRepayment($member);
                        })
                        ->disabled(function () {
                            $member = Member::where('user_id', auth()->id())->first();

                            return ! $member || app(LoanRepaymentService::class)->hasInsufficientCashForOpenPeriodRepayment($member);
                        })
                        ->requiresConfirmation()
                        ->modalHeading(__('Pay Your Loan Installment'))
                        ->modalDescription(function () {
                            $member = Member::where('user_id', auth()->id())->with('accounts')->first();
                            if (! $member) {
                                return __('Member record not found.');
                            }

                            return app(LoanRepaymentService::class)->openPeriodRepaymentModalDescription($member);
                        })
                        ->modalSubmitActionLabel(__('Pay Now'))
                        ->action(function () {
                            $member = Member::where('user_id', auth()->id())->with(['user', 'accounts'])->first();
                            if (! $member) {
                                Notification::make()->title(__('Member record not found'))->danger()->send();

                                return;
                            }
                            $outcome = app(LoanRepaymentService::class)->applyOpenPeriodRepaymentForMember($member);
                            match ($outcome) {
                                'applied' => Notification::make()->title(__('Installment Paid'))->body(__('Your loan installment has been paid successfully.'))->success()->send(),
                                'insufficient' => Notification::make()->title(__('Insufficient Balance'))->body(__('Your cash account does not have enough balance to cover this installment.'))->danger()->send(),
                                default => Notification::make()->title(__('Nothing to Pay'))->body(__('No installment is due for the current period.'))->warning()->send(),
                            };
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('')
                    ->button(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('loan_id')
                    ->label(__('Loan'))
                    ->options(function () {
                        $member = auth()->user()?->member;
                        if (! $member) {
                            return [];
                        }

                        return Loan::query()->where('member_id', $member->id)->orderByDesc('id')->get()
                            ->mapWithKeys(fn (Loan $l) => [$l->id => __('Loan #:id', ['id' => $l->id])]);
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'paid' => __('Paid'),
                        'overdue' => __('Overdue'),
                    ]),
                Tables\Filters\Filter::make('due_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label(__('Due from')),
                        Forms\Components\DatePicker::make('until')->label(__('Due until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('due_date', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('due_date', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label(__('Paid from')),
                        Forms\Components\DatePicker::make('until')->label(__('Paid until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['until']));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyInstallments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
