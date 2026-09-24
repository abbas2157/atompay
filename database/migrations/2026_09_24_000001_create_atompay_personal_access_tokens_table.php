<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the AtomPay mobile app (Sanctum's standard schema).
 *
 * AtomShop already has a `personal_access_tokens` table in this database,
 * with tokenable_type `App\Models\User` - the same class name AtomPay uses.
 * Sharing it would make every AtomShop app token valid here, so AtomPay
 * keeps its own table and App\Models\PersonalAccessToken points at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');                     // users.id (AtomShop)
            $table->text('name');                            // device name sent at sign-in
            $table->string('token', 64)->unique();           // sha256 of the secret
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_personal_access_tokens');
    }
};
