<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who looked at a customer's identity: every time staff open a CNIC scan, a
 * selfie, a signed verification form, or the review page that shows the CNIC
 * number. A customer viewing their own documents is not recorded.
 *
 * Append-only and kept when the customer's account is deleted: it records
 * what staff did, and holds no customer data beyond the account id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_kyc_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('viewer_id')->index();        // users.id (AtomShop) - who looked
            $table->unsignedBigInteger('subject_user_id')->index();  // users.id (AtomShop) - whose identity
            $table->unsignedBigInteger('kyc_profile_id')->nullable(); // no FK: the profile goes on account deletion
            $table->string('item', 20);                              // cnic-front | cnic-back | selfie | form | review
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_kyc_access_logs');
    }
};
