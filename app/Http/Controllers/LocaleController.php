<?php

namespace App\Http\Controllers;

use App\Services\Localization\LocalizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(string $locale, LocalizationService $localization, Request $request): RedirectResponse
    {
        $localization->setLocale($locale);

        return back()->with('status', 'Language switched successfully.');
    }
}
