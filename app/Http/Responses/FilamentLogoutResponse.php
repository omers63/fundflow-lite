<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class FilamentLogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'member'], true)) {
            return redirect()->to('/');
        }

        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl(),
        );
    }
}
