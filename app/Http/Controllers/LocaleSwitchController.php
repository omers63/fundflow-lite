<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LocaleSwitchController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED = ['en', 'ar'];

    private static ?bool $canPersistUserLocale = null;

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        if ($request->user() !== null && $this->canPersistUserLocale()) {
            $request->user()->forceFill([
                'preferred_locale' => $locale,
            ])->save();
        }

        // When users open /locale/{locale} directly (no Referer), back() needs a safe fallback.
        return redirect()->back(302, [], route('home'));
    }

    private function canPersistUserLocale(): bool
    {
        if (self::$canPersistUserLocale !== null) {
            return self::$canPersistUserLocale;
        }

        return self::$canPersistUserLocale = Schema::hasColumn('users', 'preferred_locale');
    }
}
