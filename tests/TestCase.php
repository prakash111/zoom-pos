<?php

namespace Tests;

use App\Support\Installation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $shutdownRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();
        Installation::markAsInstalled();

        if (! static::$shutdownRegistered) {
            register_shutdown_function(function () {
                Installation::markAsInstalled();
            });
            static::$shutdownRegistered = true;
        }

        // The license server URL is hardcoded in config; blank it for tests so
        // they run offline (format-check) by default. Tests that exercise the
        // remote path set it back with config()->set() + Http::fake().
        config([
            'services.license_server.url' => '',
            'services.license_server.store_url' => '',
            'services.license_server.secret' => '',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Installation::markAsInstalled();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        Installation::markAsInstalled();
    }
}
