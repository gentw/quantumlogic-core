<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VAT determination needs to know where the customer is and whether the
     * sale is B2B: country decides domestic 20% vs EU reverse charge vs
     * non-EU zero-rating, and a UID (ATU/DE/... VAT id) is what makes an EU
     * sale B2B. Guest checkout collects these; admins can edit them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('postal_code');
            $table->string('company_name')->nullable()->after('country_code');
            $table->string('vat_id', 20)->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'company_name', 'vat_id']);
        });
    }
};
