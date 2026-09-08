<?php

namespace Tests\Concerns;

use App\Models\SduiModule;
use App\Services\Modular\ModulePackageService;

/**
 * Helper for lifecycle tests that install a package module and then need it
 * licensed before activation. Uses the real verify path — with no license
 * server configured the offline fallback accepts any well-formed key.
 */
trait LicensesModules
{
    protected function licenseModule(SduiModule $module, string $key = 'demo-test-suite-license-000000'): SduiModule
    {
        $result = app(ModulePackageService::class)->verifyAndRecordLicense($module, $key, null);

        if (! $result['status']) {
            $this->fail('Test license key was rejected: '.$result['message']);
        }

        return $module->refresh();
    }
}
