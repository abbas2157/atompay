<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sign-up waiting for its one-time code. The customer gives ONE contact:
 * an email (code sent by email) or a mobile (code sent on WhatsApp). The
 * AtomShop `users` row is only created once that code is proven, so no
 * unverified account ever reaches AtomShop's table.
 *
 * Holds a name, contact and password hash, so rows are pruned
 * (PendingSignup::prunable) once they can no longer be completed.
 *
 * Every timestamp is nullable on purpose: MySQL/MariaDB without
 * explicit_defaults_for_timestamp give the first NOT NULL timestamp
 * "ON UPDATE CURRENT_TIMESTAMP".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_pending_signups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();          // the signup_id the client holds
            $table->string('name');
            $table->string('channel', 10);                // email | whatsapp
            $table->string('email')->nullable()->index(); // when signing up by email
            $table->string('phone', 20)->nullable()->index(); // 03XXXXXXXXX when signing up by mobile
            $table->string('password');                   // bcrypt hash, AtomShop-compatible

            $table->char('code_hash', 64)->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('sends')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable();     // users.id once created
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_pending_signups');
    }
};
