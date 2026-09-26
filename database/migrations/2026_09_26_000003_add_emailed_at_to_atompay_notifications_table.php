<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Records that an inbox notification was also sent by email. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atompay_notifications', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('atompay_notifications', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
