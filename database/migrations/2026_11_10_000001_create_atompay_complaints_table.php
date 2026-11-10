<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer complaints raised in the app - the in-app grievance route SECP
 * Circular 15/2022 asks digital lenders for. Kept when an account is
 * deleted (like orders), because the regulator can ask for them; the
 * account they point at is anonymised by then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();            // users.id (AtomShop)
            $table->string('reference', 20)->nullable()->unique(); // CMP-1042, set right after insert
            $table->string('topic', 20);                      // see config('atompay.complaints.topics')
            $table->unsignedBigInteger('order_id')->nullable(); // orders.id (AtomShop), the customer's own
            $table->text('message');
            $table->string('status', 12)->default('open');    // open | answered | closed
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_complaints');
    }
};
