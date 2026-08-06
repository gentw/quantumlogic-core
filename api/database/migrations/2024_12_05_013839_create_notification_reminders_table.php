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
        Schema::create('notification_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Creates a foreign key constraint
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete(); // Assumes 'users' table
            $table->string('subject');
            $table->enum('type', ['pagese', 'tikete', 'sherbim']); // Adjust options as per your use case
            $table->enum('priority', ['ulet', 'mesem', 'larte'])->default('mesem'); // Add default if applicable
            $table->enum('delivery_schedule', ['menjehere', 'me_vone'])->default('menjehere');
            $table->dateTime('execute_time')->nullable(); // Added execute_time with datetime picker compatibility
            $table->boolean('system')->default(false); // Default value for clarity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_reminders');
    }
};
