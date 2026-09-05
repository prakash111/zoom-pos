<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthGuardServiceProvider;
use App\Providers\LocalizationServiceProvider;
use App\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthGuardServiceProvider::class,
    LocalizationServiceProvider::class,
    ModuleServiceProvider::class,
];
