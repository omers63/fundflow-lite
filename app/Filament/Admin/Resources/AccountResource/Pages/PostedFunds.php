<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Resources\AccountResource;
use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Member;
use App\Services\ContributionCycleService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PostedFunds extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AccountResource::class;

    protected string $view = 'filament.admin.resources.account-resource.pages.posted-funds';

    protected static bool $shouldRegisterNavigation = false;

    public Account $record;

    public static function canAccess(array $parameters = []): bool
    {
        return AccountResource::canViewAny();
    }

    public function mount(int|string $record): void
    {
        /** @var Account $account */
        $account = AccountResource::getModel()::query()
            ->withTrashed()
            ->with(['member.user'])
            ->findOrFail($record);

        $this->record = $account;

        abort_unless(auth()->user()?->can('view', $account), 403);
    }

    public function getTitle(): string
    {
        return __('Posted Funds');
    }

    public function getSubheading(): ?string
    {
        return __('All member portal posted funds with posting cycle and attachment access.');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('backToLedger')
                ->label(__('Back to Ledger'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AccountResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn(): Builder => BankTransaction::query()
                    ->with(['member.user'])
                    ->where('raw_data->source', 'member_portal_post')
                    ->latest('transaction_date')
            )
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('member.member_number')
                    ->label(__('Member #'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('—')),
                TextColumn::make('member.user.name')
                    ->label(__('Member'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('—')),
                TextColumn::make('amount')
                    ->label(__('Amount (SAR)'))
                    ->money('SAR')
                    ->sortable(),
                TextColumn::make('posting_cycle')
                    ->label(__('Posting Cycle'))
                    ->state(function (BankTransaction $record): string {
                        if ($record->transaction_date === null) {
                            return '—';
                        }

                        return app(ContributionCycleService::class)->periodLabel(
                            (int) $record->transaction_date->month,
                            (int) $record->transaction_date->year
                        );
                    }),
                TextColumn::make('apply_mode')
                    ->label(__('Apply Mode'))
                    ->state(function (BankTransaction $record): string {
                        $raw = is_array($record->raw_data) ? $record->raw_data : [];
                        $apply = is_string($raw['apply'] ?? null) ? $raw['apply'] : null;

                        return match ($apply) {
                            'contribution' => __('Contribution'),
                            'repayment' => __('Repayment'),
                            'both' => __('Contribution(s) & Repayment'),
                            default => '—',
                        };
                    })
                    ->badge()
                    ->color(function (BankTransaction $record): string {
                        $raw = is_array($record->raw_data) ? $record->raw_data : [];
                        $apply = is_string($raw['apply'] ?? null) ? $raw['apply'] : null;

                        return match ($apply) {
                            'contribution' => 'success',
                            'repayment' => 'warning',
                            'both' => 'info',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable()
                    ->placeholder(__('—')),
                TextColumn::make('attachment')
                    ->label(__('Attachment'))
                    ->state(function (BankTransaction $record): string {
                        $raw = is_array($record->raw_data) ? $record->raw_data : [];
                        $url = is_string($raw['attachment_url'] ?? null) ? $raw['attachment_url'] : null;
                        $path = is_string($raw['attachment_path'] ?? null) ? $raw['attachment_path'] : null;

                        if ($url !== null && $url !== '') {
                            return __('View');
                        }

                        if ($path !== null && $path !== '') {
                            return __('View');
                        }

                        return '—';
                    })
                    ->url(function (BankTransaction $record): ?string {
                        $raw = is_array($record->raw_data) ? $record->raw_data : [];
                        $url = is_string($raw['attachment_url'] ?? null) ? $raw['attachment_url'] : null;
                        $path = is_string($raw['attachment_path'] ?? null) ? $raw['attachment_path'] : null;

                        if ($url !== null && $url !== '') {
                            return $url;
                        }

                        if ($path !== null && $path !== '') {
                            try {
                                return Storage::url($path);
                            } catch (\Throwable) {
                                return null;
                            }
                        }

                        return null;
                    })
                    ->openUrlInNewTab()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label(__('Member'))
                    ->searchable()
                    ->options(fn() => Member::query()->with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\Filter::make('transaction_date')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')->label(__('From')),
                        \Filament\Forms\Components\DatePicker::make('until')->label(__('Until')),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q, $from) => $q->whereDate('transaction_date', '>=', $from))
                            ->when($data['until'] ?? null, fn(Builder $q, $until) => $q->whereDate('transaction_date', '<=', $until));
                    }),
            ])
            ->emptyStateHeading(__('No posted funds found'))
            ->emptyStateDescription(__('Member portal posted funds will appear here once submitted.'));
    }
}

