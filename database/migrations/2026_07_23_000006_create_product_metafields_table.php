<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The Shopify export carries ~60 taxonomy attribute columns (Color, Material,
         * Connectivity technology, ...) which are over 90% empty and change whenever
         * Shopify revises its taxonomy. Storing them as rows keeps the products table
         * narrow and lets new attributes arrive without a migration.
         */
        Schema::create('product_metafields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('namespace', 64)->default('shopify');
            $table->string('key', 128);
            $table->text('value');

            $table->timestamps();

            $table->unique(['product_id', 'namespace', 'key']);
        });

        /*
         * Values arrive semicolon-delimited ("bluetooth; wireless"). Exploding them
         * into their own table is what makes faceted filtering a plain indexed join
         * rather than a LIKE '%...%' scan.
         */
        Schema::create('product_metafield_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_metafield_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('key', 128);
            $table->string('value', 191);

            $table->index(['key', 'value']);
            $table->unique(['product_metafield_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_metafield_values');
        Schema::dropIfExists('product_metafields');
    }
};
