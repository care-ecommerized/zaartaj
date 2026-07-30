<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            /*
             * Full ancestry as it arrived, e.g. "Electronics > Audio > Speakers".
             * Shopify exports the taxonomy as a single string, so keeping it lets
             * the importer match an existing branch without walking the tree.
             *
             * Uniqueness is enforced on a digest rather than the text: a 512-char
             * utf8mb4 unique index is 2048 bytes, which InnoDB accepts only in
             * DYNAMIC row format, and MariaDB installs still default to COMPACT.
             */
            $table->string('path', 512)->nullable();
            $table->char('path_hash', 40)->nullable()->unique();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true)->index();

            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
