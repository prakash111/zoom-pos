<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public online-catalog share links (mirrors legacy /c/{32-hex-id}).
        // Rendered by CatalogViewController with no auth required by design —
        // that's the whole point of the feature — inside a sandboxed iframe
        // with no allow-same-origin, so a shared link can never read cookies
        // or session tokens for this domain even though it's served from it.
        Schema::create('published_catalogs', function (Blueprint $table) {
            $table->string('id', 32)->primary(); // opaque hex id, used directly in the public URL
            $table->string('company_id');
            $table->string('title');
            $table->json('product_ids');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_catalogs');
    }
};
