<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\BillingInvoiceService;
use App\Services\ServiceOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Billing tests run against the real MySQL schema inside a rolled-back
 * transaction — the migrations use MySQL-specific DDL, so sqlite is not an
 * option, and RefreshDatabase would wipe the dev database.
 */
abstract class BillingTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function makeClient(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test',
            'surname' => 'Client '.uniqid(),
            'email' => uniqid('billing-test-').'@example.test',
            'password' => 'secret-password',
            'role' => 'client',
            'country_code' => 'AT',
        ], $attributes));
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Test',
            'surname' => 'Admin '.uniqid(),
            'email' => uniqid('billing-admin-').'@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
        ]);
    }

    protected function makeOrder(User $client, float $unitPriceNet = 100.01, array $options = []): ServiceOrder
    {
        return app(ServiceOrderService::class)->create($client, [[
            'service_id' => Service::query()->where('billing_type', 'recurring')->first()?->id,
            'description' => 'Test line',
            'quantity' => 1,
            'unit_price_net' => $unitPriceNet,
        ]], $options + ['deposit_percent' => 50]);
    }

    protected function issueInvoice(ServiceOrder $order, InvoiceType $type = InvoiceType::OneOff, float $fraction = 1.0): Invoice
    {
        $invoices = app(BillingInvoiceService::class);

        return $invoices->issue($invoices->createDraftFromOrder($order, $type, $fraction));
    }
}
