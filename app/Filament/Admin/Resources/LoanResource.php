<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LoanResource\Pages;
use App\Filament\Admin\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;
use App\Filament\Admin\Widgets\LoanStatsWidget;
use App\Models\Account;
use App\Models\FundTier;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Notifications\LoanApprovedNotification;
use App\Notifications\LoanCancelledNotification;
use App\Notifications\LoanDisbursedNotification;
use App\Notifications\LoanPartialDisbursementNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Services\AccountingService;
use App\Services\LoanImportService;
use App\Services\LoanEarlySettlementService;
use App\Services\LoanEligibilityService;
use App\Services\LoanQueueOrderingService;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Facades\FilamentView;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.finance');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Loan::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    // =========================================================================
    // Form (used by Create page)
    // =========================================================================

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Loan Request')->schema([
                Forms\Components\Select::make('member_id')
                    ->label('Member')
                    ->options(fn() => Member::active()->with('user')->get()
                        ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                    ->searchable()->required()->live(),

                Forms\Components\Placeholder::make('member_eligibility')
                    ->label('Member Eligibility')
                    ->content(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return '— Select a member to see their eligibility status.';
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return '—';
                        }
                        $svc = app(LoanEligibilityService::class);
                        $ctx = $svc->context($member);
                        if ($ctx['eligible']) {
                            return '✅ Eligible '
                                . '| Fund balance: SAR ' . number_format($ctx['fund_balance'], 2)
                                . ' | Max loan: SAR ' . number_format($ctx['max_loan_amount']);
                        }

                        return '⚠ Not eligible: ' . $ctx['reason'];
                    }),

                Forms\Components\TextInput::make('amount_requested')
                    ->label('Requested Amount (SAR)')
                    ->numeric()->prefix('SAR')->required()
                    ->minValue(1000)
                    ->maxValue(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return null; // uncapped until a member is selected
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return null;
                        }
                        return app(LoanEligibilityService::class)->maxLoanAmount($member);
                    })
                    ->helperText(function ($get) {
                        $memberId = $get('member_id');
                        if (!$memberId) {
                            return 'Select a member first to see the maximum loan amount.';
                        }
                        $member = Member::with('accounts')->find($memberId);
                        if (!$member) {
                            return null;
                        }
                        $max = app(LoanEligibilityService::class)->maxLoanAmount($member);
                        $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                        return 'Max: SAR ' . number_format($max) . ' (2× fund balance of SAR ' . number_format($fundBal) . ')';
                    }),
                Forms\Components\DatePicker::make('applied_at')
                    ->label('Request Date')
                    ->default(now()->toDateString())
                    ->required(),
                Forms\Components\Toggle::make('is_emergency')
                    ->label('Emergency Loan')
                    ->helperText('Assigns this loan to the Emergency fund tier upon approval.')
                    ->default(false),
                Forms\Components\Textarea::make('purpose')->required()->columnSpanFull(),
            ])->columns(2),

            Section::make('Guarantor & Witnesses')->schema([
                Forms\Components\Select::make('guarantor_member_id')
                    ->label('Guarantor Member')
                    ->options(fn() => Member::active()->with('user')->get()
                        ->mapWithKeys(fn($m) => [$m->id => "{$m->member_number} – {$m->user->name}"]))
                    ->searchable()->nullable()
                    ->helperText('Must be an active member with income.'),
                Forms\Components\TextInput::make('witness1_name')->label('Witness 1 — Name')->maxLength(255),
                Forms\Components\TextInput::make('witness1_phone')->label('Witness 1 — Phone')->tel()->maxLength(50),
                Forms\Components\TextInput::make('witness2_name')->label('Witness 2 — Name')->maxLength(255),
                Forms\Components\TextInput::make('witness2_phone')->label('Witness 2 — Phone')->tel()->maxLength(50),
            ])->columns(2),
        ]);
    }

    // =========================================================================
    // Early settlement (table row, view page, installments relation)
    // =========================================================================

    public static function earlySettleLoanModalDescription(Loan $r): string
    {
        $svc = app(LoanEarlySettlementService::class);
        $r->loadMissing('member');
        $required = $svc->requiredCash($r);
        $balance = (float) $r->member->cash_balance;
        $principal = $r->remaining_amount;

        return 'Principal remaining (installments): SAR ' . number_format($principal, 2)
            . '. Cash required now (including any late fees): SAR ' . number_format($required, 2)
            . '. Member cash balance: SAR ' . number_format($balance, 2)
            . '. All remaining installments will be debited from cash and marked paid.';
    }

    public static function earlySettleLoanAction(?Closure $afterSuccess = null): Action
    {
        return Action::make('early_settle')
            ->label('Early Settle')
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->visible(fn(Loan $r) => $r->status === 'active')
            ->requiresConfirmation()
            ->modalHeading('Confirm Early Settlement')
            ->modalDescription(fn(Loan $r) => static::earlySettleLoanModalDescription($r))
            ->action(function (Loan $record, Component $livewire) use ($afterSuccess) {
                try {
                    app(LoanEarlySettlementService::class)->earlySettle($record);
                } catch (\InvalidArgumentException | \RuntimeException $e) {
                    Notification::make()
                        ->title('Early settlement failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title('Loan Early Settled')->success()->send();
                static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                if ($afterSuccess) {
                    $afterSuccess($record, $livewire);
                }
            });
    }

    // =========================================================================
    // Approve / Reject (table row actions + view/edit record headers)
    // =========================================================================

    public static function approveLoanAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn(Loan $r) => $r->status === 'pending')
            ->fillForm(fn(Loan $r) => [
                'amount_approved' => $r->amount_requested,
                'is_emergency' => $r->is_emergency,
            ])
            ->schema(fn(Loan $record) => [
                Forms\Components\TextInput::make('amount_approved')
                    ->label('Approved Amount (SAR)')
                    ->numeric()->prefix('SAR')->required()
                    ->live()
                    ->helperText(
                        'Loan tier and fund tier are auto-assigned from the requested amount (SAR '
                        . number_format((float) $record->amount_requested)
                        . '). Adjust this figure only for the disbursed amount.'
                    ),

                Forms\Components\Toggle::make('is_emergency')
                    ->label('Emergency Loan')
                    ->live()
                    ->helperText('Emergency loans bypass the standard loan-tier queue and are assigned to the Emergency fund tier.')
                    ->default(false),

                Forms\Components\Placeholder::make('repayment_preview')
                    ->label('Loan Schedule & Tier Assignment')
                    ->content(function ($get) use ($record) {
                        $requestedAmount = (float) $record->amount_requested;
                        $previewApproved = (float) ($get('amount_approved') ?? $requestedAmount);
                        $isEmergency = (bool) ($get('is_emergency') ?? $record->is_emergency);
                        $fundBal = (float) ($record->member->fundAccount()?->balance ?? 0);
                        // Tier and min installment must match the same principal as computeInstallmentsCount().
                        $loanTier = LoanTier::forAmount($previewApproved);
                        $threshold = Setting::loanSettlementThreshold();

                        if (!$loanTier) {
                            return new HtmlString(
                                '<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">'
                                . '⚠ No loan tier covers SAR ' . e(number_format($previewApproved))
                                . '. Adjust Loan Tiers in Settings before approving.'
                                . '</div>'
                            );
                        }

                        $minInstall = (float) $loanTier->min_monthly_installment;
                        $memberPortion = min(max(0.0, $fundBal), $previewApproved);
                        $masterPortion = $previewApproved - $memberPortion;
                        $settleAmt = $previewApproved * $threshold;
                        $count = Loan::computeInstallmentsCount($previewApproved, $fundBal, $minInstall, $threshold);

                        $fundTier = $isEmergency
                            ? FundTier::emergency()
                            : FundTier::forLoanTier($loanTier->id);
                        $fundTierLabel = $fundTier
                            ? $fundTier->label . ' (SAR ' . number_format((float) $fundTier->available_amount, 2) . ' uncommitted)'
                            : '⚠ No matching fund tier';

                        $declaredPool = $fundTier ? (float) $fundTier->allocated_amount : 0.0;
                        $declaredRow = $fundTier
                            ? '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                            . '<td class="py-2.5 pl-3 pr-3 text-gray-500 dark:text-gray-400">' . e('Fund tier declared pool') . '</td>'
                            . '<td class="py-2.5 pr-3 text-right tabular-nums font-semibold text-gray-950 dark:text-white">SAR '
                            . e(number_format($declaredPool, 2))
                            . ' <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">('
                            . e((string) $fundTier->percentage) . '% of master fund)</span></td></tr>'
                            : '';

                        $row = fn(string $label, string $value, bool $highlight = false): string =>
                            '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                            . '<td class="py-2.5 pl-3 pr-3 text-gray-500 dark:text-gray-400">' . e($label) . '</td>'
                            . '<td class="py-2.5 pr-3 text-right tabular-nums ' . ($highlight ? 'font-semibold text-gray-950 dark:text-white' : 'text-gray-700 dark:text-gray-300') . '">' . e($value) . '</td>'
                            . '</tr>';

                        $masterFundBal = (float) (Account::masterFund()?->balance ?? 0);

                        $masterClass = $masterFundBal < $previewApproved ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-700 dark:text-gray-300';
                        $masterRow = '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                            . '<td class="py-2.5 pl-3 pr-3 text-gray-500 dark:text-gray-400">' . e('Master fund balance (cash ledger)') . '</td>'
                            . '<td class="py-2.5 pr-3 text-right tabular-nums ' . $masterClass . '">SAR ' . e(number_format($masterFundBal, 2)) . '</td>'
                            . '</tr>';

                        $loanTierValue = $loanTier->label
                            . ' (SAR ' . number_format((float) $loanTier->min_amount)
                            . ' – SAR ' . number_format((float) $loanTier->max_amount) . ')';
                        $rows = $row('Loan tier (from approved amount in form)', $loanTierValue)
                            . $row('Fund tier', $fundTierLabel)
                            . $declaredRow
                            . $masterRow
                            . $row('Member fund balance', 'SAR ' . number_format($fundBal, 2))
                            . $row('Member portion', 'SAR ' . number_format($memberPortion, 2))
                            . $row('Fund (master) portion', 'SAR ' . number_format($masterPortion, 2))
                            . $row('Settlement top-up (' . ($threshold * 100) . '%)', 'SAR ' . number_format($settleAmt, 2))
                            . $row('Monthly installment', 'SAR ' . number_format($minInstall, 2))
                            . $row('Repayment period', $count . ' months', true);

                        $table = '<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40">'
                            . '<table class="w-full min-w-[20rem] text-sm">'
                            . '<thead><tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">'
                            . '<th scope="col" class="py-2.5 pl-3 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Field</th>'
                            . '<th scope="col" class="py-2.5 pr-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Value</th>'
                            . '</tr></thead>'
                            . '<tbody>' . $rows . '</tbody>'
                            . '</table></div>';

                        $warnHtml = '';
                        if ($fundTier && $declaredPool + 0.01 < $previewApproved) {
                            $warnHtml = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">'
                                . '⚠ Approved amount (SAR ' . e(number_format($previewApproved, 2)) . ') is above this fund tier’s declared pool (SAR '
                                . e(number_format($declaredPool, 2)) . '). Disbursements will be capped to that pool; use a lower approved amount or adjust fund tiers.'
                                . '</div>';
                        } elseif ($masterFundBal + 0.01 < $previewApproved) {
                            $warnHtml = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">'
                                . 'ℹ Master fund cash (SAR ' . e(number_format($masterFundBal, 2)) . ') is below the approved amount. '
                                . 'Disbursement size is capped by the <strong>fund tier declared pool</strong>, not this balance; the ledger still must have enough cash when you post each disbursement.'
                                . '</div>';
                        }

                        $note = '<p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">'
                            . 'Tier assignment is based on the <span class="font-medium text-gray-700 dark:text-gray-300">requested amount</span>. '
                            . 'Repayment period is re-computed at disbursement using the then-current fund balance.'
                            . '</p>';

                        return new HtmlString('<div class="space-y-3">' . $warnHtml . $table . $note . '</div>');
                    })
                    ->columnSpanFull(),
            ])
            ->action(function (Loan $record, array $data, Component $livewire) {
                $amount = (float) $data['amount_approved'];
                $isEmergency = (bool) ($data['is_emergency'] ?? false);
                $threshold = Setting::loanSettlementThreshold();

                // Same principal for tier assignment and installment count (must match approve modal preview).
                $loanTier = LoanTier::forAmount($amount);

                if (!$loanTier) {
                    Notification::make()
                        ->title('Cannot Approve')
                        ->body('No loan tier covers SAR ' . number_format($amount) . '. Adjust Loan Tiers or the approved amount.')
                        ->danger()->send();

                    return;
                }

                $fundTier = $isEmergency
                    ? FundTier::emergency()
                    : FundTier::forLoanTier($loanTier->id);

                if (!$fundTier) {
                    Notification::make()
                        ->title('Cannot Approve')
                        ->body('No active fund tier found for this loan. Configure Fund Tiers in Settings.')
                        ->danger()->send();

                    return;
                }

                $fundBal = (float) ($record->member->fundAccount()?->balance ?? 0);
                $minInstall = (float) $loanTier->min_monthly_installment;
                $count = Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);

                $record->update([
                    'status' => 'approved',
                    'amount_approved' => $amount,
                    'is_emergency' => $isEmergency,
                    'installments_count' => $count,
                    'loan_tier_id' => $loanTier->id,
                    'fund_tier_id' => $fundTier->id,
                    'queue_position' => null,
                    'approved_at' => now(),
                    'approved_by_id' => auth()->id(),
                    'settlement_threshold' => $threshold,
                ]);

                LoanQueueOrderingService::resequenceFundTier($fundTier->id);
                $record->refresh();

                $tierInfo = $isEmergency
                    ? 'Emergency fund tier'
                    : "{$fundTier->label} (queue position #{$record->queue_position})";

                try {
                    $record->member->user->notify(new LoanApprovedNotification(
                        amount: $amount,
                        installments: $count,
                        dueDate: now()->addMonths($count)->format('d M Y')
                    ));
                } catch (\Throwable) {
                }

                Notification::make()
                    ->title('Loan Approved')
                    ->body("Assigned to {$tierInfo}. Repayment: {$count} months × SAR " . number_format($minInstall) . '/month.')
                    ->success()->send();

                static::dispatchLoanListHeaderWidgetsRefresh($livewire);

                if ($livewire instanceof EditRecord) {
                    $url = static::getUrl('view', ['record' => $record], shouldGuessMissingParameters: true);
                    $livewire->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }
            });
    }

    public static function rejectLoanAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn(Loan $r) => $r->status === 'pending')
            ->schema([
                Forms\Components\Textarea::make('rejection_reason')->label('Rejection Reason')->required(),
            ])
            ->action(function (Loan $record, array $data, Component $livewire) {
                $record->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);
                try {
                    $record->member->user->notify(new MembershipRejectedNotification($data['rejection_reason']));
                } catch (\Throwable) {
                }
                Notification::make()->title('Loan Rejected')->warning()->send();
                static::dispatchLoanListHeaderWidgetsRefresh($livewire);

                if ($livewire instanceof EditRecord) {
                    $url = static::getUrl('view', ['record' => $record], shouldGuessMissingParameters: true);
                    $livewire->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }
            });
    }

    public static function disburseLoanAction(): Action
    {
        return Action::make('disburse')
            ->label(fn(Loan $r) => $r->isFullyDisbursed() ? 'Disbursed' : 'Disburse')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(fn(Loan $r) => $r->status === 'approved')
            ->requiresConfirmation(false)
            ->modalWidth('lg')
            ->modalHeading(function (Loan $r) {
                $remaining = $r->remainingToDisburse();
                $approved = (float) $r->amount_approved;
                $disbursed = (float) $r->amount_disbursed;
                $portion = $r->disbursements()->count() + 1;
                return "Disburse Loan #{$r->id} — SAR " . number_format($remaining, 2)
                    . ' remaining (portion #' . $portion . ' of SAR ' . number_format($approved, 2) . ')';
            })
            ->schema(function (Loan $r) {
                $remaining = $r->remainingToDisburse();
                $masterBal = (float) (Account::masterFund()?->balance ?? 0);
                $fundTier = $r->fundTier;
                $declaredPool = $fundTier ? max(0.0, (float) $fundTier->allocated_amount) : $remaining;
                $policyMax = min($remaining, $declaredPool);
                $masterMax = min($remaining, $masterBal);
                $fundBal = (float) ($r->member->fundAccount()?->balance ?? 0);
                $minInstall = (float) ($r->loanTier?->min_monthly_installment ?? 1000);
                $threshold = (float) $r->settlement_threshold;
                $count = Loan::computeInstallmentsCount((float) $r->amount_approved, $fundBal, $minInstall, $threshold);

                $tierPct = $fundTier ? (string) $fundTier->percentage : '—';
                $infoHtml = '<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40">'
                    . '<table class="w-full text-sm">'
                    . '<tbody>'
                    . '<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Approved amount</td><td class="py-2 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300">SAR ' . number_format((float) $r->amount_approved, 2) . '</td></tr>'
                    . '<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Already disbursed</td><td class="py-2 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300">SAR ' . number_format((float) $r->amount_disbursed, 2) . '</td></tr>'
                    . '<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Remaining to disburse</td><td class="py-2 pr-3 text-right tabular-nums font-semibold text-gray-950 dark:text-white">SAR ' . number_format($remaining, 2) . '</td></tr>'
                    . '<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Fund tier declared pool</td><td class="py-2 pr-3 text-right tabular-nums font-semibold text-gray-950 dark:text-white">SAR ' . number_format($declaredPool, 2) . ' <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">(' . e($tierPct) . '% of master)</span></td></tr>'
                    . '<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Master fund balance (ledger)</td><td class="py-2 pr-3 text-right tabular-nums ' . ($masterBal < $remaining ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-700 dark:text-gray-300') . '">SAR ' . number_format($masterBal, 2) . '</td></tr>'
                    . '<tr><td class="py-2 pl-3 pr-3 text-gray-500 dark:text-gray-400">Est. repayment period</td><td class="py-2 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300">' . $count . ' months</td></tr>'
                    . '</tbody></table></div>';

                $warnHtml = '';
                if ($remaining <= 0.01) {
                    $warnHtml = '<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">⚠ '
                        . 'Nothing remains to be disbursed on this loan.'
                        . '</div>';
                } elseif ($masterMax <= 0.01) {
                    $warnHtml = '<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">⚠ '
                        . 'Master fund ledger balance (SAR ' . number_format($masterBal, 2) . ') is not enough to post a disbursement against remaining approved principal.'
                        . '</div>';
                } elseif ($fundTier && $declaredPool <= 0.01 && $masterMax > 0.01) {
                    $warnHtml = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">⚠ '
                        . 'This fund tier’s declared pool is SAR 0. Check <strong>Force</strong> below to disburse up to SAR '
                        . number_format($masterMax, 2) . ' (lesser of remaining approved and master ledger balance).'
                        . '</div>';
                } elseif ($declaredPool + 0.01 < $remaining && $policyMax > 0.01) {
                    $warnHtml = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">⚠ '
                        . 'Per policy, each disbursement is capped at the fund tier’s declared pool (SAR ' . number_format($declaredPool, 2)
                        . ') even though SAR ' . number_format($remaining, 2) . ' remains on the loan. Repayment starts only after full disbursement.'
                        . ' Check <strong>Force</strong> to allow up to SAR ' . number_format($masterMax, 2)
                        . ' for this posting (lesser of remaining approved and master ledger balance).'
                        . '</div>';
                }
                if ($policyMax > 0.01 && $masterBal + 0.01 < $policyMax) {
                    $warnHtml .= '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">ℹ '
                        . 'Master fund cash (SAR ' . number_format($masterBal, 2) . ') is below the tier declared pool cap; without <strong>Force</strong> you can still enter up to the pool, but posting will fail until the ledger has enough balance. With <strong>Force</strong>, the maximum follows master cash (SAR ' . number_format($masterMax, 2) . ').'
                        . '</div>';
                }

                return [
                    Forms\Components\Placeholder::make('disburse_info')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString('<div class="space-y-3">' . $infoHtml . $warnHtml . '</div>'))
                        ->columnSpanFull(),
                    Forms\Components\Checkbox::make('force')
                        ->label('Force')
                        ->helperText('Override the per-disbursement cap from the fund tier’s declared pool. The amount is still limited by remaining approved principal and master fund ledger balance.')
                        ->visible($fundTier !== null && $declaredPool + 0.01 < $remaining)
                        ->live()
                        ->afterStateUpdated(function ($state, $set) use ($remaining, $masterBal, $policyMax) {
                            if ($state) {
                                $set('amount', min($remaining, $masterBal));
                            } else {
                                $set('amount', $policyMax > 0.01 ? $policyMax : null);
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount to disburse (SAR)')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(fn (Get $get) => $get('force') ? $masterMax : ($policyMax > 0.01 ? $policyMax : 0.01))
                        ->default(fn() => $policyMax > 0.01 ? $policyMax : null)
                        ->suffix('SAR')
                        ->helperText(function (Get $get) use ($masterBal, $policyMax, $masterMax) {
                            if ($get('force')) {
                                return 'Max: SAR ' . number_format($masterMax, 2) . ' (lesser of remaining approved and master fund ledger balance).';
                            }

                            return 'Max: SAR ' . number_format($policyMax, 2) . ' (lesser of remaining approved and fund tier declared pool). Master ledger SAR ' . number_format($masterBal, 2) . ' is enforced on submit.';
                        })
                        ->disabled(fn (Get $get) => $remaining <= 0.01 || $masterMax <= 0.01 || (!$get('force') && $policyMax < 0.01))
                        ->required(fn (Get $get) => !($remaining <= 0.01 || $masterMax <= 0.01 || (!$get('force') && $policyMax < 0.01)))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes (optional)')
                        ->nullable()
                        ->rows(2)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (Loan $record, array $data, Component $livewire) {
                $record->loadMissing(['fundTier', 'member.accounts']);
                $amount = (float) ($data['amount'] ?? 0);
                $notes = $data['notes'] ?? null;
                $force = (bool) ($data['force'] ?? false);
                $remaining = $record->remainingToDisburse();
                $fundTier = $record->fundTier;
                $declaredCap = $fundTier ? max(0.0, (float) $fundTier->allocated_amount) : $remaining;
                $masterBal = (float) (Account::masterFund()?->balance ?? 0);
                if ($amount <= 0) {
                    Notification::make()->title('Enter a disbursement amount')->danger()->send();
                    return;
                }

                if ($amount > $remaining + 0.01) {
                    Notification::make()
                        ->title('Amount exceeds remaining to disburse')
                        ->body('Remaining: SAR ' . number_format($remaining, 2))
                        ->danger()->send();
                    return;
                }

                if (!$force && $amount > $declaredCap + 0.01) {
                    Notification::make()
                        ->title('Amount exceeds fund tier declared pool')
                        ->body('Declared pool: SAR ' . number_format($declaredCap, 2) . '. Check Force to override this cap, within master fund balance.')
                        ->danger()->send();
                    return;
                }

                if ($amount > $masterBal + 0.01) {
                    Notification::make()
                        ->title('Amount exceeds master fund balance')
                        ->body('Available on master ledger: SAR ' . number_format($masterBal, 2))
                        ->danger()->send();
                    return;
                }

                // Pre-posting balance: semantic member vs. master split and installment count only.
                // Ledger: full disbursement still debits master + mirrors the same amount on member fund.
                $memberFundBalanceBefore = (float) ($record->member->fundAccount()?->balance ?? 0);

                // Create the disbursement record (portions filled by AccountingService)
                $disbursement = LoanDisbursement::create([
                    'loan_id' => $record->id,
                    'amount' => $amount,
                    'member_portion' => 0,
                    'master_portion' => 0,
                    'disbursed_at' => now(),
                    'disbursed_by_id' => auth()->id(),
                    'notes' => $notes,
                ]);

                try {
                    app(AccountingService::class)->postPartialLoanDisbursement($record, $amount, $disbursement);
                } catch (\Throwable $e) {
                    $disbursement->delete();
                    Notification::make()->title('Disbursement failed')->body($e->getMessage())->danger()->send();
                    return;
                }

                $record->refresh();
                $totalDisbursed = (float) $record->amount_disbursed;
                $amountApproved = (float) $record->amount_approved;

                if ($record->isFullyDisbursed()) {
                    // Full disbursement — activate loan and build repayment schedule
                    $disbursedAt = now();
                    $minInstall = (float) ($record->loanTier?->min_monthly_installment ?? 1000);
                    $threshold = (float) $record->settlement_threshold;
                    $count = Loan::computeInstallmentsCount($amountApproved, $memberFundBalanceBefore, $minInstall, $threshold);

                    $exemption = Loan::computeExemptionAndFirstRepayment($disbursedAt);
                    $exemption = Loan::adjustFirstRepaymentIfContributionAlreadyMade($record->member, $exemption);

                    DB::transaction(function () use ($record, $disbursedAt, $exemption, $count, $minInstall, $amountApproved, $memberFundBalanceBefore) {
                        $memberPortion = min(max(0.0, $memberFundBalanceBefore), $amountApproved);
                        $masterPortion = $amountApproved - $memberPortion;

                        $record->update([
                            'status' => 'active',
                            'installments_count' => $count,
                            'disbursed_at' => $disbursedAt,
                            'due_date' => $disbursedAt->copy()->addMonths($count)->toDateString(),
                            'member_portion' => $memberPortion,
                            'master_portion' => $masterPortion,
                        ] + $exemption);

                        $startDate = Carbon::create(
                            $exemption['first_repayment_year'],
                            $exemption['first_repayment_month'],
                            5
                        );

                        for ($i = 1; $i <= $count; $i++) {
                            LoanInstallment::create([
                                'loan_id' => $record->id,
                                'installment_number' => $i,
                                'amount' => $minInstall,
                                'due_date' => $startDate->copy()->addMonths($i - 1)->toDateString(),
                                'status' => 'pending',
                            ]);
                        }
                    });

                    $record->refresh();

                    try {
                        $record->member->user->notify(new LoanDisbursedNotification($record));
                    } catch (\Throwable) {
                    }

                    Notification::make()
                        ->title('Loan Fully Disbursed')
                        ->body("{$count} installments of SAR " . number_format($minInstall) . '/month. First repayment: ' . ($exemption['first_repayment_month'] . '/' . $exemption['first_repayment_year']))
                        ->success()->send();
                } else {
                    // Partial disbursement — loan stays approved, notify member
                    try {
                        $record->member->user->notify(new LoanPartialDisbursementNotification(
                            disbursement: $disbursement,
                            totalDisbursed: $totalDisbursed,
                            amountApproved: $amountApproved,
                        ));
                    } catch (\Throwable) {
                    }

                    Notification::make()
                        ->title('Partial Disbursement Recorded')
                        ->body('SAR ' . number_format($amount, 2) . ' disbursed. Remaining: SAR ' . number_format($record->remainingToDisburse(), 2) . '. Repayment will start after full disbursement.')
                        ->info()->send();
                }

                LoanQueueOrderingService::resequenceFundTier($record->fund_tier_id);
                static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                $livewire->dispatch('fundflow-refresh-loan-installments');
            });
    }

    // =========================================================================
    // Table
    // =========================================================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('queue_position')->label('Q#')->sortable()->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emg.')
                    ->boolean()
                    ->trueIcon('heroicon-o-bolt')
                    ->falseIcon(null)
                    ->trueColor('danger')
                    ->tooltip(fn(Loan $r) => $r->is_emergency ? 'Emergency Loan' : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('loanTier.label')->label('Tier')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('member.member_number')->label('Member #')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('member.user.name')->label('Member')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('amount_requested')->label('Requested')->money('SAR')->toggleable(),
                Tables\Columns\TextColumn::make('amount_approved')->label('Approved')->money('SAR')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Months')
                    ->description(fn(Loan $r) => $r->loanTier
                        ? 'SAR ' . number_format($r->loanTier->min_monthly_installment) . '/mo'
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        'early_settled' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('late_repayment_count')->label('Late #')
                    ->badge()->color(fn($state) => $state > 0 ? 'warning' : 'success')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('applied_at')->dateTime('d M Y')->sortable()->toggleable(),
            ])
            ->columnManager()
            ->defaultSort('applied_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'early_settled' => 'Early Settled',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('loan_tier_id')->label('Tier')
                    ->options(LoanTier::pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('fund_tier_id')->label('Fund tier')
                    ->options(FundTier::query()->orderBy('label')->pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->options(fn() => Member::with('user')->orderBy('member_number')->get()
                        ->mapWithKeys(fn(Member $m) => [$m->id => "{$m->member_number} – {$m->user->name}"])),
                Tables\Filters\TernaryFilter::make('is_emergency')
                    ->label('Emergency'),
                Tables\Filters\TernaryFilter::make('disbursed')
                    ->label('Disbursed')
                    ->trueLabel('Disbursed')
                    ->falseLabel('Not disbursed')
                    ->queries(
                        true: fn($q) => $q->whereNotNull('disbursed_at'),
                        false: fn($q) => $q->whereNull('disbursed_at'),
                    ),
                Tables\Filters\Filter::make('applied_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('Applied from'),
                        Forms\Components\DatePicker::make('until')->label('Applied until'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q) => $q->whereDate('applied_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn($q) => $q->whereDate('applied_at', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('amount_approved')
                    ->schema([
                        Forms\Components\TextInput::make('min')->label('Min approved (SAR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max approved (SAR)')->numeric(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(filled($data['min'] ?? null), fn($q) => $q->where('amount_approved', '>=', $data['min']))
                            ->when(filled($data['max'] ?? null), fn($q) => $q->where('amount_approved', '<=', $data['max']));
                    }),
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('app.action.new_loan'))
                    ->icon('heroicon-o-plus-circle'),
                Action::make('importLoans')
                    ->label(__('app.action.import_loans'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn (): bool => static::canCreate())
                    ->modalHeading(__('app.loan.import.heading'))
                    ->modalDescription(new HtmlString(
                        '<div class="space-y-3 text-sm">' .
                            '<div class="rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs dark:border-blue-500/30 dark:bg-blue-500/10">' .
                                '<p class="font-semibold text-blue-900 dark:text-blue-200 mb-1">' . e(__('app.ui.before_import')) . '</p>' .
                                '<p class="text-blue-900/90 dark:text-blue-100/90 mb-1">' .
                                    e(__('app.loan.import.sample_hint', ['filename' => ''])) . ' ' .
                                    '<a href="' . route('downloads.loan-import-sample') . '" class="font-semibold text-blue-700 underline hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200">loans-import-sample-10.csv</a>' .
                                '</p>' .
                                '<p class="text-blue-900/90 dark:text-blue-100/90">' .
                                    e(__('app.loan.import.warning_opening_balances')) .
                                '</p>' .
                            '</div>' .
                            '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">' .
                                '<table class="w-full text-xs">' .
                                    '<tbody class="divide-y divide-gray-100 dark:divide-gray-800">' .
                                        '<tr>' .
                                            '<td class="w-44 bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.csv_format')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.ui.first_row_headers')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.member_identifier')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.member_columns_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.status_values')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.status_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.amount_columns')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.amount_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.disbursement_columns')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.disbursement_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.installment_columns')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.installment_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.tier_columns')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.tier_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.flags_and_notes')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.flags_help')) . '</td>' .
                                        '</tr>' .
                                        '<tr>' .
                                            '<td class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-900/30 dark:text-gray-200">' . e(__('app.ui.date_columns')) . '</td>' .
                                            '<td class="px-3 py-2 text-gray-600 dark:text-gray-300">' . e(__('app.loan.import.dates_help')) . '</td>' .
                                        '</tr>' .
                                    '</tbody>' .
                                '</table>' .
                            '</div>' .
                        '</div>'
                    ))
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
                            ->title(__('app.loan.import.finished'))
                            ->body($body)
                            ->color($result['failed'] > 0 || $result['errors'] !== [] ? 'warning' : 'success')
                            ->persistent()
                            ->send();
                    }),
                Action::make('export_csv')
                    ->label(__('app.action.export_loans'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->action(function () {
                        $filename = 'loans-' . now()->format('Y-m-d') . '.csv';

                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'loan_number', 'member_number', 'member_name',
                                'tier', 'amount_requested', 'amount_approved',
                                'member_portion', 'master_portion',
                                'status', 'applied_at', 'approved_at', 'disbursed_at',
                                'installments_total', 'installments_paid',
                                'min_monthly_installment',
                                'guarantor_member_number', 'guarantor_name',
                            ]);

                            Loan::with(['member.user', 'loanTier', 'guarantor.user'])
                                ->withCount(['installments as installments_total'])
                                ->withCount(['installments as installments_paid' => fn($q) => $q->where('status', 'paid')])
                                ->orderByDesc('id')
                                ->each(function (Loan $l) use ($handle) {
                                    fputcsv($handle, [
                                        $l->loan_number,
                                        $l->member?->member_number,
                                        $l->member?->user?->name,
                                        $l->loanTier?->label,
                                        $l->amount_requested,
                                        $l->amount_approved,
                                        $l->member_portion,
                                        $l->master_portion,
                                        $l->status,
                                        $l->applied_at?->toDateString(),
                                        $l->approved_at?->toDateString(),
                                        $l->disbursed_at?->toDateString(),
                                        $l->installments_total,
                                        $l->installments_paid,
                                        $l->min_monthly_installment,
                                        $l->guarantor?->member_number,
                                        $l->guarantor?->user?->name,
                                    ]);
                                });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    static::approveLoanAction(),
                    static::disburseLoanAction(),
                    static::rejectLoanAction(),

                    // ── CANCEL ──
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn(Loan $r) => in_array($r->status, ['pending', 'approved']))
                        ->schema([
                            Forms\Components\Textarea::make('cancellation_reason')->label('Cancellation Reason')->nullable(),
                        ])
                        ->action(function (Loan $record, array $data, Component $livewire) {
                            $fundTierId = $record->fund_tier_id;
                            $record->update(['status' => 'cancelled', 'cancellation_reason' => $data['cancellation_reason'] ?? null]);
                            try {
                                $record->member->user->notify(new LoanCancelledNotification($record, $data['cancellation_reason'] ?? ''));
                            } catch (\Throwable) {
                            }
                            if ($fundTierId !== null) {
                                LoanQueueOrderingService::resequenceFundTier((int) $fundTierId);
                            }
                            Notification::make()->title('Loan Cancelled')->send();
                            static::dispatchLoanListHeaderWidgetsRefresh($livewire);
                        }),

                    static::earlySettleLoanAction(),

                    DeleteAction::make()
                        ->modalDescription(
                            'Reverses all ledger postings for this loan (disbursement, repayments, and any cash or guarantor lines tied to its installments), then soft-deletes installments, the loan account, and the loan. Restoring a loan from the trash does not rebuild ledger postings — use only when you understand the impact.'
                        )
                        ->using(function (Loan $record) {
                            $fundTierId = $record->fund_tier_id;
                            app(AccountingService::class)->safeDeleteLoan($record);
                            if ($fundTierId !== null) {
                                LoanQueueOrderingService::resequenceFundTier((int) $fundTierId);
                            }

                            return true;
                        })
                        ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
                    RestoreAction::make()
                        ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
                    ForceDeleteAction::make()
                        ->after(fn(Component $livewire) => static::dispatchLoanListHeaderWidgetsRefresh($livewire)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(
                            'Each selected loan is deleted like a single delete: ledger lines are reversed, installments and the loan account removed. Failures are reported; other rows still process.'
                        )
                        ->using(function (DeleteBulkAction $action, $records) {
                            $accounting = app(AccountingService::class);
                            $tierIds = [];
                            foreach ($records as $record) {
                                if ($record->fund_tier_id !== null) {
                                    $tierIds[(int) $record->fund_tier_id] = true;
                                }
                                try {
                                    $accounting->safeDeleteLoan($record);
                                } catch (\Throwable $e) {
                                    $action->reportBulkProcessingFailure(message: $e->getMessage());
                                    report($e);
                                }
                            }
                            foreach (array_keys($tierIds) as $tid) {
                                LoanQueueOrderingService::resequenceFundTier($tid);
                            }
                        }),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Loan request')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('borrower')
                            ->label('Member')
                            ->state(fn(Loan $record): ?string => $record->member
                                ? "{$record->member->member_number} – {$record->member->user->name}"
                                : null)
                            ->url(function (Loan $record): ?string {
                                $member = $record->member;
                                if ($member === null || !MemberResource::canView($member)) {
                                    return null;
                                }

                                return MemberResource::getUrl('view', ['record' => $member]);
                            })
                            ->color('primary')
                            ->weight(FontWeight::Medium),
                        TextEntry::make('member_eligibility')
                            ->label('Member eligibility')
                            ->state(function (Loan $record): string {
                                $member = $record->member;
                                if ($member === null) {
                                    return '—';
                                }
                                $ctx = app(LoanEligibilityService::class)->context($member);
                                if ($ctx['eligible']) {
                                    return '✅ Eligible '
                                        . '| Fund balance: SAR ' . number_format($ctx['fund_balance'], 2)
                                        . ' | Max loan: SAR ' . number_format($ctx['max_loan_amount']);
                                }

                                return '⚠ Not eligible: ' . $ctx['reason'];
                            })
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'active' => 'success',
                                'completed' => 'gray',
                                'early_settled' => 'success',
                                'rejected' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('amount_requested')
                            ->label('Requested amount')
                            ->money('SAR'),
                        TextEntry::make('amount_approved')
                            ->label('Approved amount')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('installments_count')
                            ->label('Installments (months)'),
                        TextEntry::make('is_emergency')
                            ->label('Emergency loan')
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('purpose')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Fund & schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        TextEntry::make('loanTier.label')
                            ->label('Loan tier')
                            ->placeholder('—'),
                        TextEntry::make('fundTier.label')
                            ->label('Fund tier')
                            ->placeholder('—'),
                        TextEntry::make('queue_position')
                            ->label('Queue #')
                            ->placeholder('—'),
                        TextEntry::make('applied_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('approved_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('disbursed_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('due_date')
                            ->date('d M Y')
                            ->placeholder('—'),
                        TextEntry::make('settled_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('member_portion')
                            ->label('Member portion')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('master_portion')
                            ->label('Master / fund portion')
                            ->money('SAR')
                            ->placeholder('—'),
                        TextEntry::make('repaid_to_master')
                            ->label('Repaid (master track)')
                            ->money('SAR'),
                    ])->columns(2)
                    ->collapsible(),
                Section::make('Guarantor & witnesses')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        TextEntry::make('guarantor_display')
                            ->label('Guarantor')
                            ->state(fn(Loan $record): ?string => $record->guarantor
                                ? "{$record->guarantor->member_number} – {$record->guarantor->user->name}"
                                : null)
                            ->url(function (Loan $record): ?string {
                                $guarantor = $record->guarantor;
                                if ($guarantor === null || !MemberResource::canView($guarantor)) {
                                    return null;
                                }

                                return MemberResource::getUrl('view', ['record' => $guarantor]);
                            })
                            ->color('primary')
                            ->weight(FontWeight::Medium)
                            ->placeholder('—'),
                        TextEntry::make('witness1_name')
                            ->label('Witness 1 — name')
                            ->placeholder('—'),
                        TextEntry::make('witness1_phone')
                            ->label('Witness 1 — phone')
                            ->placeholder('—'),
                        TextEntry::make('witness2_name')
                            ->label('Witness 2 — name')
                            ->placeholder('—'),
                        TextEntry::make('witness2_phone')
                            ->label('Witness 2 — phone')
                            ->placeholder('—'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [InstallmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    /**
     * Refresh list-page header widgets ({@see LoanStatsWidget}) after table mutations.
     */
    public static function dispatchLoanListHeaderWidgetsRefresh(?Component $livewire): void
    {
        if ($livewire === null) {
            return;
        }

        $name = json_encode(
            app('livewire.factory')->resolveComponentName(LoanStatsWidget::class),
            JSON_THROW_ON_ERROR
        );

        $livewire->js('setTimeout(() => { window.Livewire.getByName(' . $name . ').forEach(w => w.$refresh()); }, 0)');
    }
}
