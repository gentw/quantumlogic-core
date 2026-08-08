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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->integer('email_news_and_updates')->nullable()->default(0);
            $table->integer('email_tips_and_tutorials')->nullable()->default(0);
            $table->integer('email_my_tickets')->nullable()->default(0);
            $table->integer('email_invoices')->nullable()->default(0);
            $table->integer('email_reminders')->nullable()->default(0);
            $table->integer('push_my_tickets')->nullable()->default(0);
            $table->integer('push_my_comments')->nullable()->default(0);
            $table->integer('push_reminders')->nullable()->default(0);
            $table->integer('push_invoices')->nullable()->default(0);
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
