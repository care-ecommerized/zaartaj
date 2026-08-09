<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            // Optional sub-country scoping. When set, the zone only prices a
            // destination whose district is listed here — this is how Bangladesh
            // is split into inside-Dhaka and outside-Dhaka rates while both still
            // sit under the BD country. Null/empty = the zone prices the whole
            // country (the existing behaviour).
            $table->json('districts')->nullable()->after('countries');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('districts');
        });
    }
};
