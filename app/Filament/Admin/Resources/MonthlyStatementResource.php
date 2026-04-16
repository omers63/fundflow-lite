<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MonthlyStatementResource\Pages;
use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MonthlyStatement;
use Filament\Actions\Action;
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

    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('member_id')
                ->label('Member')
                ->options(fn() => Member::with('user')->get()->pluck('user.name', 'id'))
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')->money('SAR'),
                Tables\Columns\TextColumn::make('total_contributions')->money('SAR'),
                Tables\Columns\TextColumn::make('total_repayments')->money('SAR'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->money('SAR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\Filter::make('period')
                    ->schema([Forms\Components\TextInput::make('period')->placeholder('YYYY-MM')])
                    ->query(fn($query, $data) => $data['period'] ? $query->where('period', $data['period']) : $query),
                Tables\Filters\SelectFilter::make('period_year')
                    ->label('Year')
                    ->options(array_combine(
                        range((int) now()->year, (int) now()->year - 15),
                        range((int) now()->year, (int) now()->year - 15)
                    ))
                    ->query(fn($query, $state) => $state ? $query->where('period', 'like', $state . '-%') : $query),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('generate_all')
                    ->label('Generate This Month')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $period = now()->format('Y-m');
                        $generated = 0;

                        Member::active()->with(['contributions', 'loans.installments'])->each(function (Member $member) use ($period, &$generated) {
                            [$year, $month] = explode('-', $period);

                            $contributions = Contribution::where('member_id', $member->id)
                                ->where('month', (int) $month)
                                ->where('year', (int) $year)
                                ->sum('amount');

                            $repayments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
                                ->whereMonth('due_date', $month)
                                ->whereYear('due_date', $year)
                                ->where('status', 'paid')
                                ->sum('amount');

                            $lastStatement = MonthlyStatement::where('member_id', $member->id)
                                ->orderByDesc('period')
                                ->first();

                            $opening = $lastStatement?->closing_balance ?? 0;
                            $closing = $opening + $contributions - $repayments;

                            MonthlyStatement::upsertForMember($member->id, $period, [
                                'opening_balance' => $opening,
                                'total_contributions' => $contributions,
                                'total_repayments' => $repayments,
                                'closing_balance' => $closing,
                                'generated_at' => now(),
                            ]);
                            $generated++;
                        });

                        Notification::make()
                            ->title("Generated {$generated} statements for {$period}")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
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
