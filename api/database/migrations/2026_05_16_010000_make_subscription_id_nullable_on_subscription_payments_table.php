<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aligns the schema with the original create-table migration's intent
     * (it declared subscription_id as ->nullable() but the DB ended up NOT NULL).
     *
     * SubscriptionService::startTrial() and the rest of the trial / subscribe /
     * renewal paths INSERT a payment row first, then the subscription, then
     * link the two — that pattern requires this column to be nullable.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_payments MODIFY subscription_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscription_payments MODIFY subscription_id BIGINT UNSIGNED NOT NULL');
    }
};
