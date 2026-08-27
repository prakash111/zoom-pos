<?php

namespace App\Http\Middleware;

use App\Services\Localization\LocalizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(
        protected LocalizationService $localization
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->localization->getActiveLocale();
        App::setLocale($locale);

        return $next($request);
    }
}
