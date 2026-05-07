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
        Schema::create('notification_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('recipient_user_id')->constrained('users');
            $table->foreignId('alarm_alert_id')->nullable()->constrained('alarm_incomings');
            $table->foreignId('chat_id')->nullable();
            $table->string('type')->nullable();
            $table->string('message')->nullable();
            $table->integer('read')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_lists');
    }
};
