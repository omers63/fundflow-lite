<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-200 dark:ring-primary-700 p-5">
            <div class="flex items-start gap-3">
                <x-heroicon-o-arrow-down-circle
                    class="w-6 h-6 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-primary-800 dark:text-primary-300">
                        {{ __('Post member funds') }}
                    </p>
                    <p class="text-sm text-primary-700 dark:text-primary-400 mt-1">
                        {{ __('Use the actions above to post your transfer. The workflow posts to fund bank intake, master cash, your cash account, dependent allocations (if needed), then applies contributions or repayments.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <x-heroicon-o-arrow-down-circle class="w-5 h-5 text-emerald-500" />
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Post Funds') }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Your transfer is automatically applied as contribution or repayment based on your account status and active cycle rules.') }}
            </p>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Recent posted funds') }}</h3>
                <span
                    class="text-xs text-gray-500 dark:text-gray-400">{{ __('Last :count records', ['count' => 10]) }}</span>
            </div>

            @php $posts = $this->recentPosts(); @endphp

            @if($posts === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No posted funds yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 text-left">{{ __('Transaction date') }}</th>
                                <th class="py-2 text-left">{{ __('Type') }}</th>
                                <th class="py-2 text-left">{{ __('Amount (SAR)') }}</th>
                                <th class="py-2 text-left">{{ __('Reference') }}</th>
                                <th class="py-2 text-left">{{ __('Attachment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($posts as $post)
                                <tr class="border-b border-gray-100 dark:border-gray-700/70">
                                    <td class="py-2">{{ $post['date'] ?? '—' }}</td>
                                    <td class="py-2">{{ $post['apply_label'] }}</td>
                                    <td class="py-2">{{ \App\Support\UiNumber::sar($post['amount']) }}</td>
                                    <td class="py-2">{{ $post['reference'] !== '' ? $post['reference'] : '—' }}</td>
                                    <td class="py-2">
                                        @if(!empty($post['attachment_url']))
                                            <a class="text-primary-600 hover:underline" href="{{ $post['attachment_url'] }}"
                                                target="_blank" rel="noopener noreferrer">
                                                {{ __('View') }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>