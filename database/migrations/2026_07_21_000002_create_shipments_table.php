<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier')->index();

            // Steadfast requires the invoice to be unique across the merchant
            // account, so it doubles as our idempotency key on retries.
            $table->string('invoice');
            $table->string('consignment_id')->nullable();
            $table->string('tracking_code')->nullable();

            $table->string('status')->default('draft')->index();
            $table->string('provider_status')->nullable();

            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('delivery_type')->default(0);
            $table->text('note')->nullable();

            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->json('last_response')->nullable();
            $table->timestamps();

            $table->unique(['courier', 'invoice']);
            $table->unique(['courier', 'consignment_id']);
            $table->index(['courier', 'tracking_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
