<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MembershipApplicationResource\Pages;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Notifications\MembershipApprovedNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\MemberNumberService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;
    protected static string|\BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = 'Applications';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.membership');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) MembershipApplication::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Applicant Information')
                ->schema([
                    Forms\Components\TextInput::make('user.name')
                        ->label('Full Name')
                        ->disabled(),
                    Forms\Components\TextInput::make('user.email')
                        ->label('Email')
                        ->disabled(),
                    Forms\Components\TextInput::make('user.phone')
                        ->label('Phone')
                        ->disabled(),
                ])->columns(3),

            Section::make('Identity & Address')
                ->schema([
                    Forms\Components\TextInput::make('national_id')
                        ->label('National ID')
                        ->disabled(),
                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->disabled(),
                    Forms\Components\TextInput::make('city')
                        ->disabled(),
                    Forms\Components\Textarea::make('address')
                        ->columnSpanFull()
                        ->disabled(),
                ])->columns(3),

            Section::make('Employment')
                ->schema([
                    Forms\Components\TextInput::make('occupation')->disabled(),
                    Forms\Components\TextInput::make('employer')->disabled(),
                    Forms\Components\TextInput::make('monthly_income')
                        ->label('Monthly Income (SAR)')
                        ->disabled(),
                ])->columns(3),

            Section::make('Next of Kin')
                ->schema([
                    Forms\Components\TextInput::make('next_of_kin_name')
                        ->label('Name')
                        ->disabled(),
                    Forms\Components\TextInput::make('next_of_kin_phone')
                        ->label('Phone')
                        ->disabled(),
                ])->columns(2),

            Section::make('Application Document')
                ->schema([
                    Forms\Components\FileUpload::make('application_form_path')
                        ->label('Uploaded Form')
                        ->disk('public')
                        ->disabled()
                        ->downloadable(),
                ]),

            Section::make('Review')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                        ->disabled(),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('city')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MembershipApplication $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Membership Application')
                    ->modalDescription('Are you sure you want to approve this application? The applicant will be notified via email, SMS, and WhatsApp.')
                    ->action(function (MembershipApplication $record) {
                        $memberNumberService = app(MemberNumberService::class);
                        $memberNumber = $memberNumberService->generate();

                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        $record->user->update(['status' => 'approved']);

                        Member::create([
                            'user_id' => $record->user_id,
                            'member_number' => $memberNumber,
                            'joined_at' => now()->toDateString(),
                            'status' => 'active',
                        ]);

                        try {
                            $record->user->notify(new MembershipApprovedNotification($memberNumber));
                        } catch (\Throwable $e) {
                            // notifications are best-effort; log silently
                        }

                        Notification::make()
                            ->title('Application Approved')
                            ->body("Member {$record->user->name} has been approved with number {$memberNumber}.")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MembershipApplication $record) => $record->status === 'pending')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for rejecting this application...'),
                    ])
                    ->action(function (MembershipApplication $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        $record->user->update(['status' => 'rejected']);

                        try {
                            $record->user->notify(new MembershipRejectedNotification($data['rejection_reason']));
                        } catch (\Throwable $e) {
                            // notifications are best-effort
                        }

                        Notification::make()
                            ->title('Application Rejected')
                            ->body("Application for {$record->user->name} has been rejected.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembershipApplications::route('/'),
            'view' => Pages\ViewMembershipApplication::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}
