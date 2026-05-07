<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->enum('status', ['active', 'canceled', 'pending', 'failed', 'trial'])->default('pending');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->enum('recurring_payment_method', ['cc', 'paypal', 'bank_transfer']);
            $table->string('cc_payment_id')->nullable();
            $table->string('paypal_payment_id')->nullable();
            $table->string('bank_transfer_payment_id')->nullable();
            $table->enum('last_payment_status', ['success', 'failed'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
