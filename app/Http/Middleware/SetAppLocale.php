<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetAppLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Check Accept-Language or custom headers sent by ZooM POS Flutter app
        $locale = $request->header('X-App-Locale') 
               ?? $request->header('Accept-Language') 
               ?? config('app.locale', 'en');

        // Extract primary 2-letter language code (e.g., 'en-US' -> 'en')
        $locale = strtolower(substr(trim(explode(';', explode(',', (string) $locale)[0])[0]), 0, 2));

        $supportedLocales = [
            'en', // English
            'hi', // Hindi
            'ar', // Arabic
            'zh', // Chinese
            'nl', // Dutch
            'fr', // French
            'de', // German
            'id', // Indonesian
            'it', // Italian
            'ja', // Japanese
            'pt', // Portuguese
            'ru', // Russian
            'es', // Spanish
            'tr', // Turkish
        ];

        if (in_array($locale, $supportedLocales, true)) {
            App::setLocale($locale);
        } else {
            App::setLocale('en');
        }

        return $next($request);
    }
}
