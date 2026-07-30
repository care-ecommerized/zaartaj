<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('original_filename');
            $table->string('disk');
            $table->string('path');

            $table->string('status')->default('pending')->index();

            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('products_created')->default(0);
            $table->unsignedInteger('products_updated')->default(0);
            $table->unsignedInteger('images_queued')->default(0);
            $table->unsignedInteger('failures')->default(0);

            // Set when the file itself is unreadable, as opposed to individual bad rows.
            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        /*
         * One row per rejected CSV line. Without this an import that skips 12 of 300
         * products reports only a number, and nobody can find out which twelve.
         */
        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('line_number');
            $table->string('handle')->nullable();
            $table->text('message');
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['product_import_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
    }
};
