<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this account needs an emailed code at login.
 *
 * Lives on `users` rather than `user_preferences` because the login controller
 * reads it before authentication completes, on every attempt. That side table
 * has a nullable FK and no guaranteed row per user, so reading it there would
 * mean a join plus a null-row fallback on the hot auth path — and this is a
 * security setting, not a notification opt-in like the rest of that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('locale');
        });

        // Existing staff start switched on, per config/two_factor.php. Clients
        // keep the column default of false, which is the point of the change.
        $roles = config('two_factor.default_on_roles', []);

        if ($roles !== []) {
            DB::table('users')->whereIn('role', $roles)->update(['two_factor_enabled' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_enabled');
        });
    }
};
