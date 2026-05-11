<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContributionResource\Pages;
use App\Filament\Admin\Widgets\ContributionStatsWidget;
use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Services\ContributionCycleService;
use App\Services\ContributionImportService;
use App\Support\DatabaseDialect;
use App\Support\FilamentStoredUploadPath;
use App\Support\FilamentTableSummaries;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
    }

    public static function getNavigationLabel(): string
    {
        return __('Contributions');
    }

    public static function getModelLabel(): string
    {
        return __('Contribution');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contributions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('member_id')
                ->label(__('Member'))
                ->options(fn() => Member::with('user')
                    ->get()
                    ->pluck('user.name', 'id')
                    ->prepend('-- Select Member --', ''))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($set, $state, string $operation): void {
                    if ($operation !== 'create') {
                        return;
                    }

                    if ($state === null || $state === '') {
                        $set('amount', null);

                        return;
                    }

                    $amount = Member::query()->whereKey($state)->value('monthly_contribution_amount');
                    $set('amount', $amount !== null ? (float) $amount : null);
                }),
            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->prefix('SAR')
                ->required()
                ->minValue(0)
                ->readOnlyOn('create')
                ->helperText(fn(string $operation): ?string => $operation === 'create'
                    ? 'Filled from the member\'s monthly contribution amount.'
                    : null),
            Forms\Components\Select::make('month')
                ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12))))
                ->required(),
            Forms\Components\TextInput::make('year')
                ->numeric()
                ->default(now()->year)
                ->required()
                ->rule(static function (Get $get, Field $component): Closure {
                    return function (string $attribute, mixed $value, Closure $fail) use ($get, $component): void {
                        $memberId = $get('member_id');
                        $month = $get('month');

                        if (!filled($memberId) || !filled($month) || !filled($value)) {
                            return;
                        }

                        $exceptId = ($record = $component->getRecord()) instanceof Contribution
                            ? (int) $record->getKey()
                            : null;

                        if (!Contribution::activePeriodExists((int) $memberId, (int) $month, (int) $value, $exceptId)) {
                            return;
                        }

                        $fail(Contribution::duplicateCycleMessage((int) $month, (int) $value));
                    };
                }),
            Forms\Components\DateTimePicker::make('paid_at')
                ->label(__('Payment Date'))
                ->default(now()),
            Forms\Components\Checkbox::make('is_late')
                ->label(__('Late payment'))
                ->helperText(__('Override whether this contribution counts as late for compliance. The automatic flag from contribution runs can be corrected here.'))
                ->default(false),
            Forms\Components\TextInput::make('late_fee_amount')
                ->label(__('Late fee (SAR)'))
                ->numeric()
                ->prefix('SAR')
                ->nullable()
                ->visible(fn(Get $get): bool => (bool) $get('is_late'))
                ->helperText(__('Credited to master cash only (not master fund). Leave empty to use the configured default when saving.')),
            Forms\Components\TextInput::make('reference_number')
                ->label(__('Reference #'))
                ->nullable(),
            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->nullable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->modifyQueryUsing(function ($query) {
                $tableName = $query->getModel()->getTable();
                $dueMonthSql = DatabaseDialect::monthExpression('loan_installments.due_date');
                $dueYearSql = DatabaseDialect::yearExpression('loan_installments.due_date');
                $notesSql = DatabaseDialect::isMysqlFamily()
                    ? "CONCAT('Loan #', loans.id, ' installment #', loan_installments.installment_number)"
                    : "'Loan #' || loans.id || ' installment #' || loan_installments.installment_number";

                $contributionQuery = $query
                    ->select("{$tableName}.*");

                $repaymentQuery = LoanInstallment::query()
                    ->withoutGlobalScope(SoftDeletingScope::class)
                    ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
                    ->where('loan_installments.status', 'paid')
                    ->paidVisibleInCollections()
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

                $combinedRows = $contributionQuery->unionAll($repaymentQuery);

                return Contribution::query()
                    ->fromSub($combinedRows->toBase(), $tableName)
                    ->select("{$tableName}.*")
                    ->selectRaw(
                        'SUM(amount) OVER (
                            PARTITION BY member_id
                            ORDER BY paid_at ASC, id ASC
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) as running_balance'
                    );
            })
            ->headerActions([
                Action::make('exportCsv')
                    ->label(__('Export Contributions'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function () {
                        $filename = 'contributions-' . now()->format('Y-m-d') . '.csv';
                        $dueMonthSql = DatabaseDialect::monthExpression('loan_installments.due_date');
                        $dueYearSql = DatabaseDialect::yearExpression('loan_installments.due_date');

                        return response()->streamDownload(function () use ($dueMonthSql, $dueYearSql) {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'id',
                                'member_number',
                                'member_name',
                                'month',
                                'year',
                                'period',
                                'amount',
                                'is_late',
                                'recorded_at',
                            ]);

                            $contributionRows = DB::table('contributions')
                                ->leftJoin('members', 'members.id', '=', 'contributions.member_id')
                                ->leftJoin('users', 'users.id', '=', 'members.user_id')
                                ->whereNull('contributions.deleted_at')
                                ->selectRaw('contributions.id as id')
                                ->selectRaw('members.member_number as member_number')
                                ->selectRaw('users.name as member_name')
                                ->selectRaw('contributions.month as month')
                                ->selectRaw('contributions.year as year')
                                ->selectRaw('contributions.amount as amount')
                                ->selectRaw('contributions.is_late as is_late')
                                ->selectRaw('contributions.created_at as created_at');

                            $repaymentRows = DB::table('loan_installments')
                                ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
                                ->leftJoin('members', 'members.id', '=', 'loans.member_id')
                                ->leftJoin('users', 'users.id', '=', 'members.user_id')
                                ->where('loan_installments.status', 'paid')
                                ->when(
                                    LoanInstallment::hasCollectionsVisibilityColumn(),
                                    fn($q) => $q->where('loan_installments.show_as_loan_repayment_in_collections', true),
                                )
                                ->whereNull('loan_installments.deleted_at')
                                ->selectRaw('(-loan_installments.id) as id')
                                ->selectRaw('members.member_number as member_number')
                                ->selectRaw('users.name as member_name')
                                ->selectRaw("{$dueMonthSql} as month")
                                ->selectRaw("{$dueYearSql} as year")
                                ->selectRaw('loan_installments.amount as amount')
                                ->selectRaw('loan_installments.is_late as is_late')
                                ->selectRaw('loan_installments.created_at as created_at');

                            DB::query()
                                ->fromSub($contributionRows->unionAll($repaymentRows), 'rows')
                                ->orderByDesc('year')
                                ->orderByDesc('month')
                                ->orderBy('id')
                                ->cursor()
                                ->each(function (object $row) use ($handle): void {
                                    fputcsv($handle, [
                                        $row->id,
                                        $row->member_number,
                                        $row->member_name,
                                        $row->month,
                                        $row->year,
                                        date('F', mktime(0, 0, 0, (int) $row->month, 1)) . ' ' . $row->year,
                                        number_format((float) $row->amount, 2, '.', ''),
                                        ((bool) $row->is_late) ? 'Yes' : 'No',
                                        $row->created_at,
                                    ]);
                                });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
                Action::make('importContributions')
                    ->label(__('app.action.import_contributions'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn(): bool => static::canCreate())
                    ->modalHeading(__('app.contribution.import.heading'))
                    ->modalDescription(new HtmlString(
                        '<div class="space-y-3 text-sm">' .
                        '<div class="rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs dark:border-blue-500/30 dark:bg-blue-500/10">' .
                        '<p class="font-semibold text-blue-900 dark:text-blue-200 mb-1">' . e(__('app.ui.need_starter_file')) . '</p>' .
                        '<p class="text-blue-900/90 dark:text-blue-100/90">' .
                        e(__('Download a ready sample with common formats (numeric and month-name values): ')) .
                        '<a href="' . route('downloads.contribution-import-sample') . '" class="font-semibold text-blue-700 underline hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200">contributions-import-sample-15.csv</a>' .
                        '</p>' .
                        '</div>' .
                        '<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">' .
                        '<table class="w-full text-xs">' .
                        '<tbody class="divide-y divide-gray-100 dark:divide-gray-800">' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 w-44 bg-gray-50 dark:bg-gray-900/30">' . e(__('app.ui.csv_format')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.ui.first_row_headers')) . '</td>' .
                        '</tr>' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Required fields')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('member name, month, year, amount.')) . '</td>' .
                        '</tr>' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Month value')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('Use 1-12 or a month name (e.g. January).')) . '</td>' .
                        '</tr>' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Optional fields')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('paid_at, guarantor, check#.')) . '</td>' .
                        '</tr>' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Amount sign rules')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('Positive amount = contribution (or repayment if member has active imported loan). Negative amount = create/approve/disburse loan using guarantor and check#.')) . '</td>' .
                        '</tr>' .
                        '<tr>' .
                        '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">' . e(__('Repayment routing')) . '</td>' .
                        '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('After a negative row for a member, following positive rows are treated as loan repayments until the loan is fully repaid: the row amount funds member cash once, then full installments are paid oldest-unpaid cycle first (late if overdue), then the CSV month/year cycle if affordable; surplus stays on member cash. A period cannot have both a contribution and a repayment on an active loan; then rows revert to normal contributions.')) . '</td>' .
                        '</tr>' .
                        '</tbody>' .
                        '</table>' .
                        '</div>' .
                        '</div>'
                    ))
                    ->modalWidth('2xl')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label(__('CSV file'))
                            ->disk('local')
                            ->directory('contribution-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $relative = FilamentStoredUploadPath::toRelativePath($data['csv_file'] ?? null);
                        if ($relative === null) {
                            Notification::make()
                                ->title(__('Import failed'))
                                ->body(__('No uploaded CSV file was received. Please re-select the file and try again.'))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $fullPath = Storage::disk('local')->path($relative);

                        try {
                            $result = app(ContributionImportService::class)->import($fullPath);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('Import failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        } finally {
                            try {
                                Storage::disk('local')->delete($relative);
                            } catch (\Throwable) {
                            }
                        }

                        $body = __('Created: :created · Skipped: :skipped · Failed: :failed', [
                            'created' => $result['created'],
                            'skipped' => $result['skipped'],
                            'failed' => $result['failed'],
                        ]);

                        if ($result['errors'] !== []) {
                            $preview = implode("\n", array_slice($result['errors'], 0, 8));
                            if (count($result['errors']) > 8) {
                                $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                            }
                            $body .= "\n\n" . $preview;
                        }

                        Notification::make()
                            ->title(__('app.contribution.import.finished'))
                            ->body($body)
                            ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                            ->persistent()
                            ->send();

                        static::dispatchContributionStatsRefresh($livewire);
                    }),
                CreateAction::make()
                    ->label(__('app.action.new') . ' ' . __('app.resource.contribution'))
                    ->icon('heroicon-o-plus-circle')
                    ->modalWidth('2xl')
                    ->createAnother(false)
                    ->mutateDataUsing(function (array $data): array {
                        $data['payment_method'] = Contribution::PAYMENT_METHOD_ADMIN;
                        if (!empty($data['is_late'])) {
                            $raw = $data['late_fee_amount'] ?? null;
                            if ($raw === null || $raw === '') {
                                $at = !empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();
                                $fee = app(ContributionCycleService::class)->lateFeeForContributionPeriod(
                                    (int) $data['month'],
                                    (int) $data['year'],
                                    $at,
                                );
                                $data['late_fee_amount'] = $fee > 0 ? $fee : null;
                            }
                        } else {
                            $data['late_fee_amount'] = null;
                        }

                        return $data;
                    })
                    ->after(function (Component $livewire): void {
                        static::dispatchContributionStatsRefresh($livewire);
                    })
                    ->visible(fn(): bool => static::canCreate()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label(__('Member #'))
                    ->wrap()
                    ->extraHeaderAttributes(['style' => FilamentTableSummaries::memberNumberCellStyle()])
                    ->extraCellAttributes(['style' => FilamentTableSummaries::memberNumberCellStyle()])
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label(__('Member Name'))
                    ->wrap()
                    ->extraHeaderAttributes(['style' => FilamentTableSummaries::memberDisplayNameCellStyle()])
                    ->extraCellAttributes(['style' => FilamentTableSummaries::memberDisplayNameCellStyle()])
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            Member::query()
                                ->select('users.name')
                                ->join('users', 'users.id', '=', 'members.user_id')
                                ->whereColumn('members.id', 'contributions.member_id')
                                ->limit(1),
                            $direction,
                        );
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->grow(false)
                    ->width('6rem')
                    ->extraHeaderAttributes(['style' => FilamentTableSummaries::narrowFixedCellStyle('6rem')])
                    ->extraCellAttributes(['style' => FilamentTableSummaries::narrowFixedCellStyle('6rem')])
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable()
                    ->summarize(FilamentTableSummaries::countSumAverageMoney()),
                Tables\Columns\TextColumn::make('running_balance')
                    ->label(__('Balance'))
                    ->money('SAR')
                    ->grow(false)
                    ->width('13rem')
                    ->extraHeaderAttributes(['style' => FilamentTableSummaries::narrowFixedCellStyle('13rem')])
                    ->extraCellAttributes(['style' => FilamentTableSummaries::narrowFixedCellStyle('13rem')])
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1)))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('Source'))
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => Contribution::paymentMethodLabel($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label(__('Late?'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('late_fee_amount')
                    ->label(__('Late fee'))
                    ->money('SAR')
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label(__('Member'))
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(
                        range(1, 12),
                        array_map(
                            fn($m) => Carbon::create(null, $m, 1)
                                ->locale(app()->getLocale())
                                ->translatedFormat('F'),
                            range(1, 12)
                        )
                    )),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()])
                    ->query(fn($query, $data) => $data['year'] ? $query->where('year', $data['year']) : $query),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT)
                        ->modalWidth('2xl'),
                    EditAction::make()
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT)
                        ->modalWidth('2xl')
                        ->after(function (Component $livewire): void {
                            static::dispatchContributionStatsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT)
                        ->modalDescription(__('Soft-deletes this contribution and reverses its fund ledger postings (master + member fund). Restoring re-posts the contribution to the ledger.')),
                    RestoreAction::make()
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT),
                    ForceDeleteAction::make()
                        ->visible(fn(Contribution $record): bool => $record->payment_method !== Contribution::PAYMENT_METHOD_LOAN_REPAYMENT),
                ]),
            ])
            ->recordUrl(null)
            ->recordAction(function (Model $record): ?string {
                if (!$record instanceof Contribution) {
                    return null;
                }

                if ($record->payment_method === Contribution::PAYMENT_METHOD_LOAN_REPAYMENT) {
                    return null;
                }

                if (static::canView($record)) {
                    return 'view';
                }

                if (static::canEdit($record)) {
                    return 'edit';
                }

                return null;
            })
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributions::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    protected static function dispatchContributionStatsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        $targetName = json_encode(
            app('livewire.factory')->resolveComponentName(ContributionStatsWidget::class),
            JSON_THROW_ON_ERROR
        );

        $livewire->js(
            'setTimeout(() => window.Livewire.getByName(' . $targetName . ').forEach(w => w.$refresh()), 0)'
        );
    }
}
