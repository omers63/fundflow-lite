<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Warning hero --}}
        <div class="rounded-2xl bg-gradient-to-br from-red-600 to-rose-700 shadow-lg ring-1 ring-red-800 overflow-hidden">
            <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-white/15 ring-2 ring-white/20">
                        <x-heroicon-o-exclamation-triangle class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Destructive operation</h2>
                        <p class="mt-1 text-sm text-red-100">
                            Purge removes <strong class="text-white">all rows</strong> from every table that does <strong class="text-white">not</strong> have a
                            <code class="text-xs bg-white/20 px-1 rounded">deleted_at</code> column, except protected system tables (users, permissions, migrations, queues, cache, sessions).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tables to purge --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-red-500" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tables that will be emptied</h3>
                </div>
                <span class="text-xs font-medium text-gray-500">{{ count($purgeableTables) }} table{{ count($purgeableTables) !== 1 ? 's' : '' }}</span>
            </div>
            <div class="px-6 py-4 max-h-64 overflow-y-auto">
                @if(count($purgeableTables) === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">No tables match the purge rules.</p>
                @else
                    <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($purgeableTables as $table)
                            <li class="flex items-center gap-2 rounded-lg bg-red-50 dark:bg-red-900/15 ring-1 ring-red-100 dark:ring-red-900/40 px-3 py-2 text-sm font-mono text-red-800 dark:text-red-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>
                                {{ $table }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Always excluded --}}
            <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-shield-check class="w-4 h-4 text-emerald-500" />
                        Always preserved
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Never truncated by this tool.</p>
                </div>
                <div class="px-6 py-4 max-h-48 overflow-y-auto">
                    <ul class="space-y-1">
                        @foreach($alwaysExcludedTables as $table)
                            <li class="text-xs font-mono text-gray-600 dark:text-gray-400">{{ $table }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Skipped: soft deletes --}}
            <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-archive-box-x-mark class="w-4 h-4 text-amber-500" />
                        Skipped (has <code class="text-xs">deleted_at</code>)
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Not included in purge while this column exists.</p>
                </div>
                <div class="px-6 py-4 max-h-48 overflow-y-auto">
                    @if(count($softDeleteSkippedTables) === 0)
                        <p class="text-xs text-gray-500 dark:text-gray-400">None of your application tables currently use soft deletes.</p>
                    @else
                        <ul class="space-y-1">
                            @foreach($softDeleteSkippedTables as $table)
                                <li class="text-xs font-mono text-amber-700 dark:text-amber-300">{{ $table }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <p class="text-xs text-center text-gray-400">
            After purging business data you may need to run <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan db:seed</code> to restore defaults.
        </p>
    </div>
</x-filament-panels::page>
