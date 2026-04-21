<?php

namespace App\Filament\Admin\Resources\MemberResource\RelationManagers;

use App\Filament\Admin\Resources\AccountResource;
use App\Filament\Admin\Resources\MemberResource\Concerns\InteractsWithMemberCycleHeaderActions;
use App\Models\Account;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class AccountsRelationManager extends RelationManager
{
    use InteractsWithMemberCycleHeaderActions;

    protected static string $relationship = 'accounts';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Accounts');
    }

    /**
     * Allow header cycle actions on member View pages (panel defaults to read-only RMs).
     */
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
            ->recordTitleAttribute('name')
            ->recordUrl(fn(Account $record): string => AccountResource::getUrl('view', ['record' => $record]))
            ->striped()
            ->headerActions([
                $this->allocateCycleHeaderAction(),
                $this->contributeCycleHeaderAction(),
                $this->repaymentCycleHeaderAction(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->formatStateUsing(fn(Account $r) => $r->type_label)
                    ->color(fn(Account $r) => $r->type_color)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('loan_id')->label(__('Loan #'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('Balance (SAR)'))
                    ->money('SAR')
                    ->color(fn(Account $r) => (float) $r->balance >= 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Account type'))
                    ->options([
                        Account::TYPE_MEMBER_CASH => __('Member Cash'),
                        Account::TYPE_MEMBER_FUND => __('Member Fund'),
                        Account::TYPE_LOAN => __('Loan'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\TernaryFilter::make('loan_linked')
                    ->label(__('Loan account'))
                    ->trueLabel(__('Linked to a loan'))
                    ->falseLabel(__('Not a loan account'))
                    ->queries(
                        true: fn($q) => $q->whereNotNull('loan_id'),
                        false: fn($q) => $q->whereNull('loan_id'),
                    ),
                Tables\Filters\Filter::make('balance')
                    ->schema([
                        Forms\Components\TextInput::make('balance_min')->label(__('Min balance (SAR)'))->numeric(),
                        Forms\Components\TextInput::make('balance_max')->label(__('Max balance (SAR)'))->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['balance_min'] ?? null), fn($q) => $q->where('balance', '>=', $data['balance_min']))
                            ->when(filled($data['balance_max'] ?? null), fn($q) => $q->where('balance', '<=', $data['balance_max']));
                    }),
            ]);
    }
}
