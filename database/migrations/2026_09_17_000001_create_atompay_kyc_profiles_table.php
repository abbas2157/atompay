<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 1 (Customer Verification) + Section 2 (One-Time Address
 * Verification) of the AtomPay KYC form. One row per customer; it is
 * done once and reused by every later credit assessment.
 *
 * Lives in the shared AtomShop database but is owned by AtomPay - hence
 * the prefix, and why this app tracks its migrations in `atompay_migrations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_kyc_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();          // users.id (AtomShop)

            // 1. Customer verification (filled by the customer)
            $table->string('full_name');
            $table->string('cnic', 15)->index();             // 13 digits, stored without dashes
            $table->string('mobile', 20);
            $table->date('date_of_birth');
            $table->text('residential_address');
            $table->foreignId('city_id')->nullable();        // cities.id (AtomShop)
            $table->string('cnic_front_path')->nullable();   // private disk
            $table->string('cnic_back_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->boolean('face_verified')->default(false);
            $table->timestamp('submitted_at')->nullable();

            // 2. One-time address verification (recorded by staff)
            $table->boolean('address_verified')->default(false);
            $table->string('verification_form_path')->nullable(); // signed form scan
            $table->foreignId('verified_by')->nullable();    // users.id of the field agent
            $table->date('verified_at')->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->text('verification_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_kyc_profiles');
    }
};
