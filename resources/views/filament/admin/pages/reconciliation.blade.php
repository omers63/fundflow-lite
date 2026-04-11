<x-filament-panels::page>
    <div class="lg:grid lg:grid-cols-[minmax(0,14rem)_1fr] lg:gap-8">
        {{-- Sidebar --}}
        <aside class="mb-6 space-y-1 lg:mb-0">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Workspace</p>
            <button type="button" wire:click="$set('sideTab', 'overview')"
                @class([
                    'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $sideTab === 'overview',
                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $sideTab !== 'overview',
                ])>
                <x-heroicon-o-chart-pie class="h-5 w-5 shrink-0" />
                Overview
            </button>
            <button type="button" wire:click="$set('sideTab', 'snapshots')"
                @class([
                    'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $sideTab === 'snapshots',
                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $sideTab !== 'snapshots',
                ])>
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 shrink-0" />
                Snapshots
            </button>
            <button type="button" wire:click="$set('sideTab', 'methodology')"
                @class([
                    'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $sideTab === 'methodology',
                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $sideTab !== 'methodology',
                ])>
                <x-heroicon-o-document-text class="h-5 w-5 shrink-0" />
                Methodology
            </button>
        </aside>

        <div class="min-w-0 space-y-6">
            @if ($sideTab === 'overview')
                <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-6 text-white shadow-md ring-1 ring-white/10 dark:from-slate-900 dark:to-black">
                    <p class="text-xs uppercase tracking-[0.16em] text-white/70">Finance control</p>
                    <h2 class="mt-1 text-2xl font-semibold">Reconciliation control center</h2>
                    <p class="mt-2 max-w-3xl text-sm text-white/85">
                        Run checks on demand or rely on scheduled daily and monthly snapshots. Critical failures mean stored balances disagree with the ledger — investigate before period close.
                    </p>
                </section>

                @php($latest = $this->getLatestSnapshots()->first())
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Latest snapshot</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $latest ? '#' . $latest->id : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $latest?->as_of?->diffForHumans() ?? 'Run from header actions' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Latest verdict</p>
                        <p class="mt-1 text-lg font-semibold {{ $latest?->is_passing ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $latest ? ($latest->is_passing ? 'Pass' : 'Fail') : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($latest)
                                Critical {{ $latest->critical_issues }} · Warnings {{ $latest->warnings }}
                            @else
                                No data yet
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Unposted bank (now)</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ $latest ? number_format($latest->summary['pipeline']['bank_unposted_count'] ?? 0) : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Rows awaiting cash post</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Ledger mismatches</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ $latest ? number_format($latest->report['checks']['ledger_balances']['mismatch_count'] ?? 0) : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Accounts out of balance</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">How to use</h3>
                    <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li>Use <strong>Run now (real-time)</strong> before sensitive operations or after bulk imports.</li>
                        <li><strong>Daily</strong> and <strong>monthly</strong> snapshots tag the reporting window for audit; full ledger checks always use the current database state.</li>
                        <li>Open <strong>Snapshots</strong> to inspect history; download <strong>JSON</strong> (full machine-readable) or <strong>PDF</strong> (human-readable summary, truncated payload).</li>
                        <li>Optional <strong>statement balance</strong> on each run compares master cash (book) to your declared closing balance; scheduled runs read <code class="text-xs">reconciliation.bank_statement_balance</code> and <code class="text-xs">reconciliation.bank_statement_date</code> from settings.</li>
                    </ul>
                </div>
            @elseif ($sideTab === 'snapshots')
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Stored snapshots</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Newest first. Select a row to preview summary and download the complete machine-readable report.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Mode</th>
                                    <th class="px-4 py-3">As of</th>
                                    <th class="px-4 py-3">Verdict</th>
                                    <th class="px-4 py-3">Critical</th>
                                    <th class="px-4 py-3">Warnings</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @forelse ($this->getLatestSnapshots() as $snap)
                                    <tr @class(['bg-primary-50/50 dark:bg-primary-500/10' => (int) $selectedSnapshotId === (int) $snap->id])>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $snap->id }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $snap->mode }}</td>
                                        <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-400">{{ $snap->as_of->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $snap->is_passing,
                                                'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' => !$snap->is_passing,
                                            ])>{{ $snap->is_passing ? 'Pass' : 'Fail' }}</span>
                                        </td>
                                        <td class="px-4 py-3 tabular-nums text-gray-700 dark:text-gray-300">{{ $snap->critical_issues }}</td>
                                        <td class="px-4 py-3 tabular-nums text-gray-700 dark:text-gray-300">{{ $snap->warnings }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button type="button" wire:click="selectSnapshot({{ (int) $snap->id }})"
                                                class="text-primary-600 text-xs font-semibold hover:underline dark:text-primary-400">View</button>
                                            @if ($this->canExportDownloads())
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button type="button" wire:click="downloadReport({{ (int) $snap->id }})"
                                                    class="text-primary-600 text-xs font-semibold hover:underline dark:text-primary-400">JSON</button>
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button type="button" wire:click="downloadPdf({{ (int) $snap->id }})"
                                                    class="text-primary-600 text-xs font-semibold hover:underline dark:text-primary-400">PDF</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No snapshots yet. Run reconciliation from the header.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php($sel = $this->getSelectedSnapshot())
                @if ($sel)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Snapshot #{{ $sel->id }} — summary</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mode {{ $sel->mode }} · as of {{ $sel->as_of->toIso8601String() }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($this->canExportDownloads())
                                    <button type="button" wire:click="downloadReport({{ (int) $sel->id }})"
                                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:hover:bg-white/5">
                                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                        JSON
                                    </button>
                                    <button type="button" wire:click="downloadPdf({{ (int) $sel->id }})"
                                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:hover:bg-white/5">
                                        <x-heroicon-o-document-text class="h-4 w-4" />
                                        PDF
                                    </button>
                                @endif
                            </div>
                        </div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (($sel->summary['headline_checks'] ?? []) as $key => $severity)
                                <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/10">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd class="mt-0.5 text-sm font-semibold capitalize text-gray-900 dark:text-white">{{ $severity }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if (!empty($sel->report['checks']['ledger_balances']['mismatches']))
                            <div class="mt-4 rounded-lg border border-red-200 bg-red-50/80 p-3 text-xs text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                                <p class="font-semibold">Ledger mismatches (first rows)</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach (array_slice($sel->report['checks']['ledger_balances']['mismatches'], 0, 8) as $row)
                                        <li>{{ $row['name'] ?? 'Account #' . ($row['account_id'] ?? '') }} — Δ {{ number_format($row['delta'] ?? 0, 2) }} SAR</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <div class="prose prose-sm max-w-none dark:prose-invert">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Reconciliation approach</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        The engine treats <strong>ledger roll-forward</strong> as the source of truth: every non-archived account’s stored balance must equal the net of its <code>account_transactions</code> (credits minus debits). That catches partial reversals, manual edits, and import errors early.
                    </p>
                    <h4 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Checks</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400">
                        <li><strong>Stored balance vs ledger</strong> — critical if any account drifts beyond tolerance.</li>
                        <li><strong>Global trial</strong> — Σ credits vs Σ debits across all lines; warning if unequal (expected under some one-sided flows).</li>
                        <li><strong>Master vs Σ(member) cash &amp; fund</strong> — advisory; member-only cash debits (repayments) and guarantor fund debits break strict parity by design.</li>
                        <li><strong>Bank statement vs book</strong> — optional: compare <code class="text-xs">master_cash</code> balance to a declared statement closing balance (UI or settings). Variance is warning by default, or critical if you toggle strict on the run.</li>
                        <li><strong>Contributions vs fund ledger</strong> — critical if any non-deleted contribution has no ledger lines; warning if Σ contribution amounts ≠ Σ master-fund credits sourced from contributions.</li>
                        <li><strong>Active loans</strong> — pending installment total vs loan account outstanding.</li>
                        <li><strong>Approved loans (with disbursement)</strong> — before installments exist, loan ledger should match <code class="text-xs">amount_disbursed</code>; with installments, compared to remaining schedule.</li>
                        <li><strong>Orphan loan accounts</strong> — critical if a loan-type account exists without a loan row.</li>
                        <li><strong>Import pipeline</strong> — counts unposted bank/SMS rows (warnings if backlog).</li>
                        <li><strong>Period metrics</strong> — for daily/monthly modes, counts ledger lines and posted imports in the selected window.</li>
                    </ul>
                    <h4 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Automation &amp; settings</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Scheduler runs <code class="text-xs">fund:reconcile --daily</code> and <code class="text-xs">fund:reconcile --monthly</code> (see <code class="text-xs">routes/console.php</code>). When set, <code class="text-xs">reconciliation.bank_statement_balance</code>, <code class="text-xs">reconciliation.bank_statement_date</code>, and optional <code class="text-xs">reconciliation.bank_variance_critical</code> (true/false) are merged into each scheduled run for bank vs book.
                    </p>
                    <h4 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Permissions (Filament Shield)</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Custom permissions: <code class="text-xs">reconciliation_view</code> (page), <code class="text-xs">reconciliation_run</code> (header runs), <code class="text-xs">reconciliation_export</code> (JSON/PDF). Seeded via <code class="text-xs">ReconciliationPermissionsSeeder</code> and listed under <code class="text-xs">config/filament-shield.php</code> custom permissions. Run <code class="text-xs">php artisan db:seed --class=ReconciliationPermissionsSeeder</code> on existing databases.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
