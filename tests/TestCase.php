<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @touch(storage_path('installed'));

        // The license server URL is hardcoded in config; blank it for tests so
        // they run offline (format-check) by default. Tests that exercise the
        // remote path set it back with config()->set() + Http::fake().
        config([
            'services.license_server.url' => '',
            'services.license_server.store_url' => '',
            'services.license_server.secret' => '',
        ]);
    }
}
