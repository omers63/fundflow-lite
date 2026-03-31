<?php

namespace App\Filament\Admin\Resources\AccountResource\Pages;

use App\Filament\Admin\Resources\AccountResource;
use App\Models\Account;
use App\Models\AccountTransaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewAccount extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AccountTransaction::query()
                    ->where('account_id', $this->record->id)
                    ->latest('transacted_at')
            )
            ->heading('Ledger Entries')
            ->columns([
                Tables\Columns\TextColumn::make('transacted_at')
                    ->label('Date')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('entry_type')
                    ->label('Type')
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->color(fn (AccountTransaction $r) => $r->entry_type === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->placeholder('—'),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('postedBy.name')->label('Posted By'),
            ])
            ->defaultSort('transacted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('entry_type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
            ])
            ->paginated([25, 50, 100]);
    }
}
