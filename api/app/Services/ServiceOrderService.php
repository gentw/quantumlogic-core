<?php

namespace App\Services;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Order creation, totals, deposit split and state transitions.
 *
 * All money math runs in integer cents (App\Support\Money); the deposit and
 * balance of an order always sum exactly to its total — the balance absorbs
 * the rounding remainder.
 */
class ServiceOrderService
{
    private const TRANSITIONS = [
        'submit' => [ServiceOrderStatus::Draft, ServiceOrderStatus::AwaitingPayment],
        'activate' => [ServiceOrderStatus::AwaitingPayment, ServiceOrderStatus::Active],
        'startDelivery' => [ServiceOrderStatus::Active, ServiceOrderStatus::InDelivery],
        'complete' => [ServiceOrderStatus::InDelivery, ServiceOrderStatus::Completed],
    ];

    public function __construct(
        private readonly TaxService $tax,
        private readonly ServiceCatalogueService $catalogue,
    ) {}

    /**
     * Create an order from line specs.
     *
     * Each line: ['service_id' => ?, 'description' => ?, 'quantity' => ?,
     * 'unit' => ?, 'unit_price_net' => ?, 'discount_percent' => ?,
     * 'vat_rate' => ?]. Prices and VAT default from the catalogue entry; the
     * customer's country/UID decide reverse charge for the whole order.
     */
    public function create(User $customer, array $lines, array $options = []): ServiceOrder
    {
        return DB::transaction(function () use ($customer, $lines, $options) {
            $order = $customer->serviceOrders()->create([
                'order_number' => 'pending',
                'status' => ServiceOrderStatus::Draft,
                'account_manager_id' => $options['account_manager_id'] ?? null,
                'currency' => 'EUR',
                'deposit_percent' => $options['deposit_percent'] ?? null,
                'notes' => $options['notes'] ?? null,
            ]);

            $order->update(['order_number' => sprintf('SO-%06d', $order->id)]);

            foreach (array_values($lines) as $i => $line) {
                $this->addLine($order, $line, $i);
            }

            return $this->recalculate($order);
        });
    }

    /** Recompute line and order totals from the stored lines. */
    public function recalculate(ServiceOrder $order): ServiceOrder
    {
        $subtotal = $discount = $vat = $gross = 0;

        foreach ($order->items()->get() as $item) {
            $undiscounted = (int) round(Money::toCents((float) $item->unit_price_net) * (float) $item->quantity);
            $net = (int) round($undiscounted * (1 - (float) $item->discount_percent / 100));
            $lineVat = (int) round($net * (float) $item->vat_rate / 100);

            $item->update([
                'line_total_net' => Money::toEuros($net),
                'line_total_gross' => Money::toEuros($net + $lineVat),
            ]);

            $subtotal += $undiscounted;
            $discount += $undiscounted - $net;
            $vat += $lineVat;
            $gross += $net + $lineVat;
        }

        $order->update([
            'subtotal_net' => Money::toEuros($subtotal),
            'discount_total' => Money::toEuros($discount),
            'vat_total' => Money::toEuros($vat),
            'total_gross' => Money::toEuros($gross),
        ]);

        return $order->refresh();
    }

    /**
     * The deposit/balance split in euros. Always sums exactly to total_gross;
     * the balance absorbs the rounding remainder.
     *
     * @return array{deposit: float, balance: float}
     */
    public function depositSplit(ServiceOrder $order): array
    {
        $percent = (float) ($order->deposit_percent ?? 50.0);
        [$deposit, $balance] = Money::split(Money::toCents((float) $order->total_gross), $percent / 100);

        return ['deposit' => Money::toEuros($deposit), 'balance' => Money::toEuros($balance)];
    }

    public function submit(ServiceOrder $order): ServiceOrder
    {
        return $this->transition($order, 'submit');
    }

    public function activate(ServiceOrder $order): ServiceOrder
    {
        $order = $this->transition($order, 'activate');
        $order->update(['started_at' => now()]);

        return $order->refresh();
    }

    public function startDelivery(ServiceOrder $order): ServiceOrder
    {
        return $this->transition($order, 'startDelivery');
    }

    public function complete(ServiceOrder $order): ServiceOrder
    {
        $order = $this->transition($order, 'complete');
        $order->update(['completed_at' => now()]);

        return $order->refresh();
    }

    /** Cancellation is allowed from any non-terminal status. */
    public function cancel(ServiceOrder $order): ServiceOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = ServiceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw InvalidOrderStateException::make($locked, 'cancel');
            }

            $locked->update(['status' => ServiceOrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $locked->refresh();
        });
    }

    private function addLine(ServiceOrder $order, array $line, int $sortOrder): void
    {
        $service = isset($line['service_id']) ? Service::find($line['service_id']) : null;

        $vatRate = $service
            ? $this->catalogue->vatRateFor($service, $line['vat_rate'] ?? null)
            : (float) ($line['vat_rate'] ?? config('billing.vat_rate'));

        $resolution = $this->tax->resolve(
            $order->user?->country_code,
            $order->user?->vat_id,
            $vatRate
        );

        $order->items()->create([
            'service_id' => $service?->id,
            'description' => $line['description'] ?? $service?->name ?? 'Service',
            'quantity' => (float) ($line['quantity'] ?? 1),
            'unit' => $line['unit'] ?? null,
            'unit_price_net' => $service
                ? $this->catalogue->priceFor($service, isset($line['unit_price_net']) ? (float) $line['unit_price_net'] : null)
                : (float) ($line['unit_price_net'] ?? 0),
            'discount_percent' => (float) ($line['discount_percent'] ?? 0),
            'vat_rate' => $resolution->rate,
            'line_total_net' => 0,
            'line_total_gross' => 0,
            'sort_order' => $sortOrder,
        ]);

        if ($resolution->reverseCharge && ! $order->reverse_charge) {
            $order->update(['reverse_charge' => true]);
        }
    }

    private function transition(ServiceOrder $order, string $action): ServiceOrder
    {
        return DB::transaction(function () use ($order, $action) {
            $locked = ServiceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            [$from, $to] = self::TRANSITIONS[$action];

            if ($locked->status !== $from) {
                throw InvalidOrderStateException::make($locked, $action);
            }

            $locked->update(['status' => $to]);

            return $locked->refresh();
        });
    }
}
