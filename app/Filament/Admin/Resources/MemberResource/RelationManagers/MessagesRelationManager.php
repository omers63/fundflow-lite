<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Models\DirectMessage;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'directMessages';

    protected static ?string $title = 'Messages';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                /** @var Member $member */
                $member = $this->getOwnerRecord();
                $memberUserId = (int) $member->user_id;

                return $query
                    ->where(function (Builder $q) use ($memberUserId): void {
                        $q->where('from_user_id', $memberUserId)
                            ->orWhere('to_user_id', $memberUserId);
                    })
                    ->where(function (Builder $q) use ($memberUserId): void {
                        $q->where(function (Builder $q2) use ($memberUserId): void {
                            $q2->where('from_user_id', $memberUserId)
                                ->whereHas('recipient', fn(Builder $r) => $r->where('role', 'admin'));
                        })->orWhere(function (Builder $q2) use ($memberUserId): void {
                            $q2->where('to_user_id', $memberUserId)
                                ->whereHas('sender', fn(Builder $s) => $s->where('role', 'admin'));
                        });
                    })
                    ->with(['sender', 'recipient']);
            })
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('send_message')
                    ->label('Send Message')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->visible(fn(): bool => $this->getOwnerRecord()->user !== null)
                    ->modalHeading(fn(): string => 'Send Message to ' . ($this->getOwnerRecord()->user->name ?? 'Member'))
                    ->modalWidth('lg')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\Textarea::make('body')
                            ->label('Message')
                            ->required()
                            ->rows(5)
                            ->maxLength(3000),
                    ])
                    ->action(function (array $data): void {
                        /** @var Member $member */
                        $member = $this->getOwnerRecord();

                        DirectMessage::create([
                            'from_user_id' => auth()->id(),
                            'to_user_id' => $member->user_id,
                            'subject' => $data['subject'],
                            'body' => $data['body'],
                        ]);

                        Notification::make()
                            ->title('New Message from Administration')
                            ->body($data['subject'] . ': ' . mb_strimwidth($data['body'], 0, 100, '...'))
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->iconColor('info')
                            ->actions([
                                \Filament\Actions\Action::make('view')
                                    ->label('View Inbox')
                                    ->url(route('filament.member.pages.my-inbox-page')),
                            ])
                            ->sendToDatabase($member->user);

                        Notification::make()
                            ->title('Message sent to ' . ($member->user->name ?? 'member'))
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('direction')
                    ->label('Direction')
                    ->badge()
                    ->getStateUsing(function (DirectMessage $record): string {
                        $memberUserId = (int) $this->getOwnerRecord()->user_id;

                        return (int) $record->from_user_id === $memberUserId
                            ? 'Member -> Admin'
                            : 'Admin -> Member';
                    })
                    ->color(fn(string $state): string => $state === 'Member -> Admin' ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->formatStateUsing(fn(?string $state): string => filled($state) ? $state : 'No subject')
                    ->searchable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('Message')
                    ->limit(120)
                    ->searchable(),
                Tables\Columns\TextColumn::make('sender.name')
                    ->label('From')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient.name')
                    ->label('To')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read')
                    ->badge()
                    ->formatStateUsing(fn($state): string => $state ? 'Read' : 'Unread')
                    ->color(fn($state): string => $state ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->options([
                        'member_to_admin' => 'Member -> Admin',
                        'admin_to_member' => 'Admin -> Member',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $memberUserId = (int) $this->getOwnerRecord()->user_id;

                        return match ($data['value'] ?? null) {
                            'member_to_admin' => $query->where('from_user_id', $memberUserId),
                            'admin_to_member' => $query->where('to_user_id', $memberUserId),
                            default => $query,
                        };
                    }),
                Tables\Filters\TernaryFilter::make('read_at')
                    ->label('Read state')
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->schema([
                        Forms\Components\Textarea::make('body')
                            ->label('Reply')
                            ->required()
                            ->rows(4)
                            ->maxLength(3000),
                    ])
                    ->action(function (DirectMessage $record, array $data): void {
                        $member = $this->getOwnerRecord();
                        $toUserId = (int) $member->user_id;

                        DirectMessage::create([
                            'from_user_id' => auth()->id(),
                            'to_user_id' => $toUserId,
                            'parent_id' => $record->parent_id ?: $record->id,
                            'subject' => $record->subject,
                            'body' => $data['body'],
                        ]);

                        $recipient = User::find($toUserId);
                        if ($recipient) {
                            Notification::make()
                                ->title('Reply: ' . ($record->subject ?: 'Message'))
                                ->body(auth()->user()->name . ': ' . mb_strimwidth($data['body'], 0, 100, '...'))
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->iconColor('info')
                                ->actions([
                                    \Filament\Actions\Action::make('view')
                                        ->label('View Inbox')
                                        ->url(route('filament.member.pages.my-inbox-page')),
                                ])
                                ->sendToDatabase($recipient);
                        }

                        Notification::make()
                            ->title('Reply sent')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No direct messages')
            ->emptyStateDescription('No messages have been exchanged with this member yet.');
    }
}

