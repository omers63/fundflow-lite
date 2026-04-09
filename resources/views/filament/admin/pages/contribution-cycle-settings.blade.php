<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">How cycles work</h3>
            <ul class="list-disc pl-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                <li>Each <strong class="text-gray-800 dark:text-gray-200">cycle</strong> is tied to a calendar month (e.g. June 2026).</li>
                <li>That cycle <strong class="text-gray-800 dark:text-gray-200">starts</strong> on the configured day of that month.</li>
                <li>It <strong class="text-gray-800 dark:text-gray-200">ends</strong> the day before the same numbered day in the following month.</li>
                <li>The <strong class="text-gray-800 dark:text-gray-200">due date</strong> is the last day of that window (end of that day). Payments after that are late.</li>
            </ul>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
                <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
                <div class="pl-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Example (June, current year)</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->exampleJuneCycleLine() }}</p>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
                <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
                <div class="pl-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Current open period (today)</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->currentOpenPeriodLine() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 ring-1 ring-gray-200 dark:ring-gray-700 px-5 py-4 flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Change the start day</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Use <strong class="text-gray-700 dark:text-gray-300">Save settings</strong> in the header. The same rule is used for contribution collection, dependent cash allocation, and loan repayments that follow the fund cycle.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
