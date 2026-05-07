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
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnDelete();
            $table->enum('payment_method', ['cc', 'paypal', 'bank_transfer']);
            $table->string('payment_token'); // token from gateway
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
