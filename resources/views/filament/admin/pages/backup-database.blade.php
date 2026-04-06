<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/50 ring-1 ring-emerald-200 dark:ring-emerald-800">
                    <x-heroicon-o-arrow-down-tray class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Database backups</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        <strong class="text-gray-700 dark:text-gray-300">Download backup</strong> streams a copy to your browser without saving on the server.
                        <strong class="text-gray-700 dark:text-gray-300">Save backup to server</strong> writes to <code class="text-xs bg-gray-200 dark:bg-gray-700 px-1 rounded">storage/app/backups/</code> and records it in the history table below.
                    </p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                <div class="rounded-xl bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-100 dark:ring-gray-700 p-4 space-y-2">
                    <p class="font-medium text-gray-800 dark:text-gray-200">SQLite</p>
                    <p>Downloads the configured <code class="text-xs bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">.sqlite</code> file as-is. Fast and complete.</p>
                </div>
                <div class="rounded-xl bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-100 dark:ring-gray-700 p-4 space-y-2">
                    <p class="font-medium text-gray-800 dark:text-gray-200">MySQL / MariaDB</p>
                    <p>Runs <code class="text-xs bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">mysqldump</code> using your <code class="text-xs bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">MYSQL_*</code> connection settings. The client tools must be installed and available in your system PATH.</p>
                </div>
                <p class="text-xs text-amber-600 dark:text-amber-400 flex items-start gap-2">
                    <x-heroicon-o-shield-exclamation class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <span>Store backups securely. Anyone with an admin session can use this download while logged in.</span>
                </p>
            </div>

            <div class="px-6 pb-6">
                <x-filament::button
                    tag="a"
                    href="{{ route('admin.system.backup-download') }}"
                    icon="heroicon-o-arrow-down-tray"
                    color="primary"
                >
                    Download backup
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
