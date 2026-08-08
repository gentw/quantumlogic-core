<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discount codes for personalised order links (/order/{service}?coupon=...).
     *
     * service_id is nullable on purpose: a code scoped to one service is the
     * common case, null means it applies to anything orderable. The discount is
     * a percentage because service_order_items.discount_percent already exists
     * and the cents-based money math already handles it — no second discount
     * concept to keep consistent.
     */
    public function up(): void
    {
        Schema::create('service_coupons', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete would block deactivating a service; the coupon is
            // meaningless without its service, so it goes with it.
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('label')->nullable(); // who it was cut for, e.g. "Eros Sefa"
            $table->decimal('discount_percent', 5, 2);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'active'], 'service_coupons_service_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_coupons');
    }
};
