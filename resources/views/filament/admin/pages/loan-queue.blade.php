<x-filament-panels::page>
    @php $fundTiers = $this->getFundTiers(); @endphp

    @forelse($fundTiers as $ft)
        @php $queue = $this->getQueueForTier($ft->id); @endphp

        <x-filament::section>
            <x-slot name="heading">
                {{ $ft->label }}
                <span class="ml-2 text-sm font-normal text-gray-500">
                    ({{ $ft->percentage }}% allocation — Available: SAR {{ number_format($ft->available_amount) }})
                </span>
            </x-slot>

            @if($queue->isEmpty())
                <p class="text-sm text-gray-400 italic">No pending loans in this queue.</p>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Loan Tier</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (SAR)</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Applied</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($queue as $loan)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-3 py-2 font-bold text-primary-600">#{{ $loan->queue_position ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $loan->member->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $loan->member->member_number }}</div>
                            </td>
                            <td class="px-3 py-2 text-gray-500">{{ $loan->loanTier?->label ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($loan->amount_approved ?? $loan->amount_requested, 2) }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-yellow-100 text-yellow-800' => $loan->status === 'pending',
                                    'bg-blue-100 text-blue-800'   => $loan->status === 'approved',
                                ])>{{ ucfirst($loan->status) }}</span>
                            </td>
                            <td class="px-3 py-2 text-gray-500">{{ $loan->applied_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-gray-400 italic">No active fund tiers configured.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
