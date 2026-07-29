<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceType;
use App\Models\InvoiceSequence;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;

class InvoiceNumberingTest extends BillingTestCase
{
    public function test_numbers_are_gapless_and_unique(): void
    {
        $service = app(InvoiceNumberService::class);
        $numbers = [];

        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $service->allocate();
        }

        $this->assertCount(25, array_unique($numbers));

        $suffixes = array_map(fn ($n) => (int) substr($n, -4), $numbers);
        $this->assertSame(range($suffixes[0], $suffixes[0] + 24), $suffixes, 'numbers must be strictly sequential with no gaps');
    }

    public function test_allocation_takes_a_row_lock_other_connections_must_wait_for(): void
    {
        // Allocate inside this test's open transaction: the sequence row is
        // now locked. A second, independent connection must not be able to
        // grab it — NOWAIT surfaces the lock instead of blocking.
        app(InvoiceNumberService::class)->allocate();

        $sequence = InvoiceSequence::query()->first();

        config(['database.connections.mysql_probe' => config('database.connections.mysql')]);
        $probe = DB::connection('mysql_probe');

        try {
            $probe->transaction(function () use ($probe, $sequence) {
                $this->expectExceptionMessageMatches('/NOWAIT|lock/i');
                $probe->select(
                    'SELECT * FROM invoice_sequences WHERE id = ? FOR UPDATE NOWAIT',
                    [$sequence->id]
                );
            });
        } finally {
            $probe->disconnect();
        }
    }

    public function test_numbers_are_allocated_at_issue_not_at_draft_creation(): void
    {
        $client = $this->makeClient();
        $order = $this->makeOrder($client);

        $invoices = app(\App\Services\BillingInvoiceService::class);
        $draft = $invoices->createDraftFromOrder($order, InvoiceType::OneOff);

        $this->assertStringStartsWith('draft-', $draft->invoice_number, 'drafts must not burn a sequence number');

        $issued = $invoices->issue($draft);
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{4}-\d{4}$/', $issued->invoice_number);
    }
}
