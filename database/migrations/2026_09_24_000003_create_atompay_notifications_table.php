<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's in-app notification inbox, and the record that a push was
 * sent. `dedupe_key` makes the notification sweep idempotent: the same
 * reminder or decision can only ever be announced once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');                  // users.id (AtomShop)
            $table->string('type', 40);                    // instalment_due, instalment_overdue, limit_decided, kyc_verified, kyc_rejected
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();              // deep-link payload for the app
            $table->string('dedupe_key', 120)->unique();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_notifications');
    }
};
