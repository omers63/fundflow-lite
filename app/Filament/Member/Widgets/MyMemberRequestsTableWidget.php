<?php

declare(strict_types=1);

namespace App\Filament\Member\Widgets;

use App\Models\Member;
use App\Models\MemberRequest;
use App\Services\MemberRequestService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

class MyMemberRequestsTableWidget extends TableWidget
{
    protected static bool $isDiscovered = true;

    protected static ?string $heading = 'Your requests';

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('Your requests');
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $memberId = $this->member()?->id ?? 0;

        return MemberRequest::query()
            ->where('requester_member_id', $memberId);
    }

    public function table(Table $table): Table
    {
        $service = app(MemberRequestService::class);

        return $table
            ->description(__('Track requests you have submitted. Use the actions above to ask for independence or to add or remove a dependent. Pending items are reviewed by administration.'))
            ->headerActions([
                Action::make('request_independence')
                    ->label(__('Become independent'))
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->visible(fn (): bool => $this->member()?->parent_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading(__('Request independence'))
                    ->modalDescription(__('You will no longer be sponsored under a parent member. Allocation updates are already self-service, while dependent-link changes continue through requests.'))
                    ->action(function () use ($service): void {
                        $member = $this->member();
                        if (! $member) {
                            return;
                        }
                        try {
                            $service->submit($member, MemberRequest::TYPE_REQUEST_INDEPENDENCE, []);
                            Notification::make()->title(__('Request submitted'))->success()->send();
                        } catch (ValidationException $e) {
                            $this->validationToNotification($e);
                        }
                    }),

                Action::make('request_add_dependent')
                    ->label(__('Request to add a dependent'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->member() !== null)
                    ->schema([
                        Forms\Components\Textarea::make('details')
                            ->label(__('Who should be added?'))
                            ->required()
                            ->rows(4)
                            ->helperText(__('Include name and any details the office needs to link a new or existing member.')),
                    ])
                    ->action(function (array $data) use ($service): void {
                        $member = $this->member();
                        if (! $member) {
                            return;
                        }
                        try {
                            $service->submit($member, MemberRequest::TYPE_ADD_DEPENDENT, [
                                'details' => $data['details'],
                            ]);
                            Notification::make()->title(__('Request submitted'))->success()->send();
                        } catch (ValidationException $e) {
                            $this->validationToNotification($e);
                        }
                    }),

                Action::make('request_remove_dependent')
                    ->label(__('Request to remove a dependent'))
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (): bool => $this->member()?->dependents()->exists() ?? false)
                    ->schema([
                        Forms\Components\Select::make('dependent_member_id')
                            ->label(__('Dependent'))
                            ->options(function (): array {
                                $m = $this->member();
                                if (! $m) {
                                    return [];
                                }

                                return $m->dependents()
                                    ->with('user')
                                    ->orderBy('member_number')
                                    ->get()
                                    ->mapWithKeys(fn (Member $d): array => [
                                        $d->id => ($d->user?->name ?? __('Member')).' (#'.($d->member_number ?? $d->id).')',
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data) use ($service): void {
                        $member = $this->member();
                        if (! $member) {
                            return;
                        }
                        try {
                            $service->submit($member, MemberRequest::TYPE_REMOVE_DEPENDENT, [
                                'dependent_member_id' => (int) $data['dependent_member_id'],
                            ]);
                            Notification::make()->title(__('Request submitted'))->success()->send();
                        } catch (ValidationException $e) {
                            $this->validationToNotification($e);
                        }
                    }),
            ])
            ->columns([
                TextColumn::make('type')
                    ->label(__('Request'))
                    ->formatStateUsing(fn (string $state): string => MemberRequest::typeLabel($state)),
                TextColumn::make('details_display')
                    ->label(__('Details'))
                    ->visibleFrom('md')
                    ->getStateUsing(fn (MemberRequest $record): string => $record->describePayload())
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MemberRequest::STATUS_PENDING => __('Pending'),
                        MemberRequest::STATUS_APPROVED => __('Approved'),
                        MemberRequest::STATUS_REJECTED => __('Rejected'),
                        MemberRequest::STATUS_CANCELLED => __('Cancelled'),
                        default => __(ucfirst(str_replace('_', ' ', $state))),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        MemberRequest::STATUS_PENDING => 'warning',
                        MemberRequest::STATUS_APPROVED => 'success',
                        MemberRequest::STATUS_REJECTED => 'danger',
                        MemberRequest::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('admin_note')
                    ->label(__('Admin note'))
                    ->visibleFrom('lg')
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->formatStateUsing(fn ($state): string => $state
                        ? \Illuminate\Support\Carbon::parse($state)->locale(app()->getLocale())->translatedFormat('d M Y H:i')
                        : '')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function member(): ?Member
    {
        return Member::where('user_id', auth()->id())->first();
    }

    protected function validationToNotification(ValidationException $e): void
    {
        $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
        Notification::make()->title(__('Could not submit'))->body($msg)->danger()->send();
    }
}
