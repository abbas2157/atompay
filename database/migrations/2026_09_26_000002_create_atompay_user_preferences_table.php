<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's AtomPay settings. AtomShop's `users` row is not ours to add
 * columns to, so they live here; no row = the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();       // users.id (AtomShop)
            $table->boolean('email_alerts')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_user_preferences');
    }
};
