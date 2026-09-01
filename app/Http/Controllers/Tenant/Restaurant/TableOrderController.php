<?php

namespace App\Http\Controllers\Tenant\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

class TableOrderController extends Controller
{
    /**
     * Display digital menu for guests who scanned the QR code at their table.
     */
    public function show(string $token)
    {
        $table = DiningTable::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->firstOrFail();

        $company = $table->company;

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q) {
                $q->where('active', true)->orWhereNull('active');
            })
            ->orderBy('name')
            ->get();

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('restaurant.table-order', [
            'table' => $table,
            'company' => $company,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    /**
     * Submit table order placed from digital menu.
     */
    public function placeOrder(Request $request, string $token)
    {
        $table = DiningTable::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->firstOrFail();

        $company = $table->company;

        $validated = $request->validate([
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.variant' => ['nullable', 'string'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.spice_level' => ['nullable', 'string'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.seat' => ['nullable'],
        ]);

        $subtotal = 0;
        $formattedItems = [];

        foreach ($validated['items'] as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['price'];
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;

            $formattedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'quantity' => $qty,
                'price' => $price,
                'variant' => $item['variant'] ?? null,
                'modifiers' => $item['modifiers'] ?? [],
                'spice_level' => $item['spice_level'] ?? null,
                'note' => $item['note'] ?? null,
                'seat' => $item['seat'] ?? 1,
            ];
        }

        $saleCount = Sale::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $saleNumber = 'ORD-'.sprintf('%04d', $saleCount + 1);

        // 1. Create Sale Order
        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_number' => $saleNumber,
            'customer_name' => $validated['guest_name'] ?: ($table->table_number.' Guest'),
            'total' => $subtotal,
            'discount' => 0,
            'payment_method' => 'unpaid',
            'status' => 'pending',
            'operation_type' => 'sale',
            'service_type' => 'dine_in',
            'dining_table_id' => $table->id,
            'table_name' => $table->table_number.($table->floor ? " ({$table->floor->name})" : ''),
            'guest_count' => $validated['guest_count'] ?? 1,
            'kot_status' => 'pending',
            'items' => $formattedItems,
        ]);

        // 2. Generate Kitchen Order Ticket (KOT)
        $kotCount = KitchenTicket::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $kotNumber = 'KOT-'.sprintf('%03d', $kotCount + 1);

        $kot = KitchenTicket::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'kot_number' => $kotNumber,
            'dining_table_id' => $table->id,
            'table_name' => $sale->table_name,
            'service_type' => 'dine_in',
            'status' => 'pending',
            'server_name' => 'Table QR Order',
            'items' => $formattedItems,
            'kitchen_notes' => $validated['special_instructions'] ?? null,
        ]);

        // 3. Mark Table Occupied
        $table->update([
            'status' => DiningTable::STATUS_OCCUPIED,
            'current_sale_id' => $sale->id,
            'guest_count' => $validated['guest_count'] ?? 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully! The kitchen is preparing your items.',
                'sale_number' => $saleNumber,
                'kot_number' => $kotNumber,
            ]);
        }

        return redirect()->route('restaurant.table.order', ['token' => $token])
            ->with('order_success', "🎉 Your order #{$saleNumber} has been sent to the kitchen!");
    }

    /**
     * Printable Table Stand Tent Card with QR code.
     */
    public function qrCard(DiningTable $table)
    {
        $company = $table->company;
        $qrUrl = $table->getQrOrderUrl();

        return view('restaurant.table-qr-card', [
            'table' => $table,
            'company' => $company,
            'qrUrl' => $qrUrl,
        ]);
    }
}
