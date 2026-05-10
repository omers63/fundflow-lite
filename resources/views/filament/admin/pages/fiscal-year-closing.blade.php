<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200" for="fy-select">
                {{ __('Fiscal year') }}
            </label>
            <select id="fy-select" wire:model.live="fiscal_year_id"
                class="block w-full max-w-md rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900">
                @foreach (\App\Models\FiscalYear::query()->orderByDesc('start_date')->get() as $fy)
                    <option value="{{ $fy->id }}">{{ $fy->code }}
                        ({{ $fy->start_date?->toDateString() }} — {{ $fy->end_date?->toDateString() }}) —
                        {{ $fy->status }}
                    </option>
                @endforeach
            </select>
            @if ($this->fiscalYear)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Selected') }}: <strong>{{ $this->fiscalYear->code }}</strong>
                    · {{ __('Status') }}: {{ $this->fiscalYear->status }}
                    @if ($this->fiscalYear->closed_at)
                        · {{ __('Closed at') }}: {{ $this->fiscalYear->closed_at->format('Y-m-d H:i') }}
                    @endif
                    @if (filled($this->fiscalYear->archive_database_path))
                        <br><span class="mt-1 block font-mono text-[11px]">{{ __('Archive file') }}:
                            {{ $this->fiscalYear->archive_database_path }}</span>
                    @endif
                    @if ($this->fiscalYear->purged_primary_at)
                        <br><span class="mt-1 block text-amber-700 dark:text-amber-400">{{ __('Primary facts purged at') }}:
                            {{ $this->fiscalYear->purged_primary_at->format('Y-m-d H:i') }}</span>
                    @endif
                </p>
            @endif
        </div>

        @if (!empty($dry_run_result['tables']))
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ __('Dry run — row counts') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">{{ __('Table') }}</th>
                                <th class="py-2 pr-4">{{ __('Source (primary)') }}</th>
                                <th class="py-2">{{ __('Archive (existing in range)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($dry_run_result['tables'] as $table => $counts)
                                <tr>
                                    <td class="py-2 pr-4 font-mono text-xs">{{ $table }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $counts['source_count'] ?? '—' }}</td>
                                    <td class="py-2 tabular-nums">{{ $counts['archive_count'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ __('Recent closure activity') }}
            </h3>
            @if ($this->recentClosures->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No closure records yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">{{ __('When') }}</th>
                                <th class="py-2 pr-4">{{ __('Fiscal year') }}</th>
                                <th class="py-2 pr-4">{{ __('Action') }}</th>
                                <th class="py-2 pr-4">{{ __('Status') }}</th>
                                <th class="py-2 pr-4 min-w-[12rem]">{{ __('Error / note') }}</th>
                                <th class="py-2">{{ __('By') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($this->recentClosures as $c)
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap text-xs">
                                        {{ $c->started_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td class="py-2 pr-4">{{ $c->fiscalYear?->code ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $c->action }}</td>
                                    <td class="py-2 pr-4">{{ $c->status }}</td>
                                    <td @class([
                                        'py-2 pr-4 max-w-md text-xs break-words',
                                        'text-red-700 dark:text-red-400' => $c->status === 'failed' && filled($c->error_message),
                                    ])>{{ filled($c->error_message) ? $c->error_message : '—' }}</td>
                                    <td class="py-2 text-xs">{{ $c->startedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (!$this->canManageCritical())
            <p class="text-sm text-amber-700 dark:text-amber-400">
                {{ __('Close and restore actions are limited to the configured super admin role. Dry run and Excel export are available to all admins.') }}
            </p>
        @endif
    </div>
</x-filament-panels::page>