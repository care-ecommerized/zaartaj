<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // The Shopify handle doubles as our storefront slug and as the import key.
            $table->string('handle')->unique();
            $table->string('title');
            $table->longText('body_html')->nullable();

            $table->string('vendor')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->string('product_type')->nullable()->index();

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // Retained verbatim so a re-import can re-resolve the category if the tree changes.
            $table->string('shopify_category', 512)->nullable();

            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            /*
             * Denormalised from the variants on every write. The storefront sorts and
             * filters on price constantly, and doing it through a join to the cheapest
             * variant is the kind of query that gets slow quietly.
             */
            $table->decimal('min_price', 10, 2)->nullable()->index();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->unsignedInteger('total_inventory')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
