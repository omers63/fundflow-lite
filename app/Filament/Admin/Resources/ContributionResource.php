<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContributionResource\Pages;
use App\Filament\Admin\Widgets\ContributionStatsWidget;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ContributionCycleService;
use Carbon\Carbon;
use App\Services\ContributionImportService;
use Filament\Actions\Action;
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
use Closure;
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
                ->visible(fn(Get $get): bool => (bool) $get('is_late'))
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
                Action::make('importContributions')
                    ->label('Import Contributions')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn(): bool => static::canCreate())
                    ->modalHeading('Import contributions from CSV')
                    ->modalDescription(
                        'First row must be headers. One of member_id or member_number is required per row. ' .
                        'Required: month, year, amount. Month may be 1–12 or a name (e.g. January). ' .
                        'Optional: paid_at (defaults to now), reference_number, notes, is_late (0/1 or yes/no), late_fee_amount (SAR; if omitted and is_late, uses system default), ' .
                        'payment_method (leave empty for admin entry: ' . implode(', ', array_keys(Contribution::paymentMethodOptions())) . ').'
                    )
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
                                $preview .= "\n… and " . (count($result['errors']) - 8) . ' more';
                            }
                            $body .= "\n\n" . $preview;
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
                    ->formatStateUsing(fn($state) => date('F', mktime(0, 0, 0, $state, 1)))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => Contribution::paymentMethodLabel($state))
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
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map(fn($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)))),
                Tables\Filters\Filter::make('year')
                    ->schema([Forms\Components\TextInput::make('year')->numeric()->default(now()->year)])
                    ->query(fn($query, $data) => $data['year'] ? $query->where('year', $data['year']) : $query),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Source')
                    ->options(fn(): array => Contribution::paymentMethodOptions()),
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
                            ->when($data['paid_from'] ?? null, fn($q) => $q->whereDate('paid_at', '>=', $data['paid_from']))
                            ->when($data['paid_until'] ?? null, fn($q) => $q->whereDate('paid_at', '<=', $data['paid_until']));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->schema([
                        Forms\Components\TextInput::make('amount_min')->label('Min amount (SAR)')->numeric(),
                        Forms\Components\TextInput::make('amount_max')->label('Max amount (SAR)')->numeric(),
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
            ])
            ->recordUrl(null)
            ->recordAction(function (Model $record): ?string {
                if (!$record instanceof Contribution) {
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
