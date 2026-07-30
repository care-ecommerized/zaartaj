<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();

            // flat        — a fixed charge.
            // weight      — charge applies when the parcel weight (kg) falls in
            //               [min_threshold, max_threshold].
            // order_value — charge applies when the subtotal (AED) falls in
            //               [min_threshold, max_threshold].
            $table->string('method')->default('flat');

            // The charge, in the AED base currency.
            $table->decimal('amount', 10, 2)->default(0);

            // Bracket bounds: kilograms for weight rates, AED for order_value rates.
            $table->decimal('min_threshold', 10, 3)->nullable();
            $table->decimal('max_threshold', 10, 3)->nullable();

            // Subtotal (AED) at or above which this rate ships free.
            $table->decimal('free_over', 10, 2)->nullable();

            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
