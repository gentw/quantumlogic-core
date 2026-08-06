<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A specific customer's purchase; spans one or more invoices.
     * user_id is restrictOnDelete — orders are financial history and must not
     * vanish with an account row (deactivate users instead of deleting them).
     */
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 32)->index(); // draft|awaiting_payment|active|in_delivery|completed|cancelled
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal_net', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total_gross', 10, 2)->default(0);
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('reverse_charge')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'service_orders_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
