<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The existing `total` stays the grand total; these break it down.
            $table->decimal('subtotal', 10, 2)->default(0)->after('total');
            $table->decimal('shipping_total', 10, 2)->default(0)->after('subtotal');
            $table->decimal('discount_total', 10, 2)->default(0)->after('shipping_total');

            $table->string('payment_method')->default('cod')->after('discount_total');
            $table->string('payment_status')->default('unpaid')->index()->after('payment_method');

            // Steadfast prices by district; captured here so fulfilment has it.
            $table->string('customer_district')->nullable()->after('customer_address');

            $table->timestamp('placed_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal', 'shipping_total', 'discount_total',
                'payment_method', 'payment_status', 'customer_district', 'placed_at',
            ]);
        });
    }
};
