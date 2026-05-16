<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('state', 32)->nullable()->after('status')->index();
            $table->string('payment_method_token')->nullable()->after('bank_transfer_payment_id');
            $table->string('payment_method_brand', 32)->nullable()->after('payment_method_token');
            $table->timestamp('trial_started_at')->nullable()->after('trial_used');
            $table->timestamp('trial_used_at')->nullable()->after('trial_started_at')->index();
            $table->string('trial_ip', 45)->nullable()->after('trial_used_at');
            $table->string('trial_device_hash', 128)->nullable()->after('trial_ip');
            $table->timestamp('grace_period_ends_at')->nullable()->after('end_date');
            $table->unsignedTinyInteger('renewal_failure_count')->default(0)->after('last_payment_status');
            $table->timestamp('cancelled_at')->nullable()->after('renewal_failure_count');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('trial_ip', 'subscriptions_trial_ip_index');
            $table->index('trial_device_hash', 'subscriptions_trial_device_hash_index');
            $table->index(['user_id', 'state'], 'subscriptions_user_state_index');
        });

        // Backfill state from legacy status
        DB::table('subscriptions')->where('status', 'trial')->update(['state' => 'trial_active']);
        DB::table('subscriptions')->where('status', 'active')->update(['state' => 'active']);
        DB::table('subscriptions')->whereIn('status', ['canceled'])->update(['state' => 'cancelled']);
        DB::table('subscriptions')->whereIn('status', ['failed'])->update(['state' => 'past_due']);
        DB::table('subscriptions')->whereIn('status', ['pending'])->update(['state' => 'expired']);

        // Backfill trial_used_at for legacy trials (best-effort, uses start_date)
        DB::table('subscriptions')
            ->where('status', 'trial')
            ->whereNull('trial_used_at')
            ->update(['trial_used_at' => DB::raw('start_date'), 'trial_started_at' => DB::raw('start_date')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_trial_ip_index');
            $table->dropIndex('subscriptions_trial_device_hash_index');
            $table->dropIndex('subscriptions_user_state_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'state',
                'payment_method_token',
                'payment_method_brand',
                'trial_started_at',
                'trial_used_at',
                'trial_ip',
                'trial_device_hash',
                'grace_period_ends_at',
                'renewal_failure_count',
                'cancelled_at',
            ]);
        });
    }
};
