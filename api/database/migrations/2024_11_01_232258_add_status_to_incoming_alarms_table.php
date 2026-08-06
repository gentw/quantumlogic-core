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
        Schema::table('alarm_incomings', function (Blueprint $table) {
            //
            // pending
            // no_response
            // security_dispatched
            // resolved
            // base_notified
            $table->string('status')->nullable()->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_alarms', function (Blueprint $table) {
            //
            $table->dropColumn('status');
        });
    }
};
