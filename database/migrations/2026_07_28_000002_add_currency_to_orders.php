<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Money columns stay in the base currency (AED). These record what the
            // customer was *shown and charged* — the presentment currency, the base
            // it was derived from, and the rate frozen at order time so a later FX
            // move never rewrites history. `locale` is reserved for localized emails.
            $table->char('currency', 3)->default('AED')->after('total');
            $table->char('base_currency', 3)->default('AED')->after('currency');
            $table->decimal('fx_rate', 18, 8)->default(1)->after('base_currency');
            $table->char('locale', 5)->nullable()->after('fx_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'base_currency', 'fx_rate', 'locale']);
        });
    }
};
