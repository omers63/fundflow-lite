<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContributionResource\Pages;
use App\Filament\Admin\Widgets\ContributionStatsWidget;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ContributionCycleService;
use App\Services\ContributionImportService;
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
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('member_id')
                ->label('Member')
                ->options(fn () => Member::with('user')
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
                ->helperText(fn (string $operation): ?string => $operation === 'create'
                    ? 'Filled from the member\'s monthly contribution amount.'
                    : null),
            Forms\Components\Select::make('month')
                ->options(array_combine(range(1, 12), array_map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12))))
                ->required(),
            Forms\Components\TextInput::make('year')
                ->numeric()
                ->default(now()->year)
                ->required()
                ->rule(static function (Get $get, Field $component): Closure {
                    return function (string $attribute, mixed $value, Closure $fail) use ($get, $component): void {
                        $memberId = $get('member_id');
                        $month = $get('month');

                        if (! filled($memberId) || ! filled($month) || ! filled($value)) {
                            return;
                        }

                        $exceptId = ($record = $component->getRecord()) instanceof Contribution
                            ? (int) $record->getKey()
                            : null;

                        if (! Contribution::activePeriodExists((int) $memberId, (int) $month, (int) $value, $exceptId)) {
                            return;
                        }

                        $fail(Contribution::duplicateCycleMessage((int) $month, (int) $value));
                    };
                }),
            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Payment Date')
                ->default(now()),
            Forms\Components\Checkbox::make('is_late')
                ->label('Late payment')
                ->helperText('Override whether this contribution counts as late for compliance. The automatic flag from contribution runs can be corrected here.')
                ->default(false),
            Forms\Components\TextInput::make('late_fee_amount')
                ->label('Late fee (SAR)')
                ->numeric()
                ->prefix('SAR')
                ->nullable()
                ->visible(fn (Get $get): bool => (bool) $get('is_late'))
                ->helperText('Credited to master cash only (not master fund). Leave empty to use the configured default when saving.'),
            Forms\Components\TextInput::make('reference_number')
                ->label('Reference #')
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
            ->headerActions([
                Action::make('exportCsv')
                    ->label('Export Contributions')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function () {
                        $filename = 'contributions-' . now()->format('Y-m-d') . '.csv';

                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'id', 'member_number', 'member_name',
                                'month', 'year', 'period',
                                'amount', 'is_late', 'recorded_at',
                            ]);

                            Contribution::with('member.user')
                                ->orderByDesc('year')
                                ->orderByDesc('month')
                                ->orderBy('id')
                                ->each(function (Contribution $c) use ($handle) {
                                    fputcsv($handle, [
                                        $c->id,
                                        $c->member?->member_number,
                                        $c->member?->user?->name,
                                        $c->month,
                                        $c->year,
                                        date('F', mktime(0, 0, 0, $c->month, 1)) . ' ' . $c->year,
                                        number_format((float) $c->amount, 2, '.', ''),
                                        $c->is_late ? 'Yes' : 'No',
                                        $c->created_at?->toDateTimeString(),
                                    ]);
                                });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
                Action::make('importContributions')
                    ->label('Import Contributions')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn (): bool => static::canCreate())
                    ->modalHeading('Import contributions from CSV')
                    ->modalDescription(new HtmlString(
                        '<div class="space-y-3 text-sm">' .
                            '<div class="rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs dark:border-blue-500/30 dark:bg-blue-500/10">' .
                                '<p class="font-semibold text-blue-900 dark:text-blue-200 mb-1">Need a starter file?</p>' .
                                '<p class="text-blue-900/90 dark:text-blue-100/90">' .
                                    'Download a ready sample with common formats (numeric and month-name values): ' .
                                    '<a href="' . route('downloads.contribution-import-sample') . '" class="font-semibold text-blue-700 underline hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200">contributions-import-sample-15.csv</a>' .
                                '</p>' .
                            '</div>' .
                            '<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">' .
                                '<table class="w-full text-xs">' .
                                    '<tbody class="divide-y divide-gray-100 dark:divide-gray-800">' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 w-44 bg-gray-50 dark:bg-gray-900/30">CSV format</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">First row must be headers.</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Member identifier</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">Provide one of <code>member_id</code>, <code>member_number</code>, <code>national_id</code>, or <code>member_name</code> (or <code>name</code>) per row. If names are duplicated, use ID/number/national ID instead.</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Required fields</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300"><code>month</code>, <code>year</code>, <code>amount</code>.</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Month value</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">Use <code>1-12</code> or a month name (e.g. <code>January</code>).</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Optional fields</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300"><code>paid_at</code>, <code>reference_number</code>, <code>notes</code>, <code>is_late</code>, <code>late_fee_amount</code>, <code>payment_method</code>.</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Late values</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300"><code>is_late</code> accepts <code>0/1</code> or <code>yes/no</code>. If late and fee is blank, system default is applied.</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/30">Payment method</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">Allowed: <code>' . e(implode('</code>, <code>', array_keys(Contribution::paymentMethodOptions()))) . '</code>. Leave blank for admin entry.</td>' .
                                        '</tr>' .
                                    '</tbody>' .
                                '</table>' .
                            '</div>' .
                        '</div>'
                    ))
                    ->modalWidth('2xl')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('contribution-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $relative = $data['csv_file'];
                        $fullPath = Storage::disk('local')->path($relative);

                        try {
                            $result = app(ContributionImportService::class)->import($fullPath);
                        } finally {
                            Storage::disk('local')->delete($relative);
                        }

                        $body = "Created: {$result['created']} · Skipped: {$result['skipped']} · Failed: {$result['failed']}";

                        if ($result['errors'] !== []) {
                            $preview = implode("\n", array_slice($result['errors'], 0, 8));
                            if (count($result['errors']) > 8) {
                                $preview .= "\n… and ".(count($result['errors']) - 8).' more';
                            }
                            $body .= "\n\n".$preview;
                        }

                        Notification::make()
                            ->title('Contribution import finished')
                            ->body($body)
                            ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                            ->persistent()
                            ->send();

                        static::dispatchContributionStatsRefresh($livewire);
                    }),
                CreateAction::make()
                    ->label('New Contribution')
                    ->icon('heroicon-o-plus-circle')
                    ->modalWidth('2xl')
                    ->createAnother(false)
                    ->mutateDataUsing(function (array $data): array {
                        $data['payment_method'] = Contribution::PAYMENT_METHOD_ADMIN;
                        if (! empty($data['is_late'])) {
                            $raw = $data['late_fee_amount'] ?? null;
                            if ($raw === null || $raw === '') {
                                $at = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();
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
                    ->visible(fn (): bool => static::canCreate()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member Name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn ($state) => date('F', mktime(0, 0, 0, $state, 1)))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Contribution::paymentMethodLabel($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Late?')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('late_fee_amount')
                    ->label('Late fee')
                    ->money('SAR')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn ($query, $data) => $data['year'] ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Source')
                    ->options(fn (): array => Contribution::paymentMethodOptions()),
                Tables\Filters\TernaryFilter::make('is_late')
                    ->label('Late payment')
                    ->trueLabel('Late only')
                    ->falseLabel('On-time only'),
                Tables\Filters\Filter::make('paid_at')
                    ->schema([
                        Forms\Components\DatePicker::make('paid_from')->label('Paid from'),
                        Forms\Components\DatePicker::make('paid_until')->label('Paid until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['paid_from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min amount (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max amount (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['amount_min'] ?? null), fn ($q) => $q->where('amount', '>=', $data['amount_min']))
                            ->when(filled($data['amount_max'] ?? null), fn ($q) => $q->where('amount', '<=', $data['amount_max']));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth('2xl'),
                    EditAction::make()
                        ->modalWidth('2xl')
                        ->after(function (Component $livewire): void {
                            static::dispatchContributionStatsRefresh($livewire);
                        }),
                    DeleteAction::make()
                        ->modalDescription('Soft-deletes this contribution and reverses its fund ledger postings (master + member fund). Restoring re-posts the contribution to the ledger.'),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->recordUrl(null)
            ->recordAction(function (Model $record): ?string {
                if (! $record instanceof Contribution) {
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
            'setTimeout(() => window.Livewire.getByName('.$targetName.').forEach(w => w.$refresh()), 0)'
        );
    }
}
