<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Our own invoice number, sent to the gateway as the merchant reference.
            $table->string('reference')->unique();

            $table->string('gateway');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending')->index();

            // Gateway-side identifiers: the id we poll/execute against, and the
            // customer-facing transaction id returned once money has moved.
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable();

            $table->string('payer_reference')->nullable();
            $table->json('meta')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
