<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();

            // A per-browser token (kept in the `checkout_token` cookie) that ties
            // a shopper's repeated debounced captures to one row.
            $table->uuid('token')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();

            // The shopper's presentment currency/locale at capture, so a recovery
            // reminder can be shown and priced the way they saw the bag.
            $table->char('currency', 3)->nullable();
            $table->char('locale', 5)->nullable();

            // Line snapshots: [{slug, size, quantity}, ...]. Prices are never
            // trusted from here — they are re-resolved from the catalogue on resume.
            $table->json('cart')->nullable();

            // Best-effort AED subtotal at capture time, for the admin list only.
            $table->decimal('subtotal', 10, 2)->nullable();

            // open → converted (order placed) | abandoned (swept) | recovered.
            $table->string('status')->default('open')->index();

            $table->foreignId('recovered_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
