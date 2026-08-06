<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue of what the agency sells (Website Build, SEO Retainer, Hosting, ...).
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 64)->nullable()->index();
            $table->string('billing_type', 32)->index(); // one_off|recurring|milestone
            $table->decimal('default_price_net', 10, 2)->default(0);
            $table->string('default_billing_interval', 16)->nullable(); // monthly|yearly|null
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->boolean('supports_deposit')->default(false);
            $table->decimal('default_deposit_percent', 5, 2)->nullable();
            $table->boolean('is_publicly_orderable')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
