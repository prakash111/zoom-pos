<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class MobilePosTerminalTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    public function test_pos_terminal_renders_mobile_floating_pill_and_bottom_sheet_cart(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $cat = Category::create(['company_id' => $company->id, 'name' => 'Beverages']);
        $product = Product::create([
            'company_id' => $company->id,
            'category_id' => $cat->id,
            'name' => 'Iced Latte',
            'sale_price' => 4.50,
            'current_stock' => 50,
            'active' => true,
        ]);

        $response = $this->get(route('tenant.sales.create'));
        $response->assertOk();
        $response->assertSee('View Current Sale');
        $response->assertSee('mobileCartOpen');
        $response->assertSee('pb-safe');

        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->assertSee('Iced Latte')
            ->assertSee('View Current Sale')
            ->assertSee('Total Payable')
            ->assertSee('Complete Checkout');
    }

    public function test_mobile_cart_item_count_increments_and_computes_totals(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $product1 = Product::create([
            'company_id' => $company->id,
            'name' => 'Burger',
            'sale_price' => 10.00,
            'current_stock' => 20,
            'active' => true,
        ]);

        $product2 = Product::create([
            'company_id' => $company->id,
            'name' => 'Fries',
            'sale_price' => 5.00,
            'current_stock' => 30,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product1->id)
            ->call('addProductToCart', $product1->id)
            ->call('addProductToCart', $product2->id);

        $this->assertSame(3, $component->get('cartItemCount'));
        $this->assertSame(25.0, (float) $component->get('total'));
    }

    public function test_mobile_bottom_sheet_quantity_stepper_and_item_removal(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Espresso',
            'sale_price' => 3.00,
            'current_stock' => 40,
            'active' => true,
        ]);

        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->call('increaseQuantity', 0)
            ->assertSet('items.0.quantity', 2)
            ->call('decreaseQuantity', 0)
            ->assertSet('items.0.quantity', 1)
            ->call('removeItem', 0)
            ->assertSet('cartItemCount', 0);
    }

    public function test_category_pills_render_native_badges_and_product_counts(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $cat1 = Category::create(['company_id' => $company->id, 'name' => 'Desserts & Sweets']);
        $cat2 = Category::create(['company_id' => $company->id, 'name' => 'Beverages']);

        Product::create([
            'company_id' => $company->id,
            'category_id' => $cat1->id,
            'name' => 'Cheesecake',
            'sale_price' => 6.00,
            'current_stock' => 15,
            'active' => true,
        ]);

        Product::create([
            'company_id' => $company->id,
            'category_id' => $cat2->id,
            'name' => 'Matcha Latte',
            'sale_price' => 5.00,
            'current_stock' => 25,
            'active' => true,
        ]);

        $response = $this->get(route('tenant.sales.create'));
        $response->assertOk();
        $response->assertSee('pos-categories-container');
        $response->assertSee('Desserts &amp; Sweets', false);
        $response->assertSee('Beverages');

        Livewire::test(SalesCreate::class)
            ->call('selectCategory', $cat1->id)
            ->assertSee('Cheesecake')
            ->assertDontSee('Matcha Latte')
            ->call('selectCategory', null)
            ->assertSee('Cheesecake')
            ->assertSee('Matcha Latte');
    }
}
