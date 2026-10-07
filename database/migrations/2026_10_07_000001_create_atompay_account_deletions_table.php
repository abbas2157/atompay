<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of self-service account deletions. Deliberately holds no
 * name, email, phone or CNIC: the account it points at is anonymised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_account_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();        // users.id (AtomShop), now anonymised
            $table->string('source', 10);                 // app | web
            $table->string('reason', 60);                 // "self-service deletion"
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_account_deletions');
    }
};
