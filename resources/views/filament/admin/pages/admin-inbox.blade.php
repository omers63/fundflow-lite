<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Members Inbox') }}</x-slot>
        <x-slot name="description">{{ __('Use the row action to open communication with a member.') }}</x-slot>
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
