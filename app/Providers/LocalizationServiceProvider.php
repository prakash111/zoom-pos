<?php

namespace App\Providers;

use App\Services\Localization\TenantAwareTranslator;
use App\Services\Localization\TenantTranslationLoader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class LocalizationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('translation.loader', function ($app) {
            $fileLoader = new FileLoader($app['files'], [base_path('lang'), $app['path.lang']]);

            return new TenantTranslationLoader($fileLoader);
        });

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];
            $locale = $app->getLocale();

            $trans = new TenantAwareTranslator($loader, $locale);
            $trans->setFallback($app->getFallbackLocale());

            return $trans;
        });
    }

    /**
     * Bootstrap translation decorators.
     */
    public function boot(): void
    {
        // Extend translation.loader if base FileLoader was resolved by framework
        $this->app->extend('translation.loader', function ($loader, $app) {
            if ($loader instanceof TenantTranslationLoader) {
                return $loader;
            }
            if ($loader instanceof FileLoader) {
                return new TenantTranslationLoader($loader);
            }
            $fileLoader = new FileLoader($app['files'], [base_path('lang'), $app['path.lang']]);

            return new TenantTranslationLoader($fileLoader);
        });

        // Extend translator to be TenantAwareTranslator
        $this->app->extend('translator', function ($trans, $app) {
            if ($trans instanceof TenantAwareTranslator) {
                return $trans;
            }
            $loader = $app['translation.loader'];
            $tenantTrans = new TenantAwareTranslator($loader, $app->getLocale());
            $tenantTrans->setFallback($app->getFallbackLocale());

            return $tenantTrans;
        });
    }
}
