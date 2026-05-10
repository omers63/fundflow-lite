<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\FiscalYearClosure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class FiscalYearClosingService
{
    private ?int $archiveContextFiscalYearId = null;

    /** @var array<string, mixed>|null */
    private ?array $archiveContext = null;

    public function __construct(
        private readonly FiscalYearArchiveConnectionService $archiveConnections,
        private readonly FiscalYearPrimaryPurgeService $primaryPurge,
        private readonly LedgerAccountBalanceRecalculationService $ledgerBalances,
    ) {}

    /**
     * Fact tables copied in foreign-key order (within the fiscal slice). Parent dimension rows
     * (users, members, loans, accounts, import metadata, etc.) are resolved and copied first.
     *
     * @var array<int, array{table:string,date_column:string}>
     */
    protected array $factArchivePlan = [
        ['table' => 'contributions', 'date_column' => 'paid_at'],
        ['table' => 'loan_installments', 'date_column' => 'due_date'],
        ['table' => 'loan_disbursements', 'date_column' => 'disbursed_at'],
        ['table' => 'account_transactions', 'date_column' => 'transacted_at'],
        ['table' => 'monthly_statements', 'date_column' => 'generated_at'],
        ['table' => 'reconciliation_snapshots', 'date_column' => 'as_of'],
        ['table' => 'bank_transactions', 'date_column' => 'transaction_date'],
        ['table' => 'sms_transactions', 'date_column' => 'transaction_date'],
        ['table' => 'member_subscription_fees', 'date_column' => 'paid_at'],
    ];

    /**
     * Every table that must exist on the archive connection before close.
     *
     * @var list<string>
     */
    protected array $archiveRequiredTables = [
        'banks',
        'bank_import_templates',
        'sms_import_templates',
        'loan_tiers',
        'fund_tiers',
        'users',
        'members',
        'loans',
        'accounts',
        'bank_import_sessions',
        'sms_import_sessions',
        'contributions',
        'loan_installments',
        'loan_disbursements',
        'account_transactions',
        'monthly_statements',
        'reconciliation_snapshots',
        'bank_transactions',
        'sms_transactions',
        'member_subscription_fees',
    ];

    /**
     * Same row scope as close/archive for read-only exports (e.g. Excel close book), plus `loans`
     * for workbooks (loans are copied by reference in the archiver, not only `applied_at`).
     *
     * @return array<int, array{table:string,date_column:string}>
     */
    public function archiveTables(): array
    {
        return array_merge([
            ['table' => 'loans', 'date_column' => 'applied_at'],
        ], $this->factArchivePlan);
    }

    /**
     * Facts only (subset of {@see archiveTables()} without the loans workbook row).
     *
     * @return array<int, array{table:string,date_column:string}>
     */
    public function archivedFactTables(): array
    {
        return $this->factArchivePlan;
    }

    /**
     * Loans referenced by anything in the fiscal-year archive slice (wider than `applied_at` alone).
     */
    public function referencedLoansQuery(FiscalYear $fiscalYear): Builder
    {
        $ctx = $this->archiveContextFor($fiscalYear);
        $q = DB::table('loans')->orderBy('id');
        if ($ctx['loan_ids'] === []) {
            return $q->whereRaw('0 = 1');
        }

        return $q->whereIn('id', $ctx['loan_ids']);
    }

    /**
     * Same row scope as close/archive for read-only exports (Excel). `loans` uses the wider
     * referenced-loan set, not `applied_at` alone.
     */
    public function scopedSourceQuery(string $table, string $dateColumn, FiscalYear $fiscalYear): Builder
    {
        if ($table === 'loans') {
            return $this->referencedLoansQuery($fiscalYear);
        }

        $ctx = $this->archiveContextFor($fiscalYear);

        return $this->factSourceQuery($table, $dateColumn, $fiscalYear, $ctx);
    }

    /**
     * Clear cached referenced-id context (called after purge mutates primary rows).
     */
    public function forgetArchiveContextCache(): void
    {
        $this->archiveContext = null;
        $this->archiveContextFiscalYearId = null;
    }

    /**
     * Run after a successful close (or manually) to delete archived fact rows from primary and recount balances.
     */
    public function purgePrimaryForClosedFiscalYear(FiscalYear $fiscalYear): void
    {
        $this->primaryPurge->purgePrimaryFactsForArchivedYear($fiscalYear->fresh(), $this);
    }

    public function resolveArchiveConnectionForDryRunOrClose(FiscalYear $fiscalYear, ?string $archiveConnectionOverride): string
    {
        if ($archiveConnectionOverride !== null) {
            return $archiveConnectionOverride;
        }

        return $this->archiveConnections->ensureArchiveDatabaseReady($fiscalYear);
    }

    /**
     * When no per-file path exists, restores from the legacy `archive` SQLite connection from .env.
     */
    public function resolveArchiveConnectionForRestore(FiscalYear $fiscalYear, ?string $archiveConnectionOverride): string
    {
        if ($archiveConnectionOverride !== null) {
            return $archiveConnectionOverride;
        }

        $path = $fiscalYear->archive_database_path;
        if ($path !== null && trim((string) $path) !== '') {
            return $this->archiveConnections->registerStoredArchiveForRestore($fiscalYear);
        }

        return 'archive';
    }

    /**
     * @return array{tables: array<string, array{source_count:int, archive_count:int}>}
     */
    public function dryRun(FiscalYear $fiscalYear, ?string $archiveConnectionOverride = null): array
    {
        $archiveConnection = $this->resolveArchiveConnectionForDryRunOrClose($fiscalYear, $archiveConnectionOverride);
        $this->assertArchiveConnectionReady($archiveConnection);
        $startDate = $this->dateString($fiscalYear->start_date);
        $endDate = $this->dateString($fiscalYear->end_date);
        $ctx = $this->archiveContextFor($fiscalYear);

        $result = [];

        foreach ($this->factArchivePlan as $item) {
            $table = $item['table'];
            $dateColumn = $item['date_column'];

            $sourceCount = (int) $this->factSourceQuery($table, $dateColumn, $fiscalYear, $ctx)->count();

            $archiveQ = DB::connection($archiveConnection)->table($table);
            if ($table === 'loan_disbursements') {
                $archiveQ->where(function (Builder $q) use ($dateColumn, $startDate, $endDate, $ctx): void {
                    $q->whereBetween($dateColumn, [$startDate, $endDate]);
                    if ($ctx['extra_loan_disbursement_ids'] !== []) {
                        $q->orWhereIn('id', $ctx['extra_loan_disbursement_ids']);
                    }
                });
            } elseif ($table === 'bank_transactions') {
                $archiveQ->whereIn('id', $ctx['bank_transaction_ids']);
            } elseif ($table === 'sms_transactions') {
                $archiveQ->whereIn('id', $ctx['sms_transaction_ids']);
            } else {
                $archiveQ->whereBetween($dateColumn, [$startDate, $endDate]);
            }

            $archiveCount = (int) $archiveQ->count();

            $result[$table] = [
                'source_count' => $sourceCount,
                'archive_count' => $archiveCount,
            ];
        }

        return ['tables' => $result];
    }

    public function close(FiscalYear $fiscalYear, int $userId, ?string $archiveConnectionOverride = null, bool $purgePrimaryAfterSuccessfulClose = false): FiscalYearClosure
    {
        if ($fiscalYear->status !== 'open') {
            throw new RuntimeException("Fiscal year {$fiscalYear->code} is not open.");
        }

        if ($purgePrimaryAfterSuccessfulClose && $archiveConnectionOverride !== null) {
            throw new RuntimeException(
                'Primary purge is only supported with the default per–fiscal-year SQLite archive. Omit the legacy archive override.'
            );
        }

        if ($archiveConnectionOverride === null) {
            $this->archiveConnections->persistArchivePathIfMissing($fiscalYear);
            $fiscalYear->refresh();
        }

        $archiveConnection = $this->resolveArchiveConnectionForDryRunOrClose($fiscalYear, $archiveConnectionOverride);
        $this->assertArchiveConnectionReady($archiveConnection);

        $closure = FiscalYearClosure::create([
            'fiscal_year_id' => $fiscalYear->id,
            'action' => 'close',
            'status' => 'started',
            'archive_connection' => $archiveConnection,
            'archive_batch_id' => (string) str()->uuid(),
            'started_by_id' => $userId,
            'started_at' => now(),
        ]);

        $lock = Cache::lock("fiscal-year-close-{$fiscalYear->id}", 600);

        try {
            $lock->block(10);
            $fiscalYear->update(['status' => 'closing']);

            $rowCounts = [];
            $ctx = $this->archiveContextFor($fiscalYear);
            $startDate = $this->dateString($fiscalYear->start_date);
            $endDate = $this->dateString($fiscalYear->end_date);

            Schema::connection($archiveConnection)->withoutForeignKeyConstraints(function () use ($archiveConnection, $fiscalYear, $ctx, $startDate, $endDate, &$rowCounts): void {
                $this->archiveDimensions($archiveConnection, $ctx);

                foreach ($this->factArchivePlan as $item) {
                    $table = $item['table'];
                    $dateColumn = $item['date_column'];
                    $q = $this->factSourceQuery($table, $dateColumn, $fiscalYear, $ctx);
                    $this->chunkInsertQuery($archiveConnection, $table, $q);

                    $sourceCount = (int) $this->factSourceQuery($table, $dateColumn, $fiscalYear, $ctx)->count();
                    $archiveQ = DB::connection($archiveConnection)->table($table);
                    if ($table === 'loan_disbursements') {
                        $archiveQ->where(function (Builder $q) use ($dateColumn, $startDate, $endDate, $ctx): void {
                            $q->whereBetween($dateColumn, [$startDate, $endDate]);
                            if ($ctx['extra_loan_disbursement_ids'] !== []) {
                                $q->orWhereIn('id', $ctx['extra_loan_disbursement_ids']);
                            }
                        });
                    } elseif ($table === 'bank_transactions') {
                        $archiveQ->whereIn('id', $ctx['bank_transaction_ids']);
                    } elseif ($table === 'sms_transactions') {
                        $archiveQ->whereIn('id', $ctx['sms_transaction_ids']);
                    } else {
                        $archiveQ->whereBetween($dateColumn, [$startDate, $endDate]);
                    }
                    $archiveCount = (int) $archiveQ->count();

                    $rowCounts[$table] = [
                        'source_count' => $sourceCount,
                        'archive_count' => $archiveCount,
                    ];

                    if ($archiveCount < $sourceCount) {
                        throw new RuntimeException("Archive verification failed for table [{$table}].");
                    }
                }
            });

            $metadata = [
                'archive_connection' => $archiveConnection,
                'archive_batch_id' => $closure->archive_batch_id,
            ];
            if ($archiveConnectionOverride === null && $fiscalYear->archive_database_path) {
                $metadata['archive_database_path_relative'] = $fiscalYear->archive_database_path;
                $metadata['archive_database_path_absolute'] = $this->archiveConnections->resolveAbsoluteArchivePath($fiscalYear);
            }

            $fiscalYear->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_id' => $userId,
                'close_metadata' => $metadata,
            ]);

            $checks = ['row_count_check' => 'passed'];
            $closure->update([
                'status' => 'completed',
                'finished_by_id' => $userId,
                'finished_at' => now(),
                'row_counts' => $rowCounts,
                'integrity_checks' => $checks,
            ]);

            $fiscalYear->refresh();

            if ($purgePrimaryAfterSuccessfulClose) {
                $this->primaryPurge->purgePrimaryFactsForArchivedYear($fiscalYear, $this);
                $checks['primary_purge'] = 'completed';
                $closure->update([
                    'integrity_checks' => $checks,
                ]);
            }
        } catch (Throwable $e) {
            $fiscalYear->update(['status' => 'open']);
            $closure->update([
                'status' => 'failed',
                'finished_by_id' => $userId,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            optional($lock)->release();
        }

        return $closure->fresh();
    }

    public function restore(FiscalYear $fiscalYear, int $userId, ?string $archiveConnectionOverride = null): FiscalYearClosure
    {
        $archiveConnection = $this->resolveArchiveConnectionForRestore($fiscalYear, $archiveConnectionOverride);
        $this->assertArchiveConnectionReady($archiveConnection);

        $closure = FiscalYearClosure::create([
            'fiscal_year_id' => $fiscalYear->id,
            'action' => 'restore',
            'status' => 'started',
            'archive_connection' => $archiveConnection,
            'archive_batch_id' => (string) str()->uuid(),
            'started_by_id' => $userId,
            'started_at' => now(),
        ]);

        $lock = Cache::lock("fiscal-year-restore-{$fiscalYear->id}", 600);

        try {
            $lock->block(10);
            $fiscalYear->update(['status' => 'restoring']);

            $rowCounts = [];
            $restoreCtx = $this->resolveRestoreContextFromArchive($fiscalYear, $archiveConnection);

            foreach ($this->factArchivePlan as $item) {
                $table = $item['table'];
                $dateColumn = $item['date_column'];

                $rows = $this->archivedFactQuery($archiveConnection, $table, $dateColumn, $fiscalYear, $restoreCtx)
                    ->orderBy('id')
                    ->get();

                if ($rows->isEmpty()) {
                    $rowCounts[$table] = ['restored' => 0];

                    continue;
                }

                DB::table($table)->upsert(
                    $rows->map(fn ($row) => (array) $row)->all(),
                    ['id']
                );

                $rowCounts[$table] = ['restored' => $rows->count()];
            }

            $fiscalYear->update(['status' => 'open']);

            $closure->update([
                'status' => 'completed',
                'finished_by_id' => $userId,
                'finished_at' => now(),
                'row_counts' => $rowCounts,
                'integrity_checks' => ['restore' => 'completed'],
            ]);

            $this->ledgerBalances->recalculateAllAccountsOnPrimaryConnection();
        } catch (Throwable $e) {
            $fiscalYear->update(['status' => 'closed']);
            $closure->update([
                'status' => 'failed',
                'finished_by_id' => $userId,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            optional($lock)->release();
        }

        return $closure->fresh();
    }

    private function chunkInsertQuery(string $archiveConnection, string $table, Builder $query): void
    {
        $query->clone()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($archiveConnection, $table): void {
                DB::connection($archiveConnection)
                    ->table($table)
                    ->insertOrIgnore($rows->map(fn ($row) => (array) $row)->all());
            }, 'id');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function archiveDimensions(string $archiveConnection, array $ctx): void
    {
        foreach (array_chunk($ctx['bank_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'banks', DB::table('banks')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['bank_template_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'bank_import_templates', DB::table('bank_import_templates')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['sms_template_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'sms_import_templates', DB::table('sms_import_templates')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['loan_tier_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'loan_tiers', DB::table('loan_tiers')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['fund_tier_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'fund_tiers', DB::table('fund_tiers')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['user_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'users', DB::table('users')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['member_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'members', DB::table('members')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['loan_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'loans', DB::table('loans')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['account_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'accounts', DB::table('accounts')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['bank_import_session_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'bank_import_sessions', DB::table('bank_import_sessions')->whereIn('id', $chunk));
        }

        foreach (array_chunk($ctx['sms_import_session_ids'], 400) as $chunk) {
            $this->chunkInsertQuery($archiveConnection, 'sms_import_sessions', DB::table('sms_import_sessions')->whereIn('id', $chunk));
        }
    }

    public function factSourceQuery(string $table, string $dateColumn, FiscalYear $fiscalYear, array $ctx): Builder
    {
        $startDate = $this->dateString($fiscalYear->start_date);
        $endDate = $this->dateString($fiscalYear->end_date);

        if ($table === 'loan_disbursements') {
            return DB::table($table)->where(function (Builder $inner) use ($dateColumn, $startDate, $endDate, $ctx): void {
                $inner->whereBetween($dateColumn, [$startDate, $endDate]);
                if ($ctx['extra_loan_disbursement_ids'] !== []) {
                    $inner->orWhereIn('id', $ctx['extra_loan_disbursement_ids']);
                }
            });
        }

        if ($table === 'bank_transactions') {
            return DB::table($table)->whereIn('id', $ctx['bank_transaction_ids']);
        }

        if ($table === 'sms_transactions') {
            return DB::table($table)->whereIn('id', $ctx['sms_transaction_ids']);
        }

        return DB::table($table)->whereBetween($dateColumn, [$startDate, $endDate]);
    }

    /**
     * @param  array{bank_transaction_ids: array<int,int>, sms_transaction_ids: array<int,int>, extra_loan_disbursement_ids: array<int,int>}  $restoreCtx
     */
    private function archivedFactQuery(
        string $archiveConnection,
        string $table,
        string $dateColumn,
        FiscalYear $fiscalYear,
        array $restoreCtx,
    ): Builder {
        $startDate = $this->dateString($fiscalYear->start_date);
        $endDate = $this->dateString($fiscalYear->end_date);
        $b = DB::connection($archiveConnection)->table($table);

        return match ($table) {
            'loan_disbursements' => $b->where(function (Builder $q) use ($dateColumn, $startDate, $endDate, $restoreCtx): void {
                $q->whereBetween($dateColumn, [$startDate, $endDate]);
                if ($restoreCtx['extra_loan_disbursement_ids'] !== []) {
                    $q->orWhereIn('id', $restoreCtx['extra_loan_disbursement_ids']);
                }
            }),
            'bank_transactions' => $b->whereIn('id', $restoreCtx['bank_transaction_ids']),
            'sms_transactions' => $b->whereIn('id', $restoreCtx['sms_transaction_ids']),
            default => $b->whereBetween($dateColumn, [$startDate, $endDate]),
        };
    }

    /**
     * Recompute extended bank/sms/disbursement id sets using only the archive DB (supports rows
     * copied solely as duplicate/disbursement parents outside the FY date window).
     *
     * @return array{bank_transaction_ids: array<int,int>, sms_transaction_ids: array<int,int>, extra_loan_disbursement_ids: array<int,int>}
     */
    private function resolveRestoreContextFromArchive(FiscalYear $fiscalYear, string $archiveConnection): array
    {
        $startDate = $this->dateString($fiscalYear->start_date);
        $endDate = $this->dateString($fiscalYear->end_date);

        $seedBank = DB::connection($archiveConnection)->table('bank_transactions')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->pluck('id')
            ->all();

        $bankIds = $this->expandDuplicateAncestorIds($archiveConnection, 'bank_transactions', $seedBank);

        $seedSms = DB::connection($archiveConnection)->table('sms_transactions')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->pluck('id')
            ->all();

        $smsIds = $this->expandDuplicateAncestorIds($archiveConnection, 'sms_transactions', $seedSms);

        $extraDiss = $bankIds === [] ? []
            : DB::connection($archiveConnection)->table('bank_transactions')
                ->whereIn('id', $bankIds)
                ->whereNotNull('loan_disbursement_id')
                ->pluck('loan_disbursement_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        return [
            'bank_transaction_ids' => $bankIds,
            'sms_transaction_ids' => $smsIds,
            'extra_loan_disbursement_ids' => $extraDiss,
        ];
    }

    /**
     * Cached per fiscal year for a single service instance (Excel summary hits this many times).
     *
     * @return array{
     *   loan_ids: array<int,int>,
     *   member_ids: array<int,int>,
     *   account_ids: array<int,int>,
     *   user_ids: array<int,int>,
     *   loan_tier_ids: array<int,int>,
     *   fund_tier_ids: array<int,int>,
     *   bank_ids: array<int,int>,
     *   bank_template_ids: array<int,int>,
     *   sms_template_ids: array<int,int>,
     *   bank_import_session_ids: array<int,int>,
     *   sms_import_session_ids: array<int,int>,
     *   bank_transaction_ids: array<int,int>,
     *   sms_transaction_ids: array<int,int>,
     *   extra_loan_disbursement_ids: array<int,int>,
     * }
     */
    public function archiveContextFor(FiscalYear $fiscalYear): array
    {
        if ($this->archiveContext !== null && $this->archiveContextFiscalYearId === $fiscalYear->id) {
            return $this->archiveContext;
        }

        $this->archiveContext = $this->buildArchiveContext($fiscalYear);
        $this->archiveContextFiscalYearId = $fiscalYear->id;

        return $this->archiveContext;
    }

    /**
     * @return array{
     *   loan_ids: array<int,int>,
     *   member_ids: array<int,int>,
     *   account_ids: array<int,int>,
     *   user_ids: array<int,int>,
     *   loan_tier_ids: array<int,int>,
     *   fund_tier_ids: array<int,int>,
     *   bank_ids: array<int,int>,
     *   bank_template_ids: array<int,int>,
     *   sms_template_ids: array<int,int>,
     *   bank_import_session_ids: array<int,int>,
     *   sms_import_session_ids: array<int,int>,
     *   bank_transaction_ids: array<int,int>,
     *   sms_transaction_ids: array<int,int>,
     *   extra_loan_disbursement_ids: array<int,int>,
     * }
     */
    private function buildArchiveContext(FiscalYear $fiscalYear): array
    {
        $startDate = $this->dateString($fiscalYear->start_date);
        $endDate = $this->dateString($fiscalYear->end_date);

        // --- Loans & members referenced without bank/sms ---
        $loanIds = Collection::wrap([
            ...DB::table('loans')->whereBetween('applied_at', [$startDate, $endDate])->pluck('id')->all(),
            ...DB::table('loan_installments')->whereBetween('due_date', [$startDate, $endDate])->pluck('loan_id')->all(),
            ...DB::table('loan_disbursements')->whereBetween('disbursed_at', [$startDate, $endDate])->pluck('loan_id')->all(),
        ]);

        $memberIds = Collection::wrap([
            ...DB::table('contributions')->whereBetween('paid_at', [$startDate, $endDate])->pluck('member_id')->all(),
            ...DB::table('monthly_statements')->whereBetween('generated_at', [$startDate, $endDate])->pluck('member_id')->all(),
            ...DB::table('member_subscription_fees')->whereBetween('paid_at', [$startDate, $endDate])->pluck('member_id')->all(),
            ...DB::table('account_transactions')->whereBetween('transacted_at', [$startDate, $endDate])
                ->whereNotNull('member_id')
                ->pluck('member_id')
                ->all(),
        ]);

        [$loanIds, $memberIds, $accountIds] = $this->fixpointLoanMemberAccounts($startDate, $endDate, $loanIds, $memberIds);

        // --- Bank / SMS overlays (sessions, disbursement tails, duplicate parents) ---
        $seedBank = DB::table('bank_transactions')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->pluck('id')
            ->all();
        $bankTransactionIds = $this->expandDuplicateAncestorIds(null, 'bank_transactions', $seedBank);

        $seedSms = DB::table('sms_transactions')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->pluck('id')
            ->all();
        $smsTransactionIds = $this->expandDuplicateAncestorIds(null, 'sms_transactions', $seedSms);

        if ($bankTransactionIds !== []) {
            $loanIds = $loanIds->merge(DB::table('bank_transactions')->whereIn('id', $bankTransactionIds)->whereNotNull('loan_id')->pluck('loan_id'));
            $memberIds = $memberIds->merge(DB::table('bank_transactions')->whereIn('id', $bankTransactionIds)->whereNotNull('member_id')->pluck('member_id'));
        }

        if ($smsTransactionIds !== []) {
            $memberIds = $memberIds->merge(DB::table('sms_transactions')->whereIn('id', $smsTransactionIds)->whereNotNull('member_id')->pluck('member_id'));
        }

        $extraLoanDisbursementIds = $bankTransactionIds === []
            ? []
            : DB::table('bank_transactions')->whereIn('id', $bankTransactionIds)->whereNotNull('loan_disbursement_id')->pluck('loan_disbursement_id')->unique()->values()->all();

        if ($extraLoanDisbursementIds !== []) {
            $loanIds = $loanIds->merge(DB::table('loan_disbursements')->whereIn('id', $extraLoanDisbursementIds)->pluck('loan_id'));
        }

        $loanIds = $loanIds->filter()->unique()->values();
        $memberIds = $memberIds->filter()->unique()->values();

        [$loanIds, $memberIds, $accountIds] = $this->fixpointLoanMemberAccounts($startDate, $endDate, $loanIds, $memberIds);

        $loanIdsArr = $loanIds->map(fn ($id) => (int) $id)->unique()->values()->all();
        $memberIdsArr = $memberIds->map(fn ($id) => (int) $id)->unique()->values()->all();
        $accountIdsArr = $accountIds->map(fn ($id) => (int) $id)->unique()->values()->all();
        $extraLoanDisbursementIds = array_values(array_unique(array_map(intval(...), $extraLoanDisbursementIds)));

        $userIds = collect();
        if ($loanIdsArr !== []) {
            foreach (array_chunk($loanIdsArr, 400) as $chunk) {
                $userIds = $userIds->merge(DB::table('loans')->whereIn('id', $chunk)->whereNotNull('approved_by_id')->pluck('approved_by_id'));
            }
        }

        $dissForUsers = DB::table('loan_disbursements')->where(function (Builder $q) use ($startDate, $endDate, $extraLoanDisbursementIds): void {
            $q->whereBetween('disbursed_at', [$startDate, $endDate]);
            if ($extraLoanDisbursementIds !== []) {
                $q->orWhereIn('id', $extraLoanDisbursementIds);
            }
        });
        $userIds = $userIds->merge($dissForUsers->clone()->whereNotNull('disbursed_by_id')->pluck('disbursed_by_id'));

        $userIds = $userIds->merge(DB::table('account_transactions')->whereBetween('transacted_at', [$startDate, $endDate])->pluck('posted_by'));
        $userIds = $userIds->merge(DB::table('member_subscription_fees')->whereBetween('paid_at', [$startDate, $endDate])->pluck('posted_by'));
        $userIds = $userIds->merge(
            DB::table('reconciliation_snapshots')->whereBetween('as_of', [$startDate, $endDate])->whereNotNull('created_by_id')->pluck('created_by_id')
        );

        if ($bankTransactionIds !== []) {
            foreach (array_chunk($bankTransactionIds, 400) as $chunk) {
                $userIds = $userIds->merge(DB::table('bank_transactions')->whereIn('id', $chunk)->whereNotNull('posted_by')->pluck('posted_by'));
            }
        }
        if ($smsTransactionIds !== []) {
            foreach (array_chunk($smsTransactionIds, 400) as $chunk) {
                $userIds = $userIds->merge(DB::table('sms_transactions')->whereIn('id', $chunk)->whereNotNull('posted_by')->pluck('posted_by'));
            }
        }

        $bankImportSessionIds = collect();
        if ($bankTransactionIds !== []) {
            foreach (array_chunk($bankTransactionIds, 400) as $chunk) {
                $bankImportSessionIds = $bankImportSessionIds->merge(DB::table('bank_transactions')->whereIn('id', $chunk)->pluck('import_session_id'));
            }
            $bankImportSessionIds = $bankImportSessionIds->unique()->values();
        }

        $smsImportSessionIds = collect();
        if ($smsTransactionIds !== []) {
            foreach (array_chunk($smsTransactionIds, 400) as $chunk) {
                $smsImportSessionIds = $smsImportSessionIds->merge(DB::table('sms_transactions')->whereIn('id', $chunk)->pluck('import_session_id'));
            }
            $smsImportSessionIds = $smsImportSessionIds->unique()->values();
        }

        $bankIds = collect();
        $bankTemplateIds = collect();
        $smsTemplateIds = collect();

        if ($bankImportSessionIds->isNotEmpty()) {
            foreach (array_chunk($bankImportSessionIds->all(), 400) as $chunk) {
                $sessions = DB::table('bank_import_sessions')->whereIn('id', $chunk)->get(['bank_id', 'template_id', 'imported_by']);
                foreach ($sessions as $s) {
                    $bankIds->push((int) $s->bank_id);
                    $bankTemplateIds->push((int) $s->template_id);
                    $userIds->push((int) $s->imported_by);
                }
            }
        }

        if ($smsImportSessionIds->isNotEmpty()) {
            foreach (array_chunk($smsImportSessionIds->all(), 400) as $chunk) {
                $sessions = DB::table('sms_import_sessions')->whereIn('id', $chunk)->get(['bank_id', 'template_id', 'imported_by']);
                foreach ($sessions as $s) {
                    if ($s->bank_id !== null) {
                        $bankIds->push((int) $s->bank_id);
                    }
                    $smsTemplateIds->push((int) $s->template_id);
                    $userIds->push((int) $s->imported_by);
                }
            }
        }

        $bankTemplateIdsUnique = $bankTemplateIds->unique()->values()->all();
        if ($bankTemplateIdsUnique !== []) {
            foreach (array_chunk($bankTemplateIdsUnique, 400) as $chunk) {
                $bankIds = $bankIds->merge(DB::table('bank_import_templates')->whereIn('id', $chunk)->pluck('bank_id'));
            }
        }

        $smsTemplateIdsUnique = $smsTemplateIds->unique()->values()->all();
        if ($smsTemplateIdsUnique !== []) {
            foreach (array_chunk($smsTemplateIdsUnique, 400) as $chunk) {
                $bankIds = $bankIds->merge(DB::table('sms_import_templates')->whereIn('id', $chunk)->whereNotNull('bank_id')->pluck('bank_id'));
            }
        }

        if ($bankTransactionIds !== []) {
            foreach (array_chunk($bankTransactionIds, 400) as $chunk) {
                $bankIds = $bankIds->merge(DB::table('bank_transactions')->whereIn('id', $chunk)->pluck('bank_id'));
            }
        }
        if ($smsTransactionIds !== []) {
            foreach (array_chunk($smsTransactionIds, 400) as $chunk) {
                $bankIds = $bankIds->merge(DB::table('sms_transactions')->whereIn('id', $chunk)->whereNotNull('bank_id')->pluck('bank_id'));
            }
        }

        if ($memberIdsArr !== []) {
            foreach (array_chunk($memberIdsArr, 400) as $chunk) {
                $userIds = $userIds->merge(DB::table('members')->whereIn('id', $chunk)->pluck('user_id'));
            }
        }

        $loanTierIds = collect();
        $fundTierIds = collect();
        if ($loanIdsArr !== []) {
            foreach (array_chunk($loanIdsArr, 400) as $chunk) {
                $loanTierIds = $loanTierIds->merge(DB::table('loans')->whereIn('id', $chunk)->whereNotNull('loan_tier_id')->pluck('loan_tier_id'));
                $fundTierIds = $fundTierIds->merge(DB::table('loans')->whereIn('id', $chunk)->whereNotNull('fund_tier_id')->pluck('fund_tier_id'));
            }
        }

        return [
            'loan_ids' => $loanIdsArr,
            'member_ids' => $memberIdsArr,
            'account_ids' => $accountIdsArr,
            'user_ids' => $userIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'loan_tier_ids' => $loanTierIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'fund_tier_ids' => $fundTierIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'bank_ids' => $bankIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'bank_template_ids' => $bankTemplateIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'sms_template_ids' => $smsTemplateIds->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'bank_import_session_ids' => $bankImportSessionIds->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'sms_import_session_ids' => $smsImportSessionIds->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'bank_transaction_ids' => array_values(array_unique(array_map(intval(...), $bankTransactionIds))),
            'sms_transaction_ids' => array_values(array_unique(array_map(intval(...), $smsTransactionIds))),
            'extra_loan_disbursement_ids' => $extraLoanDisbursementIds,
        ];
    }

    /**
     * Loans/members/accounts stabilize when accounts introduce loan_ids and member graph includes parents.
     *
     * @return array{0: Collection<int, int>, 1: Collection<int, int>, 2: Collection<int, int>}
     */
    private function fixpointLoanMemberAccounts(string $startDate, string $endDate, Collection $loanIds, Collection $memberIds): array
    {
        $loanIds = $loanIds->filter()->unique()->values();
        $memberIds = $memberIds->filter()->unique()->values();

        $prevSig = null;
        for ($i = 0; $i < 25; $i++) {
            if ($loanIds->isNotEmpty()) {
                foreach (array_chunk($loanIds->all(), 400) as $chunk) {
                    $rows = DB::table('loans')->whereIn('id', $chunk)->get(['member_id', 'guarantor_member_id']);
                    foreach ($rows as $r) {
                        if ($r->member_id) {
                            $memberIds->push((int) $r->member_id);
                        }
                        if ($r->guarantor_member_id) {
                            $memberIds->push((int) $r->guarantor_member_id);
                        }
                    }
                }
            }

            $memberIds = Collection::wrap($this->expandMemberAncestorIds($memberIds->filter()->unique()->values()->all()));

            $accountIds = Collection::wrap(
                DB::table('account_transactions')->whereBetween('transacted_at', [$startDate, $endDate])->pluck('account_id')->all()
            );

            if ($memberIds->isNotEmpty()) {
                foreach (array_chunk($memberIds->unique()->values()->all(), 400) as $chunk) {
                    $accountIds = $accountIds->merge(DB::table('accounts')->whereIn('member_id', $chunk)->pluck('id')->all());
                }
            }
            if ($loanIds->isNotEmpty()) {
                foreach (array_chunk($loanIds->unique()->values()->all(), 400) as $chunk) {
                    $accountIds = $accountIds->merge(DB::table('accounts')->whereIn('loan_id', $chunk)->pluck('id')->all());
                }
            }
            $accountIds = $accountIds->filter()->unique()->values();

            $extraLoansFromAccounts = collect();
            if ($accountIds->isNotEmpty()) {
                foreach (array_chunk($accountIds->all(), 400) as $chunk) {
                    $extraLoansFromAccounts = $extraLoansFromAccounts->merge(
                        DB::table('accounts')->whereIn('id', $chunk)->whereNotNull('loan_id')->pluck('loan_id')->all()
                    );
                }
            }
            $loanIds = $loanIds->merge($extraLoansFromAccounts)->filter()->unique()->values();

            $sig = $loanIds->sort()->values()->implode(',')
                .'|'.$memberIds->sort()->values()->implode(',')
                .'|'.$accountIds->sort()->values()->implode(',');
            if ($sig === $prevSig) {
                return [$loanIds, $memberIds, $accountIds];
            }
            $prevSig = $sig;
        }

        return [$loanIds, $memberIds, $accountIds];
    }

    /**
     * @param  array<int, int|string|null>  $memberIds
     * @return array<int, int>
     */
    private function expandMemberAncestorIds(array $memberIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $memberIds))));
        if ($ids === []) {
            return [];
        }

        $seen = array_fill_keys($ids, true);
        $frontier = $ids;
        while ($frontier !== []) {
            $parents = DB::table('members')
                ->whereIn('id', $frontier)
                ->whereNotNull('parent_id')
                ->pluck('parent_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->all();
            $next = [];
            foreach ($parents as $pid) {
                if ($pid !== 0 && ! isset($seen[$pid])) {
                    $seen[$pid] = true;
                    $next[] = $pid;
                }
            }
            $frontier = $next;
        }

        return array_map(intval(...), array_keys($seen));
    }

    /**
     * Walk `duplicate_of_id` chains so canonical rows outside the fiscal window are still archived.
     *
     * @param  array<int, int|string|null>  $seedIds
     * @return array<int, int>
     */
    private function expandDuplicateAncestorIds(?string $connection, string $table, array $seedIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $seedIds))));
        if ($ids === []) {
            return [];
        }

        $seen = array_fill_keys($ids, true);
        $frontier = $ids;

        while ($frontier !== []) {
            $sub = $connection === null ? DB::table($table) : DB::connection($connection)->table($table);
            $parents = $sub
                ->whereIn('id', $frontier)
                ->whereNotNull('duplicate_of_id')
                ->pluck('duplicate_of_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->all();
            $next = [];
            foreach ($parents as $pid) {
                if ($pid !== 0 && ! isset($seen[$pid])) {
                    $seen[$pid] = true;
                    $next[] = $pid;
                }
            }
            $frontier = $next;
        }

        return array_map(intval(...), array_keys($seen));
    }

    private function dateString(mixed $value): string
    {
        return (string) date('Y-m-d', strtotime((string) $value));
    }

    private function assertArchiveConnectionReady(string $archiveConnection): void
    {
        DB::connection($archiveConnection)->getPdo();

        foreach ($this->archiveRequiredTables as $table) {
            if (! Schema::connection($archiveConnection)->hasTable($table)) {
                throw new RuntimeException(
                    "Archive database missing table [{$table}]. Run migrations on archive DB before closing."
                );
            }
        }
    }
}
