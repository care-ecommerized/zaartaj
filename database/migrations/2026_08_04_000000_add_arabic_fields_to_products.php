<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Arabic siblings for the storefront-facing product copy. Kept as separate
 * nullable columns rather than a JSON translations blob so the existing English
 * data and the CSV importer keep working untouched; an Arabic visitor sees these
 * when filled and falls back to the English column when they are not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->longText('body_html_ar')->nullable()->after('body_html');
            $table->string('seo_title_ar')->nullable()->after('seo_title');
            $table->text('seo_description_ar')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'body_html_ar', 'seo_title_ar', 'seo_description_ar']);
        });
    }
};
