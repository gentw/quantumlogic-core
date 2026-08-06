<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('package_id');
            }
            if (! Schema::hasColumn('invoices', 'subscribe_payment_id')) {
                $table->unsignedBigInteger('subscribe_payment_id')->nullable()->after('payment_method');
            }

            $table->timestamp('billing_period_start')->nullable()->after('subscribe_payment_id');
            $table->timestamp('billing_period_end')->nullable()->after('billing_period_start');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(
                ['subscription_id', 'billing_period_start'],
                'invoices_subscription_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_subscription_period_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_period_start', 'billing_period_end']);
        });
    }
};
