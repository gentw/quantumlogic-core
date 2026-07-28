<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs gapless sequential invoice numbering (QL-2026-0001). Allocation
     * happens inside a transaction with SELECT ... FOR UPDATE on this row —
     * never max(id)+1, which races and leaves gaps.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year'], 'invoice_sequences_prefix_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
