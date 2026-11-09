<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The area inside the chosen city, captured with the rest of the address.
 *
 * Atomshop needs a city AND an area to raise an AtomPay order - the admin
 * console validates that the area belongs to the city - but the KYC profile
 * only carried the city, so whoever placed the order had to guess the area from
 * the free-text address. Asking the customer once, where they are already
 * typing their address, removes the guess.
 *
 * Nullable: every profile created before this migration has no area, and the
 * field stays optional so the form still submits without JavaScript (the area
 * picker is populated from the chosen city client-side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atompay_kyc_profiles', function (Blueprint $table) {
            // Matches areas.id - bigint unsigned. No FK: areas is Atomshop's
            // table and the rest of this schema does not constrain into it.
            $table->unsignedBigInteger('area_id')->nullable()->after('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('atompay_kyc_profiles', function (Blueprint $table) {
            $table->dropColumn('area_id');
        });
    }
};
