<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forgot-password requests: a one-time code sent by email or WhatsApp, then a
 * short-lived reset token once the code is proven.
 *
 * Not AtomShop's `password_reset_tokens`: that table serves AtomShop's own
 * flow. A row is written even when no account matched (user_id null), so a
 * request for an unknown email looks exactly like one for a real account.
 * Codes and tokens are stored only as hashes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_password_resets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();          // the request_id the client holds
            $table->foreignId('user_id')->nullable()->index(); // users.id (AtomShop); null = no match
            $table->string('channel', 10);                // email | whatsapp
            $table->string('destination', 120);           // masked, for display only
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            // Nullable on purpose: MySQL/MariaDB without explicit_defaults_for_timestamp
            // give the first NOT NULL timestamp "ON UPDATE CURRENT_TIMESTAMP", which
            // would push the expiry forward on every attempt. Always set on create.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->boolean('sent')->default(false);      // a code actually went out
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_password_resets');
    }
};
