<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthGuardServiceProvider;
use App\Providers\LocalizationServiceProvider;

return [
    AppServiceProvider::class,
    AuthGuardServiceProvider::class,
    LocalizationServiceProvider::class,
];
