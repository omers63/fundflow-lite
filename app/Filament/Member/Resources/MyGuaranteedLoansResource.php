<?php

namespace App\Filament\Member\Resources;

use App\Filament\Member\Resources\MyGuaranteedLoansResource\Pages;
use App\Models\Loan;
use App\Models\Member;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MyGuaranteedLoansResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Loans I Guarantee';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.loans');
    }

    /** Only show in the sidebar if the member is actually a guarantor for at least one loan. */
    public static function shouldRegisterNavigation(): bool
    {
        $member = Member::where('user_id', auth()->id())->first();
        if (! $member) {
            return false;
        }

        return Loan::where('guarantor_member_id', $member->id)->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $member = Member::where('user_id', auth()->id())->first();
        if (! $member) {
            return null;
        }

        // Badge = active guaranteed loans where borrower has overdue installments
        $count = Loan::where('guarantor_member_id', $member->id)
            ->where('status', 'active')
            ->whereHas('installments', fn ($q) => $q->where('status', 'overdue'))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        $member = Member::where('user_id', auth()->id())->first();

        return $table
            ->query(fn () => Loan::where('guarantor_member_id', $member?->id ?? 0)
                ->with(['member.user', 'loanTier', 'installments']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Borrower')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->visibleFrom('md')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('loanTier.label')
                    ->label('Tier')
                    ->visibleFrom('sm')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount_approved')
                    ->label('Approved')
                    ->money('SAR')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Months')
                    ->visibleFrom('lg')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'active' => 'success',
                        'completed', 'early_settled' => 'gray',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('overdue_installments_count')
                    ->label('Overdue')
                    ->getStateUsing(fn (Loan $r) => $r->installments
                        ->where('status', 'overdue')->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('late_repayment_count')
                    ->label('Late Total')
                    ->visibleFrom('md')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('guarantor_liability_transferred_at')
                    ->label('Liability Transferred')
                    ->visibleFrom('lg')
                    ->dateTime('d M Y')
                    ->placeholder('Not transferred')
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'early_settled' => 'Early Settled',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->emptyStateHeading('No guaranteed loans')
            ->emptyStateDescription('You are not currently listed as a guarantor on any loans.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->recordActions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyGuaranteedLoans::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
