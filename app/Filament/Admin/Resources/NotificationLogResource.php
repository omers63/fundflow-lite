<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Notification Logs';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.system');
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = NotificationLog::where('status', 'failed')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(NotificationLog::query()->with('user')->latest('sent_at'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Recipient')
                    ->searchable()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('channel')
                    ->label('Channel')
                    ->colors([
                        'primary' => 'mail',
                        'info' => 'database',
                        'success' => 'twilio',
                        'warning' => 'whatsapp',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'mail' => 'Email',
                        'database' => 'In-App',
                        'twilio' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (NotificationLog $r) => $r->subject),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'sent',
                        'danger' => 'failed',
                        'gray' => 'skipped',
                    ]),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'mail' => 'Email',
                        'database' => 'In-App',
                        'twilio' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                    ]),
                Tables\Filters\Filter::make('sent_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('sent_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('sent_at', '<=', $data['until']));
                    }),
                TrashedFilter::make(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Recipient')->schema([
                TextEntry::make('user.name')->label('Name')->placeholder('—'),
                TextEntry::make('user.email')->label('Email')->placeholder('—'),
                TextEntry::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'mail' => 'primary',
                        'database' => 'info',
                        'twilio' => 'success',
                        'whatsapp' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'mail' => 'Email',
                        'database' => 'In-App',
                        'twilio' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        default => $state ?? '—',
                    }),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'gray',
                    }),
                TextEntry::make('sent_at')->label('Sent At')->dateTime('d M Y H:i')->placeholder('—'),
            ])->columns(3),

            Section::make('Content')->schema([
                TextEntry::make('subject')->label('Subject')->columnSpanFull(),
                TextEntry::make('body')
                    ->label('Message Body')
                    ->html()
                    ->columnSpanFull(),
            ]),

            Section::make('Error Details')
                ->schema([
                    TextEntry::make('error_message')
                        ->label('Error')
                        ->columnSpanFull()
                        ->color('danger'),
                ])
                ->visible(fn (NotificationLog $record) => filled($record->error_message))
                ->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
            'view' => Pages\ViewNotificationLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }
}
