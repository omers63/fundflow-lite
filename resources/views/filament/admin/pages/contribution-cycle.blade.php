<x-filament-panels::page>

    {{-- Period summary cards --}}
    @php
        $service   = app(\App\Services\ContributionCycleService::class);
        $summaries = $service->periodSummaries(6);
        $activeCount = \App\Models\Member::active()->count();
    @endphp

    @if($summaries->isNotEmpty())
    <x-filament::section heading="Recent Periods">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($summaries as $row)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['period_label'] }}</span>
                    @if($row['late_count'] > 0)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                            {{ $row['late_count'] }} late
                        </span>
                    @endif
                </div>
                <div class="mt-2 text-2xl font-bold text-primary-600 dark:text-primary-400">
                    SAR {{ number_format($row['total_amount']) }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $row['total_count'] }} / {{ $activeCount }} members &bull; Due {{ $row['deadline'] }}
                </div>
            </div>
            @endforeach
        </div>
    </x-filament::section>
    @endif

    {{-- Pending members table --}}
    <x-filament::section>
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
