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
use Filament\Actions\CreateAction;
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

    public static function getNavigationLabel(): string
    {
        return __('app.resource.statements');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'finance';
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
                ->label(__('app.field.member'))
                ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                    ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"]))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('period')
                ->placeholder(__('app.statement.period_input_placeholder'))
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
                    ->label(__('app.field.member_number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label(__('app.field.member'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label(__('app.field.opening_balance'))
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_contributions')
                    ->label(__('app.field.total_contributions'))
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total_repayments')
                    ->label(__('app.field.total_repayments'))
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->label(__('app.field.closing_balance'))
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label(__('app.action.generate'))
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('notified_at')
                    ->label(__('app.action.send'))
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
                    ->label(__('app.field.member'))
                    ->searchable()
                    ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"])),
                Tables\Filters\Filter::make('period')
                    ->schema([Forms\Components\TextInput::make('period')->placeholder('YYYY-MM')])
                    ->query(fn ($query, $data) => $data['period'] ? $query->where('period', $data['period']) : $query),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label(__('app.field.year'))
                    ->options(array_combine(
                        range((int) now()->year, (int) now()->year - 15),
                        range((int) now()->year, (int) now()->year - 15)
                    ))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('period', 'like', $data['value'].'-%')
                        : $query),
                Tables\Filters\Filter::make('not_notified')
                    ->label(__('app.statement.not_notified'))
                    ->query(fn ($q) => $q->whereNull('notified_at')),
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('app.action.new_statement'))
                    ->icon('heroicon-o-plus-circle'),
                // ── Generate previous month + send ────────────────────────
                Action::make('generate_and_send')
                    ->label(__('app.action.generate_send'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(__('app.statement.generate_send_heading'))
                    ->modalDescription(function () {
                        $period = now()->subMonth()->format('Y-m');
                        $autoEmail = Setting::statementAutoEmail()
                            ? __('app.statement.auto_email_on')
                            : __('app.statement.auto_email_off');

                        return __('app.statement.generate_send_desc', [
                            'period' => $period,
                            'auto_email' => $autoEmail,
                        ]);
                    })
                    ->action(function () {
                        $period = now()->subMonth()->format('Y-m');
                        $notify = Setting::statementAutoEmail();
                        $count = app(MonthlyStatementService::class)->generateForAllMembers($period, $notify);
                        $msg = __('app.statement.generated_count', ['count' => $count, 'period' => $period]);
                        if ($notify) {
                            $msg .= ' '.__('app.statement.notifications_sent');
                        }
                        Notification::make()->title($msg)->success()->send();
                    }),

                // ── Generate for any chosen period ────────────────────────
                Action::make('generate_for_period')
                    ->label(__('app.action.generate_for_period'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->schema([
                        Forms\Components\TextInput::make('period')
                            ->label(__('app.statement.period_input_label'))
                            ->required()
                            ->placeholder(now()->subMonth()->format('Y-m'))
                            ->regex('/^\d{4}-\d{2}$/'),
                        Forms\Components\Toggle::make('send_notification')
                            ->label(__('app.statement.email_members_after_generation'))
                            ->default(Setting::statementAutoEmail()),
                        Forms\Components\Select::make('member_id')
                            ->label(__('app.statement.specific_member'))
                            ->searchable()
                            ->options(fn () => Member::with('user')->orderBy('member_number')->get()
                                ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->member_number} — {$m->user->name}"]))
                            ->placeholder(__('app.statement.all_active_members')),
                    ])
                    ->action(function (array $data) {
                        $svc = app(MonthlyStatementService::class);
                        $period = $data['period'];
                        $notify = (bool) ($data['send_notification'] ?? false);

                        if ($data['member_id'] ?? null) {
                            $member = Member::find((int) $data['member_id']);
                            if (! $member) {
                                Notification::make()->title(__('app.statement.member_not_found'))->danger()->send();

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
                        ->label(__('app.action.download'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (MonthlyStatement $r) => route('admin.statement.pdf', $r))
                        ->openUrlInNewTab(),

                    // ── Send to member ────────────────────────────────────────
                    Action::make('send_to_member')
                        ->label(__('app.action.send'))
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
                        ->label(__('app.action.generate'))
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
