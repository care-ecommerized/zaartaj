<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable()->index();

            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();

            /*
             * Identity fallback. The export we import from has no SKUs at all, so the
             * option triple is the only stable way to recognise a variant on re-import.
             */
            $table->string('option_key', 64);

            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_per_item', 10, 2)->nullable();

            $table->unsignedInteger('grams')->default(0);
            $table->string('weight_unit', 8)->default('kg');

            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->string('tax_code')->nullable();

            $table->string('inventory_tracker')->nullable();
            $table->string('inventory_policy')->default('deny');
            $table->string('fulfillment_service')->default('manual');
            $table->integer('inventory_quantity')->default(0);

            $table->unsignedInteger('position')->default(1);

            $table->timestamps();

            $table->unique(['product_id', 'option_key']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
