<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved instruments — Stripe cards, PayPal billing agreements.
     * Provider tokens only; PAN/CVC are never stored.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16); // stripe|paypal
            $table->string('provider_token');
            $table->string('provider_customer_id')->nullable();
            $table->string('brand', 32)->nullable();
            $table->string('last4', 8)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider'], 'payment_methods_user_provider_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
