<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // What happened: placed, payment, status_changed, shipment, comment, note.
            $table->string('type')->index();
            $table->string('title');
            $table->text('body')->nullable();

            // Status transitions record where they came from and went to.
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();

            // Who did it: system (automatic), staff (an admin) or customer.
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
