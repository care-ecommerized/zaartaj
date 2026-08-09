<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One optional product video, stored as disk/path the same way a mirrored image
 * is. Kept on the product row rather than in its own table because the admin
 * offers a single clip per product — a gallery of videos would need positions,
 * and nothing asks for that yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video_disk')->nullable()->after('total_inventory');
            $table->string('video_path')->nullable()->after('video_disk');
            $table->string('video_mime')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['video_disk', 'video_path', 'video_mime']);
        });
    }
};
