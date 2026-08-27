<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\Product;
use Illuminate\Database\Seeder;

class RestaurantDemoSeeder extends Seeder
{
    public function run(?Company $targetCompany = null): void
    {
        $companies = $targetCompany ? collect([$targetCompany]) : Company::all();

        foreach ($companies as $company) {
            // 1. Create Dining Floors if none exist
            if (DiningFloor::where('company_id', $company->id)->count() === 0) {
                $indoor = DiningFloor::create([
                    'company_id' => $company->id,
                    'name' => 'Main Dining Area',
                    'order_index' => 1,
                    'is_active' => true,
                ]);

                $rooftop = DiningFloor::create([
                    'company_id' => $company->id,
                    'name' => 'Rooftop Terrace',
                    'order_index' => 2,
                    'is_active' => true,
                ]);

                $patio = DiningFloor::create([
                    'company_id' => $company->id,
                    'name' => 'Patio & Bar',
                    'order_index' => 3,
                    'is_active' => true,
                ]);

                // Tables for Indoor
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $indoor->id, 'table_number' => 'Table 01', 'seating_capacity' => 4, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $indoor->id, 'table_number' => 'Table 02', 'seating_capacity' => 2, 'status' => 'occupied', 'guest_count' => 2]);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $indoor->id, 'table_number' => 'Table 03', 'seating_capacity' => 6, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $indoor->id, 'table_number' => 'Table 04', 'seating_capacity' => 4, 'status' => 'reserved']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $indoor->id, 'table_number' => 'Table 05', 'seating_capacity' => 8, 'status' => 'billed']);

                // Tables for Rooftop
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $rooftop->id, 'table_number' => 'R-01', 'seating_capacity' => 4, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $rooftop->id, 'table_number' => 'R-02', 'seating_capacity' => 4, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $rooftop->id, 'table_number' => 'R-03', 'seating_capacity' => 2, 'status' => 'available']);

                // Tables for Patio & Bar
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $patio->id, 'table_number' => 'Bar-01', 'seating_capacity' => 2, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $patio->id, 'table_number' => 'Bar-02', 'seating_capacity' => 2, 'status' => 'available']);
                DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $patio->id, 'table_number' => 'Patio-01', 'seating_capacity' => 6, 'status' => 'available']);
            }

            // 2. Create Restaurant Categories & Foods if not present
            $happyHour = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Happy Hour Sale']);
            $burgers = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Burgers']);
            $tacos = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Tacos']);
            $lunch = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Lunch Special']);
            $salads = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Salads & Soups']);
            $desserts = Category::firstOrCreate(['company_id' => $company->id, 'name' => 'Desserts & Sweets']);

            $menuItems = [
                [
                    'name' => 'Double Cheeseburger',
                    'category_id' => $burgers->id,
                    'category_name' => $burgers->name,
                    'sale_price' => 7.50,
                    'cost_price' => 3.20,
                    'current_stock' => 100,
                    'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400',
                    'variants' => [
                        ['name' => 'Single Patty', 'price' => 6.00],
                        ['name' => 'Double Patty', 'price' => 7.50],
                        ['name' => 'Triple Monster', 'price' => 9.50],
                    ],
                    'modifiers' => [
                        ['name' => 'Extra Melted Cheddar', 'price' => 1.50],
                        ['name' => 'Crispy Smoked Bacon', 'price' => 2.00],
                        ['name' => 'Pickled Jalapenos', 'price' => 0.75],
                        ['name' => 'Truffle Mayo', 'price' => 1.25],
                    ],
                ],
                [
                    'name' => 'Burger Combo',
                    'category_id' => $burgers->id,
                    'category_name' => $burgers->name,
                    'sale_price' => 9.50,
                    'cost_price' => 4.00,
                    'current_stock' => 80,
                    'image_url' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=400',
                    'variants' => [
                        ['name' => 'Regular Fries Combo', 'price' => 9.50],
                        ['name' => 'Curly Fries & Shake Combo', 'price' => 12.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Upgrade to Onion Rings', 'price' => 1.50],
                        ['name' => 'Large Soda', 'price' => 1.00],
                    ],
                ],
                [
                    'name' => 'Cheese Pizza',
                    'category_id' => $lunch->id,
                    'category_name' => $lunch->name,
                    'sale_price' => 13.50,
                    'cost_price' => 4.50,
                    'current_stock' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400',
                    'variants' => [
                        ['name' => 'Medium (10")', 'price' => 13.50],
                        ['name' => 'Large (14")', 'price' => 17.50],
                        ['name' => 'Extra Large (18")', 'price' => 22.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Stuffed Cheese Crust', 'price' => 3.00],
                        ['name' => 'Extra Mozzarella', 'price' => 2.00],
                        ['name' => 'Spicy Pepperoni', 'price' => 2.50],
                        ['name' => 'Mushrooms & Olives', 'price' => 1.50],
                    ],
                ],
                [
                    'name' => 'Power Lunch Linguine',
                    'category_id' => $lunch->id,
                    'category_name' => $lunch->name,
                    'sale_price' => 22.00,
                    'cost_price' => 7.00,
                    'current_stock' => 50,
                    'image_url' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=400',
                    'variants' => [
                        ['name' => 'White Cream Sauce', 'price' => 22.00],
                        ['name' => 'Spicy Red Arrabiata', 'price' => 22.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Grilled Chicken Breast', 'price' => 4.50],
                        ['name' => 'Jumbo Garlic Prawns', 'price' => 6.00],
                        ['name' => 'Shaved Parmesan', 'price' => 1.50],
                    ],
                ],
                [
                    'name' => 'Vegetable Salad',
                    'category_id' => $salads->id,
                    'category_name' => $salads->name,
                    'sale_price' => 12.00,
                    'cost_price' => 3.00,
                    'current_stock' => 70,
                    'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=400',
                    'variants' => [
                        ['name' => 'Standard Bowl', 'price' => 12.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Avocado Slices', 'price' => 2.50],
                        ['name' => 'Feta Cheese Crumbles', 'price' => 1.50],
                        ['name' => 'Balsamic Vinaigrette', 'price' => 0.50],
                    ],
                ],
                [
                    'name' => 'Fruit Salad',
                    'category_id' => $salads->id,
                    'category_name' => $salads->name,
                    'sale_price' => 2.00,
                    'cost_price' => 0.80,
                    'current_stock' => 90,
                    'image_url' => 'https://images.unsplash.com/photo-1568569350062-ebfa3cb195df?w=400',
                    'variants' => [
                        ['name' => 'Cup', 'price' => 2.00],
                        ['name' => 'Large Bowl', 'price' => 5.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Honey Lime Dressing', 'price' => 0.50],
                        ['name' => 'Chia Seeds', 'price' => 0.50],
                    ],
                ],
                [
                    'name' => 'Green Salad',
                    'category_id' => $salads->id,
                    'category_name' => $salads->name,
                    'sale_price' => 9.00,
                    'cost_price' => 2.50,
                    'current_stock' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400',
                    'variants' => [],
                    'modifiers' => [],
                ],
                [
                    'name' => 'Strawberry Cake',
                    'category_id' => $desserts->id,
                    'category_name' => $desserts->name,
                    'sale_price' => 7.00,
                    'cost_price' => 2.20,
                    'current_stock' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=400',
                    'variants' => [
                        ['name' => 'Slice', 'price' => 7.00],
                        ['name' => 'Whole 8" Cake', 'price' => 38.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Vanilla Ice Cream Scoop', 'price' => 2.00],
                        ['name' => 'Extra Whipped Cream', 'price' => 1.00],
                    ],
                ],
                [
                    'name' => 'Chocolate Doughnut',
                    'category_id' => $desserts->id,
                    'category_name' => $desserts->name,
                    'sale_price' => 6.00,
                    'cost_price' => 1.50,
                    'current_stock' => 50,
                    'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=400',
                    'variants' => [],
                    'modifiers' => [],
                ],
                [
                    'name' => 'Crispy Beef Tacos',
                    'category_id' => $tacos->id,
                    'category_name' => $tacos->name,
                    'sale_price' => 8.50,
                    'cost_price' => 2.80,
                    'current_stock' => 75,
                    'image_url' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400',
                    'variants' => [
                        ['name' => 'Set of 3 Tacos', 'price' => 8.50],
                        ['name' => 'Set of 5 Tacos', 'price' => 13.00],
                    ],
                    'modifiers' => [
                        ['name' => 'Guacamole Dip', 'price' => 2.00],
                        ['name' => 'Sour Cream & Salsa', 'price' => 1.25],
                    ],
                ],
            ];

            foreach ($menuItems as $item) {
                Product::updateOrCreate([
                    'company_id' => $company->id,
                    'name' => $item['name'],
                ], [
                    'category_id' => $item['category_id'],
                    'category_name' => $item['category_name'],
                    'sale_price' => $item['sale_price'],
                    'cost_price' => $item['cost_price'],
                    'current_stock' => $item['current_stock'],
                    'image_url' => $item['image_url'],
                    'variants' => $item['variants'],
                    'modifiers' => $item['modifiers'],
                    'active' => true,
                ]);
            }

            // Ensure all other products have vibrant food images
            foreach (Product::where('company_id', $company->id)->get() as $p) {
                if (empty($p->image_url)) {
                    $p->image_url = $p->getImageUrlOrDefault();
                    $p->save();
                }
            }
        }
    }
}
