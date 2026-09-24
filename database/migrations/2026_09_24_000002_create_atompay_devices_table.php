<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones registered for AtomPay push notifications (FCM tokens).
 *
 * Not AtomShop's `fcm_tokens`: that table belongs to the shop's own app, and
 * a push meant for the AtomPay app would land in the wrong app. Each device
 * is tied to the sign-in (access token) that registered it, so signing out
 * of the app also stops its pushes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();                    // users.id (AtomShop)
            $table->foreignId('access_token_id')->nullable()->index(); // atompay_personal_access_tokens.id
            $table->string('fcm_token', 512);
            $table->char('fcm_token_hash', 64)->unique();             // sha256 - a 512-char column can't be uniquely indexed
            $table->string('platform', 10);                           // android | ios
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_devices');
    }
};
