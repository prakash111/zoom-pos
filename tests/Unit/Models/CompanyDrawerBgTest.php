<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyDrawerBgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    private function company(?string $drawerBg): Company
    {
        return Company::create([
            'name' => 'Drawer Bg Test Co',
            'slug' => 'drawer-bg-test-'.uniqid(),
            'email' => uniqid().'@drawer-bg-test.test',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
            'drawer_bg' => $drawerBg,
        ]);
    }

    public function test_blank_drawer_bg_falls_back_to_the_cream_default(): void
    {
        $this->assertSame('#FFF7ED', $this->company(null)->getDrawerBg());
        $this->assertSame('#FFF7ED', $this->company('')->getDrawerBg());
        $this->assertSame('#FFF7ED', $this->company('   ')->getDrawerBg());
    }

    #[DataProvider('whiteVariants')]
    public function test_a_literal_white_value_is_treated_as_unconfigured(string $white): void
    {
        $this->assertSame('#FFF7ED', $this->company($white)->getDrawerBg());
    }

    public static function whiteVariants(): array
    {
        return [
            ['#FFFFFF'], ['#ffffff'], ['#FFF'], ['#fff'], ['FFFFFF'], ['fff'], ['White'],
        ];
    }

    public function test_a_deliberately_chosen_non_white_colour_is_returned_as_is(): void
    {
        $company = $this->company('#0F172A');
        $this->assertSame('#0F172A', $company->getDrawerBg());
        $this->assertSame('#0F172A', $company->getThemeTokens()['drawer_bg']);
    }

    public function test_theme_tokens_never_expose_a_raw_white_drawer_bg(): void
    {
        $tokens = $this->company('#FFFFFF')->getThemeTokens();
        $this->assertNotSame('#FFFFFF', $tokens['drawer_bg']);
        $this->assertSame('#FFF7ED', $tokens['drawer_bg']);
        // gradient_start falls back to the same sanitised value.
        $this->assertSame('#FFF7ED', $tokens['drawer_gradient_start']);
    }
}
