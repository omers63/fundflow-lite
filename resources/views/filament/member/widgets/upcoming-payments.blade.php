@php
    $data   = $this->getData();
    $months = $data['months'];
@endphp

@if(!empty($months))
<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-calendar-days class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Upcoming Payments') }}</h3>
        </div>
        <span class="text-xs text-gray-400">{{ __('Next 3 months') }}</span>
    </div>

    {{-- Month columns --}}
    <div class="grid grid-cols-1 divide-y divide-gray-100 dark:divide-gray-700 sm:grid-cols-3 sm:divide-y-0 sm:divide-x">
        @foreach($months as $month)
        <div class="p-5 @if($month['is_current']) bg-primary-50/50 dark:bg-primary-900/10 @endif">

            {{-- Month label --}}
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-semibold {{ $month['is_current'] ? 'text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}">
                    {{ $month['label'] }}
                </h4>
                @if($month['is_current'])
                <span class="inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300">{{ __('This month') }}</span>
                @endif
            </div>

            {{-- Payment items --}}
            <div class="space-y-2.5">

                {{-- Contribution row --}}
                <div class="flex items-center justify-between rounded-lg px-3 py-2.5 {{ $month['contribution']['paid'] ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-gray-100 dark:bg-gray-700/50' }}">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full {{ $month['contribution']['paid'] ? 'bg-emerald-200 dark:bg-emerald-800' : 'bg-gray-200 dark:bg-gray-600' }}">
                            <x-heroicon-o-banknotes class="w-3.5 h-3.5 {{ $month['contribution']['paid'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-500 dark:text-gray-400' }}" />
                        </div>
                        <div>
                            <p class="text-xs font-medium {{ $month['contribution']['paid'] ? 'text-emerald-800 dark:text-emerald-200' : 'text-gray-700 dark:text-gray-300' }}">{{ __('Contribution') }}</p>
                            @if($month['contribution']['is_late'])
                            <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Paid late') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold {{ $month['contribution']['paid'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ \App\Support\UiNumber::sar($month['contribution']['amount']) }}
                        </p>
                        @if($month['contribution']['paid'])
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Paid') }}</p>
                        @else
                        <p class="text-xs text-gray-400">{{ __('Due') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Installment rows --}}
                @foreach($month['installments'] as $inst)
                @php
                    $isOverdue = $inst['status'] === 'overdue';
                    $isPaid    = $inst['status'] === 'paid';
                @endphp
                <div class="flex items-center justify-between rounded-lg px-3 py-2.5 {{ $isPaid ? 'bg-emerald-50 dark:bg-emerald-900/20' : ($isOverdue ? 'bg-red-50 dark:bg-red-900/20' : 'bg-indigo-50/60 dark:bg-indigo-900/10') }}">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full {{ $isPaid ? 'bg-emerald-200 dark:bg-emerald-800' : ($isOverdue ? 'bg-red-200 dark:bg-red-800' : 'bg-indigo-100 dark:bg-indigo-800') }}">
                            <x-heroicon-o-credit-card class="w-3.5 h-3.5 {{ $isPaid ? 'text-emerald-700 dark:text-emerald-300' : ($isOverdue ? 'text-red-600 dark:text-red-300' : 'text-indigo-600 dark:text-indigo-400') }}" />
                        </div>
                        <div>
                            <p class="text-xs font-medium {{ $isOverdue ? 'text-red-800 dark:text-red-200' : ($isPaid ? 'text-emerald-800 dark:text-emerald-200' : 'text-gray-700 dark:text-gray-300') }}">
                                {{ $inst['tier'] }}
                            </p>
                            <p class="text-xs {{ $isOverdue ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">{{ __('Due :date', ['date' => $inst['due_date']]) }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold {{ $isOverdue ? 'text-red-600 dark:text-red-400' : ($isPaid ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300') }}">
                            {{ \App\Support\UiNumber::sar($inst['amount']) }}
                        </p>
                        <p class="text-xs {{ $isOverdue ? 'text-red-500 dark:text-red-400' : ($isPaid ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400') }}">
                            {{
                                match ($inst['status']) {
                                    'paid' => __('Paid'),
                                    'overdue' => __('Overdue'),
                                    'pending' => __('Pending'),
                                    default => __($inst['status']),
                                }
                            }}
                        </p>
                    </div>
                </div>
                @endforeach

                @if(empty($month['installments']))
                <p class="text-xs text-gray-400 dark:text-gray-500 px-1">{{ __('No loan installments this month') }}</p>
                @endif
            </div>

            {{-- Month total --}}
            @if($month['total_due'] > 0)
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Total due') }}</span>
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ \App\Support\UiNumber::sar($month['total_due']) }}</span>
            </div>
            @else
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 flex items-center gap-1.5">
                <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('All settled') }}</span>
            </div>
            @endif

        </div>
        @endforeach
    </div>

</div>
@endif
