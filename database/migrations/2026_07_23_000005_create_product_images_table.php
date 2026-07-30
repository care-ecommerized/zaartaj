<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->text('src');
            // Shopify CDN URLs are far past the index length, so we key on a digest.
            $table->char('src_hash', 40);

            $table->unsignedInteger('position')->default(1);
            $table->string('alt')->nullable();

            /*
             * Populated by MirrorProductImage once the remote file has been copied
             * locally. Until then the storefront falls back to `src`.
             */
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->unsignedTinyInteger('mirror_attempts')->default(0);
            $table->text('mirror_error')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'src_hash']);
            $table->index(['product_id', 'position']);
            $table->index('mirrored_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
