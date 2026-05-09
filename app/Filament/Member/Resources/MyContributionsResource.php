<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyContributionsResource\Pages;
use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Support\DatabaseDialect;
use App\Support\FilamentTableSummaries;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class MyContributionsResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'My Contributions';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('My Contributions');
    }

    public static function getModelLabel(): string
    {
        return __('Contribution');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Contributions');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'my_finance';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $memberId = Member::query()
                    ->where('user_id', auth()->id())
                    ->value('id');

                if (!filled($memberId)) {
                    return Contribution::query()->whereRaw('1 = 0');
                }

                $tableName = (new Contribution())->getTable();
                $dueMonthSql = DatabaseDialect::monthExpression('loan_installments.due_date');
                $dueYearSql = DatabaseDialect::yearExpression('loan_installments.due_date');
                $notesSql = DatabaseDialect::isMysqlFamily()
                    ? "CONCAT('Loan #', loans.id, ' installment #', loan_installments.installment_number)"
                    : "'Loan #' || loans.id || ' installment #' || loan_installments.installment_number";

                $contributionRows = Contribution::query()
                    ->where('member_id', $memberId)
                    ->select("{$tableName}.*");

                $repaymentRows = LoanInstallment::query()
                    ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
                    ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
                    ->where('loans.member_id', $memberId)
                    ->where('loan_installments.status', 'paid')
                    ->whereNull('loan_installments.deleted_at')
                    ->selectRaw('(-loan_installments.id) as id')
                    ->selectRaw('loans.member_id as member_id')
                    ->selectRaw('loan_installments.amount as amount')
                    ->selectRaw("{$dueMonthSql} as month")
                    ->selectRaw("{$dueYearSql} as year")
                    ->selectRaw('loan_installments.paid_at as paid_at')
                    ->selectRaw("'" . Contribution::PAYMENT_METHOD_LOAN_REPAYMENT . "' as payment_method")
                    ->selectRaw('NULL as reference_number')
                    ->selectRaw("{$notesSql} as notes")
                    ->selectRaw('loan_installments.created_at as created_at')
                    ->selectRaw('loan_installments.updated_at as updated_at')
                    ->selectRaw('loan_installments.is_late as is_late')
                    ->selectRaw('NULL as deleted_at')
                    ->selectRaw('loan_installments.late_fee_amount as late_fee_amount');

                return Contribution::query()
                    ->fromSub($contributionRows->unionAll($repaymentRows)->toBase(), $tableName)
                    ->select("{$tableName}.*");
            })
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('app.field.amount'))
                    ->money('SAR')
                    ->weight('bold')
                    ->summarize(FilamentTableSummaries::countSumAverageMoney()),
                Tables\Columns\TextColumn::make('month')
                    ->label(__('app.field.month'))
                    ->formatStateUsing(function ($state, Contribution $record): string {
                        return Carbon::createFromDate((int) $record->year, (int) $state, 1)
                            ->locale(app()->getLocale())
                            ->translatedFormat('F');
                    }),
                Tables\Columns\TextColumn::make('year')->label(__('Year')),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('Source'))
                    ->visibleFrom('md')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => Contribution::paymentMethodLabel($state)),
                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('app.field.reference_number'))
                    ->visibleFrom('lg')
                    ->placeholder(__('—')),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('app.field.paid_at'))
                    ->visibleFrom('sm')
                    ->formatStateUsing(fn($state) => $state instanceof \Carbon\CarbonInterface
                        ? $state->locale(app()->getLocale())->translatedFormat('d M Y')
                        : '')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label(__('Late'))
                    ->visibleFrom('sm')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('download_receipt')
                        ->label(__('Receipt'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT)
                        ->url(fn(Contribution $record): string => route('member.contribution.receipt', $record))
                        ->openUrlInNewTab(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('')
                    ->button(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->options(collect(range(1, 12))->mapWithKeys(fn(int $m): array => [
                        $m => Carbon::createFromDate((int) now()->year, $m, 1)
                            ->locale(app()->getLocale())
                            ->translatedFormat('F'),
                    ])->all()),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn($query, $data) => ($data['year'] ?? null) ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label(__('Source'))
                    ->options(fn(): array => Contribution::paymentMethodOptions()),
                Tables\Filters\TernaryFilter::make('is_late')
                    ->label(__('Late payment'))
                    ->trueLabel(__('Late only'))
                    ->falseLabel(__('On-time only')),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_from')->label(__('Paid from')),
                        Forms\Components\DatePicker::make('paid_until')->label(__('Paid until')),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['paid_from'] ?? null, fn($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label(__('Min amount (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label(__('Max amount (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyContributions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
