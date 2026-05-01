@php
    $isImpersonating = session()->has('impersonator_user_id');
    $name = auth()->user()?->name;
@endphp

@if($isImpersonating)
    <div class="ff-impersonation-banner" role="status" aria-live="polite">
        <span class="ff-impersonation-banner__dot" aria-hidden="true"></span>
        <span class="ff-impersonation-banner__text">
            {{ __('Impersonating: :name', ['name' => $name ?: __('Member')]) }}
        </span>
    </div>

    <script>
        (function () {
            const markTopbar = () => {
                const banner = document.querySelector('.ff-impersonation-banner');
                const topbar = banner?.closest('.fi-topbar');
                if (topbar) {
                    topbar.classList.add('ff-impersonation-topbar');
                }
            };

            markTopbar();
            document.addEventListener('livewire:navigated', markTopbar, { once: true });
        })();
    </script>
@endif