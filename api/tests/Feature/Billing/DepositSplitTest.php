<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceType;
use App\Services\BillingInvoiceService;
use App\Services\ServiceOrderService;

class DepositSplitTest extends BillingTestCase
{
    public function test_deposit_and_balance_invoices_sum_exactly_to_the_order_total(): void
    {
        $client = $this->makeClient();
        // 100.01 net -> odd cents after VAT; 50% forces rounding both ways.
        $order = $this->makeOrder($client, 100.01);

        $invoices = app(BillingInvoiceService::class);
        $deposit = $invoices->createDraftFromOrder($order, InvoiceType::Deposit, 0.5);
        $balance = $invoices->createDraftFromOrder($order, InvoiceType::Balance);

        $this->assertSame(
            (float) $order->total_gross,
            round((float) $deposit->total_gross + (float) $balance->total_gross, 2),
            'gross must not leak a cent'
        );
        $this->assertSame(
            round((float) $order->total_gross - (float) $order->vat_total, 2),
            round((float) $deposit->subtotal_net + (float) $balance->subtotal_net, 2),
            'net must not leak a cent'
        );
    }

    public function test_odd_percent_splits_never_leak(): void
    {
        $client = $this->makeClient();
        $invoices = app(BillingInvoiceService::class);

        foreach ([[333.33, 30], [999.99, 40], [123.45, 33]] as [$price, $percent]) {
            $order = $this->makeOrder($client, $price, ['deposit_percent' => $percent]);
            $deposit = $invoices->createDraftFromOrder($order, InvoiceType::Deposit, $percent / 100);
            $balance = $invoices->createDraftFromOrder($order, InvoiceType::Balance);

            $this->assertSame(
                (float) $order->total_gross,
                round((float) $deposit->total_gross + (float) $balance->total_gross, 2),
                "leak at {$price} / {$percent}%"
            );
        }
    }

    public function test_the_split_preview_matches_the_total(): void
    {
        $client = $this->makeClient();
        $order = $this->makeOrder($client, 100.01);

        $split = app(ServiceOrderService::class)->depositSplit($order);

        $this->assertSame(
            (float) $order->total_gross,
            round($split['deposit'] + $split['balance'], 2)
        );
    }
}
