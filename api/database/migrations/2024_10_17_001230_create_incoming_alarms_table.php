<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('alarm_incomings', function (Blueprint $table) {
            $table->id();
            $table->string('signal_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('signal_string')->nullable();
            $table->string('alarm_description')->nullable();
            $table->string('acc_name')->nullable();
            $table->string('account_number')->nullable();
            $table->boolean('is_triggered')->default(0);
            $table->boolean('is_closed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_alarms');
    }
};
