<?php

namespace App\Filament\Admin\Resources\BankTransactionResource\Pages;

use App\Filament\Admin\Pages\BankingPage;
use App\Filament\Admin\Resources\BankTransactionResource;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\Member;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class CreateBankTransaction extends CreateRecord
{
    protected static string $resource = BankTransactionResource::class;

    public function form(Schema $schema): Schema
    {
        $memberOptions = fn() => Member::query()
            ->with('user')
            ->active()
            ->get()
            ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"]);

        return $this->defaultForm($schema)->schema([
            Section::make('Manual bank transaction')
                ->description('Creates a transaction row without a CSV import. The bank must have at least one CSV import template defined.')
                ->schema([
                    Forms\Components\Select::make('bank_id')
                        ->label('Bank')
                        ->options(Bank::active()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('transaction_date')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix('SAR'),
                    Forms\Components\Select::make('transaction_type')
                        ->options([
                            'credit' => 'Credit',
                            'debit' => 'Debit',
                        ])
                        ->required()
                        ->default('credit'),
                    Forms\Components\TextInput::make('reference')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('member_id')
                        ->label('Member (optional)')
                        ->options($memberOptions)
                        ->searchable()
                        ->placeholder('—')
                        ->helperText('Optional. You can link or post to a member later from the transaction view.'),
                ])->columns(2),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $bank = Bank::query()->findOrFail($data['bank_id']);
        $template = $bank->defaultTemplate() ?? $bank->importTemplates()->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'bank_id' => 'Add at least one CSV import template for this bank before creating manual transactions.',
            ]);
        }

        $session = BankImportSession::query()->firstOrCreate(
            [
                'bank_id' => $bank->id,
                'filename' => '__manual_entry__',
            ],
            [
                'template_id' => $template->id,
                'imported_by' => auth()->id(),
                'file_path' => 'manual',
                'status' => 'completed',
                'total_rows' => 0,
                'imported_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
                'notes' => 'System session for manually entered bank transactions.',
                'completed_at' => now(),
            ]
        );

        $data['import_session_id'] = $session->id;
        $data['is_duplicate'] = false;
        if (empty($data['member_id'])) {
            $data['member_id'] = null;
        }
        $data['raw_data'] = [
            'source' => 'manual',
            'created_by_user_id' => auth()->id(),
        ];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BankingPage::getUrl([
            'activeTab' => 'banks',
            'banksSubTab' => 'transactions',
        ]);
    }
}
