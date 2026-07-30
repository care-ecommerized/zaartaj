<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            // percent | fixed. Percent is off the AED subtotal; fixed is a flat
            // amount defined in `currency` (null = the AED base).
            $table->string('type');
            $table->decimal('value', 10, 2);
            // Only meaningful for fixed coupons: the currency the amount is defined
            // in. Null means the base currency (AED). Percent coupons ignore it.
            $table->char('currency', 3)->nullable();
            // Minimum order subtotal (AED base) the code needs to be usable.
            $table->decimal('min_subtotal', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
