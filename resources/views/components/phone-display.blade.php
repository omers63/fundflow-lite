@props([
    'value' => null,
    'empty' => '—',
])

@php
    $parts = \App\Support\PhoneDisplay::splitForDisplay($value);
@endphp

@if ($parts === null)
    <span {{ $attributes }}>{{ $empty }}</span>
@elseif ($parts['prefix'] === '')
    <span {{ $attributes->merge(['class' => 'phone-display-e164 font-mono text-sm']) }} dir="ltr">{{ e($parts['national']) }}</span>
@else
    <span {{ $attributes->merge(['class' => 'phone-display-e164 font-mono text-sm']) }} dir="ltr">
        <span class="phone-cc">{{ e($parts['prefix']) }}</span><span class="phone-local">{{ e($parts['national']) }}</span>
    </span>
@endif
