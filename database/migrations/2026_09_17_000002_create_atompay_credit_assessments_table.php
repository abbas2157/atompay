<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections 3-5 of the AtomPay form: Income & Financial Profile, Risk
 * Assessment and the Limit Decision. A customer may have several rows
 * over time (re-assessments); the latest decided one is the live limit.
 * Figures are frozen here so a later rule change never silently moves
 * an approved limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atompay_credit_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();          // users.id (AtomShop)

            // 3. Income & financial profile (customer)
            $table->string('employment_status', 30);
            $table->string('employer_name')->nullable();
            $table->string('income_source', 30);
            $table->unsignedInteger('monthly_income');
            $table->unsignedInteger('existing_instalments')->default(0); // other lenders, per month
            $table->unsignedInteger('monthly_expenses')->default(0);
            $table->integer('disposable_income');            // income - instalments - expenses

            // 4. Risk assessment (system-provisional, staff-confirmed)
            $table->string('credit_history', 20)->nullable();   // staff: none|good|fair|poor
            $table->string('payment_history', 20);              // from order_instalments
            $table->unsignedInteger('existing_obligations');    // unpaid AtomShop instalments
            $table->unsignedTinyInteger('risk_score');          // 0-100, higher is safer
            $table->string('risk_category', 10);                // low|medium|high

            // 5. Limit decision
            $table->unsignedInteger('approved_limit');
            $table->unsignedInteger('max_instalment');
            $table->unsignedTinyInteger('approved_tenure')->nullable(); // months
            $table->string('status', 20)->default('pending')->index(); // pending|approved|conditional|rejected
            $table->foreignId('decided_by')->nullable();     // users.id of the reviewer
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atompay_credit_assessments');
    }
};
