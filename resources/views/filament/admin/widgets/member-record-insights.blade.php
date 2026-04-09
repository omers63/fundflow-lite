@php
    use Illuminate\View\ComponentAttributeBag;
@endphp

{{-- Match Filament header widgets: Grid::make(1) + fi-sc + fi-sc-has-gap; each child = fi-grid-col (full) + fi-sc-component (see filament/schemas Component::toSchemaHtml). --}}
<div {{ (new ComponentAttributeBag)->grid(['default' => 1])->class(['fi-sc', 'fi-sc-has-gap', 'w-full']) }}>
    <div {{ (new ComponentAttributeBag)->gridColumn(['default' => 'full']) }}>
        <div class="fi-sc-component">
            @include('filament.admin.widgets.member-account-stats', ['d' => $stats])
        </div>
    </div>

    <div {{ (new ComponentAttributeBag)->gridColumn(['default' => 'full']) }}>
        <div class="fi-sc-component">
            @include('filament.admin.widgets.member-profile', ['d' => $profile])
        </div>
    </div>

    <div {{ (new ComponentAttributeBag)->gridColumn(['default' => 'full']) }}>
        <div class="fi-sc-component">
            @include('filament.admin.widgets.member-activity', ['d' => $activity])
        </div>
    </div>
</div>
