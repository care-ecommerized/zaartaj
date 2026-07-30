<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            // The ISO 4217 code is the natural key everything else references, so
            // it doubles as the primary key rather than an autoincrement id.
            $table->char('code', 3)->primary();

            $table->string('name');
            $table->string('symbol');

            // How many minor-unit digits the currency prints. Most are 2 (fils,
            // cents); the Gulf trio KWD/BHD/OMR use 3.
            $table->unsignedTinyInteger('decimals')->default(2);

            /*
             * Units of THIS currency per 1 unit of the base (AED). e.g. BDT holds
             * 33 because 1 AED = 33 BDT. The base row itself carries 1. High
             * precision so thinly-valued currencies keep their significant digits.
             */
            $table->decimal('rate_to_base', 18, 8)->default(1);

            $table->boolean('is_active')->default(true);

            // Exactly one row is the base; conversions pivot through it.
            $table->boolean('is_base')->default(false);

            // Set by staff to pin a rate the live FX job must not overwrite.
            $table->boolean('manual_override')->default(false);

            $table->timestamp('rate_updated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
