@php
    $s = $stats;
@endphp
<div class="fi-wi-database-backup-overview rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700 overflow-hidden">
    <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50 dark:from-gray-900 dark:to-blue-950/40">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <x-heroicon-o-circle-stack class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            Database overview
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Live connection stats and backup storage summary.</p>
    </div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Driver</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $s['driver'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Connection: {{ $s['connection'] }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Database</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white break-all">{{ $s['display_name'] }}</p>
            @if($s['path_or_schema'] && $s['driver'] === 'sqlite')
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono break-all">{{ $s['path_or_schema'] }}</p>
            @endif
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Reported size</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $liveSize }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                @if($modified)
                    File last modified: {{ $modified }}
                @else
                    @if(in_array($s['driver'], ['mysql', 'mariadb'], true))
                        Approx. data + index (information_schema)
                    @else
                        —
                    @endif
                @endif
            </p>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tables</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($s['table_count']) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">On default connection</p>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stored backups</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($s['stored_backup_count']) }} <span class="text-sm font-normal text-gray-500">files</span></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Recorded total: {{ $storedTotal }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Backup folder</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $folderTotal }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">storage/app/backups/</p>
        </div>
    </div>
</div>
