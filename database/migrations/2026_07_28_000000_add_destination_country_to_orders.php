<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Aramex prices and routes by destination country, and chooses its
            // product group (domestic vs international) from it. Defaults to BD
            // until the storefront checkout collects a country for cross-border
            // orders.
            $table->string('customer_country', 2)->default('BD')->after('customer_district');
            $table->string('customer_postcode', 20)->nullable()->after('customer_country');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_country', 'customer_postcode']);
        });
    }
};
