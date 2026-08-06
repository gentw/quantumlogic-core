<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-service recurring revenue (hosting, SEO retainers). Replaces the
     * retired plan-tier Subscription as the owner of recurring billing.
     *
     * service_id is restrictOnDelete: a catalogue entry with live plans must
     * not be hard-deleted (deactivate it instead).
     */
    public function up(): void
    {
        Schema::create('recurring_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('interval', 16); // monthly|yearly
            $table->decimal('amount_net', 10, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('state', 16)->index(); // active|paused|past_due|cancelled
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_charge_at')->nullable()->index();
            $table->unsignedTinyInteger('failure_count')->default(0);
            $table->string('last_failure_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // The daily charge sweep filters on exactly this pair.
            $table->index(['state', 'next_charge_at'], 'recurring_plans_state_charge_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_plans');
    }
};
