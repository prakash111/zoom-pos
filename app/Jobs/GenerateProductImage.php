<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\AiImageGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateProductImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $productId, public string $companyId) {}

    public function handle(AiImageGeneratorService $generator): void
    {
        $product = Product::withoutGlobalScopes()->where('company_id', $this->companyId)->find($this->productId);
        if (! $product || filled($product->image_url)) {
            return;
        }

        $url = $generator->generateProductImage($product->name, $product->category_name, null, $this->companyId);
        if ($url) {
            $product->update(['image_url' => $url]);
        }
    }
}
