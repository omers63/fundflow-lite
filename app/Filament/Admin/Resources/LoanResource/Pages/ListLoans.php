<?php

namespace App\Filament\Admin\Resources\LoanResource\Pages;

use App\Filament\Admin\Resources\LoanResource;
use App\Services\LoanImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importLoans')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => LoanResource::canCreate())
                ->modalHeading('Import loans from CSV')
                ->modalDescription(
                    'Column loan_status: pending (no ledger; amount_requested or amount_approved), approved (no ledger; approved row like Filament approve), '.
                    'active (default; disbursed + ledger), completed or early_settled (historical paid-off: full disbursement + bulk repayments, all installments marked paid). '.
                    'Disbursed rows: member_portion + master_portion = amount_approved (not inferred from current fund balance). '.
                    'Repayments: paid_installments_count × min_monthly_installment, or total_amount_repaid when set. '.
                    'If opening balances already reflect these loans, posting again will double-count unless you adjust imports accordingly.'
                )
                ->modalWidth('2xl')
                ->schema([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('loan-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relative = $data['csv_file'];
                    $fullPath = Storage::disk('local')->path($relative);

                    try {
                        $result = app(LoanImportService::class)->import($fullPath);
                    } finally {
                        Storage::disk('local')->delete($relative);
                    }

                    $body = "Created: {$result['created']} · Failed: {$result['failed']}";

                    if ($result['errors'] !== []) {
                        $preview = implode("\n", array_slice($result['errors'], 0, 8));
                        if (count($result['errors']) > 8) {
                            $preview .= "\n… and ".(count($result['errors']) - 8).' more';
                        }
                        $body .= "\n\n".$preview;
                    }

                    Notification::make()
                        ->title('Loan import finished')
                        ->body($body)
                        ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
