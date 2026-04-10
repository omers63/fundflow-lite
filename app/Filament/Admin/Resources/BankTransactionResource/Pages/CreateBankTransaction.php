<?php

namespace App\Filament\Admin\Resources\BankTransactionResource\Pages;

use App\Filament\Admin\Pages\BankingPage;
use App\Filament\Admin\Resources\BankTransactionResource;
use App\Models\Bank;
use App\Models\BankImportSession;
use App\Models\BankImportTemplate;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Member;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as SchemaFacade;
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

        $loanOptionsForMember = fn(?int $memberId) => Loan::query()
            ->where('member_id', $memberId)
            ->whereHas('disbursements')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn(Loan $loan) => [
                $loan->id => sprintf(
                    '#%d — SAR %s approved, SAR %s disbursed, %s',
                    $loan->id,
                    number_format((float) $loan->amount_approved, 2),
                    number_format((float) $loan->amount_disbursed, 2),
                    $loan->status
                ),
            ]);

        return $this->defaultForm($schema)->columns(1)->schema([
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
                        ->live()
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
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('loan_id', null);
                            $set('loan_disbursement_id', null);
                        })
                        ->placeholder('—')
                        ->helperText('Optional. You can link or post to a member later from the transaction view.'),
                    Forms\Components\Select::make('loan_id')
                        ->label('Loan (optional)')
                        ->options(fn(Get $get) => $loanOptionsForMember($get('member_id')))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn($set) => $set('loan_disbursement_id', null))
                        ->visible(fn(Get $get) => $get('transaction_type') === 'debit')
                        ->placeholder('—')
                        ->helperText('Optional. Member loan summary. Requires at least one disbursement on file.'),
                    Forms\Components\Select::make('loan_disbursement_id')
                        ->label('Loan disbursement payout (optional)')
                        ->options(fn(Get $get) => LoanDisbursement::query()
                            ->where('loan_id', $get('loan_id'))
                            ->orderByDesc('disbursed_at')
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn(LoanDisbursement $d) => [
                                $d->id => sprintf(
                                    'SAR %s on %s — disbursement #%d',
                                    number_format((float) $d->amount, 2),
                                    $d->disbursed_at?->format('d M Y') ?? '?',
                                    $d->id
                                ),
                            ]))
                        ->searchable()
                        ->preload()
                        ->visible(fn(Get $get) => $get('transaction_type') === 'debit' && filled($get('loan_id')))
                        ->placeholder('—')
                        ->helperText('Optional. Pick the specific disbursement record when linking a debit.'),
                ])->columns(2)->columnSpanFull(),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $bank = Bank::query()->findOrFail($data['bank_id']);
        $template = $bank->defaultTemplate() ?? $bank->importTemplates()->first();

        if ($template === null) {
            // Ensure manual entries can always be created even when no CSV template exists yet.
            $template = BankImportTemplate::query()->create([
                'bank_id' => $bank->id,
                'name' => 'System default (manual entries)',
                'is_default' => true,
                'delimiter' => ',',
                'encoding' => 'UTF-8',
                'has_header' => true,
                'skip_rows' => 0,
                'date_column' => 'transaction_date',
                'date_format' => 'Y-m-d',
                'amount_type' => 'single',
                'amount_column' => 'amount',
                'credit_column' => null,
                'debit_column' => null,
                'type_column' => 'transaction_type',
                'credit_indicator' => 'credit',
                'debit_indicator' => 'debit',
                'description_column' => 'description',
                'reference_column' => 'reference',
                'duplicate_match_fields' => ['date', 'amount', 'reference'],
                'duplicate_date_tolerance' => 0,
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
        if (($data['transaction_type'] ?? null) !== 'debit') {
            $data['loan_id'] = null;
            $data['loan_disbursement_id'] = null;
        } else {
            $data['loan_id'] = empty($data['loan_id']) ? null : $data['loan_id'];
            $data['loan_disbursement_id'] = empty($data['loan_disbursement_id']) ? null : $data['loan_disbursement_id'];
        }

        $hasLoanColumn = SchemaFacade::hasColumn('bank_transactions', 'loan_id');
        $hasDisbursementColumn = SchemaFacade::hasColumn('bank_transactions', 'loan_disbursement_id');
        if (!$hasLoanColumn) {
            unset($data['loan_id']);
        }
        if (!$hasDisbursementColumn) {
            unset($data['loan_disbursement_id']);
        }

        $linkLoan = ($data['transaction_type'] ?? null) === 'debit' && !empty($data['loan_id']);
        $linkDisbursement = ($data['transaction_type'] ?? null) === 'debit' && !empty($data['loan_disbursement_id']);
        if ($linkLoan xor $linkDisbursement) {
            throw ValidationException::withMessages([
                'loan_id' => 'When linking a debit to a disbursement, select both the loan and the disbursement record.',
            ]);
        }

        if ($linkLoan && $linkDisbursement) {
            if (!$hasLoanColumn || !$hasDisbursementColumn) {
                throw ValidationException::withMessages([
                    'loan_id' => 'Database is missing loan link columns. Run migrations, then retry.',
                ]);
            }
            if (empty($data['member_id'])) {
                throw ValidationException::withMessages([
                    'member_id' => 'Select a member when linking a loan disbursement.',
                ]);
            }
            $disbursement = LoanDisbursement::query()->find($data['loan_disbursement_id']);
            if (!$disbursement || (int) $disbursement->loan_id !== (int) $data['loan_id']) {
                throw ValidationException::withMessages([
                    'loan_disbursement_id' => 'Selected disbursement must belong to the selected loan.',
                ]);
            }
            $loan = Loan::query()
                ->where('id', $data['loan_id'])
                ->where('member_id', $data['member_id'])
                ->whereHas('disbursements')
                ->first();

            if (!$loan) {
                throw ValidationException::withMessages([
                    'loan_id' => 'Selected loan must belong to the selected member and must have disbursement records.',
                ]);
            }
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
