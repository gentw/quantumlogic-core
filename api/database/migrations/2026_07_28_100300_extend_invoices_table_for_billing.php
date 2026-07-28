<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing invoices table for Billing & Payments.
     *
     * The plan-tier columns (subscription_id, package_id, trial fields, ...) are
     * deliberately kept — they belong to the retired module and its historical
     * rows. subscription_id and package_id merely become nullable so an invoice
     * can exist without a subscription cycle.
     *
     * DB::statement is used where the schema builder would need doctrine/dbal
     * (column modification), which is not installed.
     */
    public function up(): void
    {
        // Legacy NOT NULL columns an invoice can no longer depend on.
        DB::statement('ALTER TABLE invoices MODIFY subscription_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE invoices MODIFY package_id BIGINT UNSIGNED NULL');

        // status was ENUM(paid|unpaid|failed); the billing lifecycle needs
        // draft|sent|paid|cancelled alongside the legacy values on old rows.
        DB::statement("ALTER TABLE invoices MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('service_order_id')->nullable()->after('subscription_id')
                ->constrained()->nullOnDelete();
            // null type = legacy plan-tier invoice; new rows always set one.
            $table->string('type', 32)->nullable()->after('service_order_id')
                ->index(); // deposit|milestone|balance|one_off|recurring|credit_note
            $table->foreignId('parent_invoice_id')->nullable()->after('type')
                ->constrained('invoices')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->after('parent_invoice_id')
                ->constrained('users')->nullOnDelete();

            $table->decimal('subtotal_net', 10, 2)->default(0)->after('total');
            $table->decimal('discount_total', 10, 2)->default(0)->after('subtotal_net');
            $table->decimal('vat_total', 10, 2)->default(0)->after('discount_total');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('vat_total');
            $table->decimal('total_gross', 10, 2)->default(0)->after('vat_rate');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('total_gross');
            $table->decimal('amount_due', 10, 2)->default(0)->after('amount_paid');

            $table->timestamp('due_at')->nullable()->index()->after('issued_at');
            $table->timestamp('sent_at')->nullable()->after('due_at');
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            // Stamped at issue; a locked invoice is read-only (credit notes only).
            $table->timestamp('locked_at')->nullable()->after('cancelled_at');

            $table->string('reference')->nullable()->after('description');
            $table->text('terms')->nullable()->after('reference');
            $table->text('notes')->nullable()->after('terms');
            $table->boolean('reverse_charge')->default(false)->after('notes');

            $table->string('public_token', 64)->nullable()->unique()->after('reverse_charge');
            $table->timestamp('public_token_expires_at')->nullable()->after('public_token');

            // §132 BAO: 7-year retention — invoices are soft-deleted only.
            $table->softDeletes();

            $table->index('status');
            $table->index(['user_id', 'status'], 'invoices_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_user_status_index');
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('service_order_id');
            $table->dropConstrainedForeignId('parent_invoice_id');
            $table->dropConstrainedForeignId('account_manager_id');
            $table->dropColumn([
                'type',
                'subtotal_net', 'discount_total', 'vat_total', 'vat_rate',
                'total_gross', 'amount_paid', 'amount_due',
                'due_at', 'sent_at', 'cancelled_at', 'locked_at',
                'reference', 'terms', 'notes', 'reverse_charge',
                'public_token', 'public_token_expires_at',
                'deleted_at',
            ]);
        });

        // Restore the legacy status vocabulary. Fails if non-legacy statuses
        // (draft/sent/cancelled) exist — migrate that data away first.
        DB::statement("ALTER TABLE invoices MODIFY status ENUM('paid', 'unpaid', 'failed') NOT NULL DEFAULT 'unpaid'");
        DB::statement('ALTER TABLE invoices MODIFY package_id BIGINT UNSIGNED NOT NULL');

        // subscription_id deliberately stays nullable: rows with NULL already
        // exist in the wild (pre-dating this migration), so restoring NOT NULL
        // is a data-dependent operation this down() must not attempt.
    }
};
