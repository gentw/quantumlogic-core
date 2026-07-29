<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where an account came from (guest_checkout, admin, self_signup).
     * Guest-checkout accounts are provisional until their deposit settles.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('origin', 32)->nullable()->after('role')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
