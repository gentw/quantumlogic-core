<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One money movement against an invoice. Generalises subscription_payments,
     * which is left untouched for the retired module.
     *
     * invoice_id and user_id are restrictOnDelete: payments are financial
     * records under 7-year retention (soft-deleted only, like invoices).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32)->index(); // stripe|paypal|bank_transfer|manual
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_customer_id')->nullable();
            // Guards double-application when a webhook and a browser redirect race.
            $table->string('idempotency_key')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 32)->index(); // pending|processing|awaiting_confirmation|succeeded|failed|refunded|partially_refunded
            $table->string('method_brand', 32)->nullable();
            $table->string('method_last4', 8)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'status'], 'payments_invoice_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
