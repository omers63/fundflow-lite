<x-filament-panels::page>
    <x-filament::section
        heading="Public apply page"
        description="Limits apply only to new submissions on the public membership wizard (not admin-created applications). Use Save settings in the header to change the cap."
    >
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <dt class="text-gray-500 dark:text-gray-400">Current pending applications</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ number_format($this->getPendingApplicationsCount()) }}
                </dd>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <dt class="text-gray-500 dark:text-gray-400">Configured maximum</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    @if(\App\Models\Setting::maxPendingPublicApplications() === 0)
                        <span class="text-primary-600 dark:text-primary-400">No limit</span>
                    @else
                        {{ number_format(\App\Models\Setting::maxPendingPublicApplications()) }}
                    @endif
                </dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
