<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class NavigationMenuFullPageDiagTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    /**
     * Regression test: SortableJS must load early enough (layouts/tenant.
     * blade.php's <head>, matching the superadmin layout's own working menu
     * builder) that it's already defined by the time Alpine's x-init runs
     * on the Navigation Menu tab. A <script> pushed to @stack('scripts')
     * instead resolves near the end of body — by the time x-init tries to
     * call Sortable.create(), the library is still undefined, so drag-and-
     * drop silently never activates (the defensive `typeof Sortable ===
     * 'undefined'` check avoids a crash, but there's no retry once the
     * script does finish loading). This only shows up on a real full-page
     * load through the layout — Livewire::test()->html() renders just the
     * component, not the wrapping layout where the script tag actually
     * lives, so that alone can't catch this class of bug.
     */
    public function test_sortable_js_loads_before_the_nav_builder_markup_on_a_full_page_load(): void
    {
        $this->actingAsTenantAdmin();

        $response = $this->get(route('tenant.settings.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('sortable.min.js', $html);
        $this->assertStringContainsString('tenant-navigation-builder.js', $html);
        $this->assertSame(1, substr_count($html, 'id="nav-sections-container"'));
        $this->assertStringContainsString('tenantNavigationBuilder(', $html);

        $sortablePos = strpos($html, 'sortable.min.js');
        $builderScriptPos = strpos($html, 'tenant-navigation-builder.js');
        $navBuilderPos = strpos($html, 'id="nav-sections-container"');
        $this->assertNotFalse($sortablePos);
        $this->assertNotFalse($builderScriptPos);
        $this->assertNotFalse($navBuilderPos);
        $this->assertLessThan(
            $builderScriptPos,
            $sortablePos,
            'SortableJS must load before the external navigation builder script.'
        );
        $this->assertLessThan(
            $navBuilderPos,
            $builderScriptPos,
            'The navigation builder script must load before Alpine initializes the markup.'
        );
    }
}
