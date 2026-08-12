<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's chosen language.
 *
 * Nullable on purpose: null means "nobody has chosen", so LocaleService is free
 * to keep guessing from the request. Once set it wins over geolocation.
 *
 * It has to live on the row rather than being derived per request, because the
 * mail that matters most — dunning at 3/7/14 days, recurring-charge failures —
 * is dispatched by the scheduler, where there is no request and no IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('locale', 2)->nullable()->index()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });
    }
};
