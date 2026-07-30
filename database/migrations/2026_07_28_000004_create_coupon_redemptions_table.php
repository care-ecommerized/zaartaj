<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            // Keep the redemption for reporting even if the order is later purged.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();
            // The discount applied, in the AED base currency.
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            // Per-user usage is counted by user id or email, so index both.
            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
