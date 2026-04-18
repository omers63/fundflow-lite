<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MonthlyStatementResource\Pages;
use App\Models\Member;
use App\Models\MonthlyStatement;
use App\Models\Setting;
use App\Services\MonthlyStatementService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonthlyStatementResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Statements';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = MonthlyStatement::whereNull('notified_at')
            ->where('generated_at', '>=', now()->subDays(7))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('member_id')
                ->label('Member')
                ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"]))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('period')
                ->placeholder('YYYY-MM')
                ->required(),
            Forms\Components\TextInput::make('opening_balance')->numeric()->prefix('SAR')->required(),
            Forms\Components\TextInput::make('total_contributions')->numeric()->prefix('SAR')->required(),
            Forms\Components\TextInput::make('total_repayments')->numeric()->prefix('SAR')->required(),
            Forms\Components\TextInput::make('closing_balance')->numeric()->prefix('SAR')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Opening')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_contributions')
                    ->label('Contributions')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_repayments')
                    ->label('Repayments')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->label('Closing')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generated')
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('notified_at')
                    ->label('Notified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn (MonthlyStatement $r) => $r->notified_at !== null),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"])),
                Tables\Filters\Filter::make('period')
                    ->schema([Forms\Components\TextInput::make('period')->placeholder('YYYY-MM')])
                    ->query(fn ($query, $data) => $data['period'] ? $query->where('period', $data['period']) : $query),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label('Year')
                    ->options(array_combine(
                        range((int) now()->year, (int) now()->year - 15),
                        range((int) now()->year, (int) now()->year - 15)
                    ))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('period', 'like', $data['value'].'-%')
                        : $query),
                Tables\Filters\Filter::make('not_notified')
                    ->label('Not yet notified')
                    ->query(fn ($q) => $q->whereNull('notified_at')),
                TrashedFilter::make(),
            ])
            ->headerActions([
                // ── Generate previous month + send ────────────────────────
                Action::make('generate_and_send')
                    ->label('Generate + Send')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Generate & send statements')
                    ->modalDescription(function () {
                        $period = now()->subMonth()->format('Y-m');
                        $autoEmail = Setting::statementAutoEmail() ? 'Yes' : 'No (auto-email disabled in settings)';

                        return "Generate statements for all active members for {$period} and email them the PDF. Auto-email setting: {$autoEmail}.";
                    })
                    ->action(function () {
                        $period = now()->subMonth()->format('Y-m');
                        $notify = Setting::statementAutoEmail();
                        $count = app(MonthlyStatementService::class)->generateForAllMembers($period, $notify);
                        $msg = "Generated {$count} statements for {$period}.";
                        if ($notify) {
                            $msg .= ' Notifications sent.';
                        }
                        Notification::make()->title($msg)->success()->send();
                    }),

                // ── Generate for any chosen period ────────────────────────
                Action::make('generate_for_period')
                    ->label('Generate for Period…')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->schema([
                        Forms\Components\TextInput::make('period')
                            ->label('Period (YYYY-MM)')
                            ->required()
                            ->placeholder(now()->subMonth()->format('Y-m'))
                            ->regex('/^\d{4}-\d{2}$/'),
                        Forms\Components\Toggle::make('send_notification')
                            ->label('Email members after generation')
                            ->default(Setting::statementAutoEmail()),
                        Forms\Components\Select::make('member_id')
                            ->label('Specific member (leave blank for all)')
                            ->searchable()
                            ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                                ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"]))
                            ->placeholder('All active members'),
                    ])
                    ->action(function (array $data) {
                        $svc = app(MonthlyStatementService::class);
                        $period = $data['period'];
                        $notify = (bool) ($data['send_notification'] ?? false);

                        if ($data['member_id'] ?? null) {
                            $member = Member::find((int) $data['member_id']);
                            if (! $member) {
                                Notification::make()->title('Member not found')->danger()->send();

                                return;
                            }
                            $stmt = $svc->generateForMember($member, $period, $notify);
                            Notification::make()->title("Statement #{$stmt->id} generated for {$member->member_number}.")->success()->send();

                            return;
                        }

                        $count = $svc->generateForAllMembers($period, $notify);
                        Notification::make()
                            ->title("Generated {$count} statements for {$period}.".($notify ? ' Notifications sent.' : ''))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // ── Download PDF ──────────────────────────────────────────
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (MonthlyStatement $r) => route('admin.statement.pdf', $r))
                        ->openUrlInNewTab(),

                    // ── Send to member ────────────────────────────────────────
                    Action::make('send_to_member')
                        ->label('Send to Member')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(fn (MonthlyStatement $r) => "Send statement to {$r->member->user->name}")
                        ->modalDescription(fn (MonthlyStatement $r) => "This will email the statement PDF for {$r->period_formatted} to {$r->member->user->email}.")
                        ->action(function (MonthlyStatement $record) {
                            app(MonthlyStatementService::class)->sendNotification($record);
                            Notification::make()->title("Statement sent to {$record->member->user->name}")->success()->send();
                        }),

                    // ── Regenerate ────────────────────────────────────────────
                    Action::make('regenerate')
                        ->label('Regenerate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will recalculate all financial figures for this statement. Existing data will be overwritten.')
                        ->action(function (MonthlyStatement $record) {
                            $stmt = app(MonthlyStatementService::class)->generateForMember(
                                $record->member,
                                $record->period,
                                false,
                            );
                            Notification::make()->title("Statement #{$stmt->id} regenerated for {$record->period}.")->success()->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthlyStatements::route('/'),
            'create' => Pages\CreateMonthlyStatement::route('/create'),
            'edit' => Pages\EditMonthlyStatement::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }
}
