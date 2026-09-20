<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontReviewController extends Controller
{
    protected function resolveCompany(Request $request): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $company = Company::withoutGlobalScopes()->find(app('tenant.company_id'));
            if ($company) {
                return $company;
            }
        }

        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($baseHost && str_ends_with($host, '.'.$baseHost)) {
            $slug = substr($host, 0, -(strlen($baseHost) + 1));
            if ($slug && ! in_array($slug, Company::RESERVED_SLUGS, true)) {
                $company = Company::withoutGlobalScopes()->where('slug', $slug)->first();
                if ($company) {
                    return $company;
                }
            }
        }

        if ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1') {
            $company = Company::withoutGlobalScopes()->where('custom_domain', $host)->first();
            if ($company) {
                return $company;
            }
        }

        $storeSlug = $request->input('store') ?: $request->query('store') ?: $request->input('store_slug');
        if ($storeSlug) {
            $company = Company::withoutGlobalScopes()->where('slug', $storeSlug)->first();
            if ($company) {
                return $company;
            }
        }

        if ($request->filled('company_id')) {
            $company = Company::withoutGlobalScopes()->find($request->input('company_id'));
            if ($company) {
                return $company;
            }
        }

        return Company::withoutGlobalScopes()->first();
    }

    protected function resolveCustomer(Request $request, Company $company): ?Customer
    {
        $token = $request->input('auth_token')
            ?? $request->query('auth_token')
            ?? $request->bearerToken()
            ?? $request->header('X-Customer-Token')
            ?? session('customer_auth_token');

        if (! $token) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('auth_token', $token)
            ->first();
    }

    /**
     * Get reviews for a specific product.
     * GET /store/products/{productId}/reviews
     * GET /api/v1/storefront/products/{productId}/reviews
     */
    public function index(Request $request, int|string $productId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $reviewsEnabled = (bool) ($company->enable_product_reviews ?? true);

        $product = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $productId)
            ->first();

        abort_if(! $product, 404, 'Product not found');

        $reviews = ProductReview::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->approved()
            ->recent()
            ->get();

        $totalReviews = $reviews->count();
        $avgRating = $totalReviews > 0 ? round((float) $reviews->avg('rating'), 1) : 5.0;

        $distribution = [
            5 => $reviews->where('rating', 5)->count(),
            4 => $reviews->where('rating', 4)->count(),
            3 => $reviews->where('rating', 3)->count(),
            2 => $reviews->where('rating', 2)->count(),
            1 => $reviews->where('rating', 1)->count(),
        ];

        return response()->json([
            'success' => true,
            'reviews_enabled' => $reviewsEnabled,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'average_rating' => $avgRating,
            'total_reviews' => $totalReviews,
            'rating_distribution' => $distribution,
            'reviews' => $reviews->map(fn($r) => [
                'id' => $r->id,
                'customer_name' => $r->customer_name,
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'comment' => $r->comment,
                'is_verified_purchase' => (bool) $r->is_verified_purchase,
                'created_at' => $r->created_at->format('M d, Y'),
            ]),
        ]);
    }

    /**
     * Submit a new rating and review for a product.
     * POST /store/products/{productId}/reviews
     * POST /api/v1/storefront/products/{productId}/reviews
     */
    public function store(Request $request, int|string $productId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        if (! ($company->enable_product_reviews ?? true)) {
            return response()->json([
                'success' => false,
                'message' => __('Ratings and reviews are currently disabled by this store.'),
            ], 403);
        }

        $product = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $productId)
            ->first();

        abort_if(! $product, 404, 'Product not found');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:3', 'max:3000'],
            'title' => ['nullable', 'string', 'max:200'],
            'customer_name' => ['nullable', 'string', 'max:150'],
        ]);

        $customer = $this->resolveCustomer($request, $company);
        $customerName = $customer?->name ?: ($validated['customer_name'] ?? 'Verified Customer');
        $customerEmail = $customer?->email ?? $request->input('customer_email');

        // Check if customer made a verified purchase of this product
        $isVerified = false;
        if ($customer) {
            $hasPurchased = Sale::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->where(function ($q) use ($product) {
                    $q->whereJsonContains('items', ['id' => (int) $product->id])
                      ->orWhere('items', 'like', '%"product_id":'.$product->id.'%')
                      ->orWhere('items', 'like', '%"id":'.$product->id.'%');
                })
                ->exists();

            if ($hasPurchased) {
                $isVerified = true;
            }
        }

        $requireApproval = (bool) ($company->require_review_approval ?? false);

        $review = ProductReview::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'customer_id' => $customer?->id,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'rating' => (int) $validated['rating'],
            'title' => $validated['title'] ?? null,
            'comment' => $validated['comment'],
            'is_approved' => ! $requireApproval,
            'is_verified_purchase' => $isVerified,
        ]);

        $message = $requireApproval
            ? __('Thank you! Your review has been submitted and is awaiting store approval.')
            : __('Thank you! Your review has been posted successfully.');

        return response()->json([
            'success' => true,
            'message' => $message,
            'review' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'customer_name' => $review->customer_name,
                'is_verified_purchase' => $review->is_verified_purchase,
                'is_approved' => $review->is_approved,
                'created_at' => $review->created_at->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Get reviews submitted by customer + items available for rating.
     * GET /store/customer/reviews
     * GET /api/v1/storefront/customer/reviews
     */
    public function customerReviews(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Authentication required.'),
            ], 401);
        }

        $reviews = ProductReview::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->with('product')
            ->recent()
            ->get();

        // Find products ordered by customer that can be rated
        $sales = Sale::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->recent()
            ->take(15)
            ->get();

        $reviewedProductIds = $reviews->pluck('product_id')->all();
        $unreviewedProducts = [];

        foreach ($sales as $sale) {
            $items = is_array($sale->items) ? $sale->items : (json_decode($sale->items, true) ?: []);
            foreach ($items as $item) {
                $pId = $item['id'] ?? $item['product_id'] ?? null;
                if ($pId && ! in_array((int) $pId, $reviewedProductIds, true) && ! isset($unreviewedProducts[$pId])) {
                    $unreviewedProducts[$pId] = [
                        'id' => $pId,
                        'name' => $item['name'] ?? 'Product #'.$pId,
                        'price' => (float) ($item['price'] ?? 0),
                        'order_number' => $sale->sale_number,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'reviews_enabled' => (bool) ($company->enable_product_reviews ?? true),
            'reviews' => $reviews->map(fn($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'product_name' => $r->product?->name ?? 'Product #'.$r->product_id,
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'comment' => $r->comment,
                'is_approved' => (bool) $r->is_approved,
                'created_at' => $r->created_at->format('M d, Y'),
            ]),
            'products_to_rate' => array_values($unreviewedProducts),
        ]);
    }
}
