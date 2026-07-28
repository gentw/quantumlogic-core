<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvalidInvoiceStateException;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lifecycle: draft -> issue (number + lock) -> paid, plus cancel
 * (drafts only) and credit notes (the only correction path once issued).
 * Every transition is written to the append-only invoice_activities trail.
 */
class BillingInvoiceService
{
    public function __construct(
        private readonly InvoiceNumberService $numbers,
    ) {}

    /**
     * Draft an invoice for a fraction of an order (deposit 0.5, one-off 1.0).
     * Lines are snapshotted from the order and scaled in cents per line.
     *
     * A Balance invoice ignores $fraction: each of its lines is the exact
     * remainder of the original line minus what the order's prior invoices
     * (drafts included, cancelled and credit notes excluded) already carry —
     * never re-scaled, so a deposit + balance pair sums to the order total
     * with no rounding leak, in net and gross alike.
     */
    public function createDraftFromOrder(ServiceOrder $order, InvoiceType $type, float $fraction = 1.0): Invoice
    {
        return DB::transaction(function () use ($order, $type, $fraction) {
            $invoice = Invoice::create([
                'user_id' => $order->user_id,
                'service_order_id' => $order->id,
                'account_manager_id' => $order->account_manager_id,
                'type' => $type,
                'status' => InvoiceStatus::Draft,
                'currency' => $order->currency,
                'reverse_charge' => $order->reverse_charge,
                'terms' => $type === InvoiceType::Deposit
                    ? sprintf('%s%% deposit on order %s', rtrim(rtrim(number_format($fraction * 100, 2), '0'), '.'), $order->order_number)
                    : null,
                // Legacy NOT NULL-ish columns from the plan-tier era.
                'invoice_number' => 'draft-'.uniqid(),
                'amount' => 0,
                'tax' => 0,
                'total' => 0,
                'issued_at' => now(),
            ]);

            $this->snapshotLines($invoice, $order, $fraction);
            $this->recalculate($invoice);
            $this->log($invoice, 'created', null, ['type' => $type->value, 'fraction' => $fraction]);

            return $invoice->refresh();
        });
    }

    /**
     * Issue a draft: allocate the gapless number, stamp dates, lock the row.
     * This is the only place numbers are allocated — abandoned drafts never
     * burn one.
     */
    public function issue(Invoice $invoice, ?User $actor = null, ?int $termsDays = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $termsDays) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvalidInvoiceStateException::make($locked, 'issue');
            }

            $days = $termsDays ?? (int) config('billing.payment_terms_days');

            $locked->forceFill([
                'invoice_number' => $this->numbers->allocate(),
                'status' => InvoiceStatus::Sent,
                'issued_at' => now(),
                'sent_at' => now(),
                'due_at' => now()->addDays($days),
                'amount_due' => $locked->total_gross,
                'locked_at' => now(),
            ])->save();

            $this->log($locked, 'issued', $actor, ['number' => $locked->invoice_number]);

            return $locked->refresh();
        });
    }

    /** Drafts can be cancelled; issued invoices need a credit note. */
    public function cancel(Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvalidInvoiceStateException::make($locked, 'cancel');
            }

            $locked->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->log($locked, 'cancelled', $actor);

            return $locked->refresh();
        });
    }

    /**
     * Full credit note against an issued invoice: a new, immediately-issued
     * invoice with negated lines and parent_invoice_id set. The parent is
     * marked cancelled (its content stays untouched and locked).
     */
    public function creditNote(Invoice $invoice, ?User $actor = null, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isLocked() || $locked->type === InvoiceType::CreditNote) {
                throw InvalidInvoiceStateException::make($locked, 'credit-note');
            }

            $note = Invoice::create([
                'user_id' => $locked->user_id,
                'service_order_id' => $locked->service_order_id,
                'account_manager_id' => $locked->account_manager_id,
                'type' => InvoiceType::CreditNote,
                'parent_invoice_id' => $locked->id,
                'status' => InvoiceStatus::Draft,
                'currency' => $locked->currency,
                'reverse_charge' => $locked->reverse_charge,
                'notes' => $reason,
                'invoice_number' => 'draft-'.uniqid(),
                'amount' => 0,
                'tax' => 0,
                'total' => 0,
                'issued_at' => now(),
            ]);

            foreach ($locked->items as $item) {
                $note->items()->create([
                    'service_id' => $item->service_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price_net' => -$item->unit_price_net,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'line_total_net' => -$item->line_total_net,
                    'line_total_gross' => -$item->line_total_gross,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $this->recalculate($note);
            $note = $this->issue($note, $actor);

            $locked->forceFill(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now(), 'amount_due' => 0])->save();
            $this->log($locked, 'credit_noted', $actor, ['credit_note' => $note->invoice_number, 'reason' => $reason]);

            return $note;
        });
    }

    /**
     * Re-derive amount_due from amount_paid and flip to paid when settled.
     * Caller must hold the row lock (PaymentService::apply does).
     */
    public function recomputeAmountDue(Invoice $invoice): Invoice
    {
        $due = max(0, Money::toCents((float) $invoice->total_gross) - Money::toCents((float) $invoice->amount_paid));

        $invoice->forceFill(['amount_due' => Money::toEuros($due)]);

        if ($due === 0 && $invoice->status === InvoiceStatus::Sent) {
            $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        }

        $invoice->save();

        return $invoice;
    }

    /** Append to the audit trail. Null actor = the system. */
    public function log(Invoice $invoice, string $event, ?User $actor = null, array $metadata = []): void
    {
        $invoice->activities()->create([
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'metadata' => $metadata ?: null,
        ]);
    }

    /** Copy order lines onto the invoice, scaled by $fraction in cents. */
    private function snapshotLines(Invoice $invoice, ServiceOrder $order, float $fraction): void
    {
        $remainder = $invoice->type === InvoiceType::Balance
            ? $this->alreadyInvoicedCents($order, $invoice)
            : null;

        foreach ($order->items()->get()->values() as $item) {
            $origNet = Money::toCents((float) $item->line_total_net);
            $origGross = Money::toCents((float) $item->line_total_gross);

            if ($remainder !== null) {
                $prior = $remainder[$item->sort_order] ?? ['net' => 0, 'gross' => 0];
                $netCents = $origNet - $prior['net'];
                $grossCents = $origGross - $prior['gross'];
            } else {
                $netCents = Money::split($origNet, $fraction)[0];
                $grossCents = (int) round($netCents * (1 + (float) $item->vat_rate / 100));
            }

            $invoice->items()->create([
                'service_id' => $item->service_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price_net' => $item->unit_price_net,
                'discount_percent' => $item->discount_percent,
                'vat_rate' => $item->vat_rate,
                'line_total_net' => Money::toEuros($netCents),
                'line_total_gross' => Money::toEuros($grossCents),
                'sort_order' => $item->sort_order,
            ]);
        }
    }

    /**
     * What the order's other invoices already carry, per line (keyed by
     * sort_order), in cents. Cancelled invoices and credit notes don't count.
     *
     * @return array<int, array{net: int, gross: int}>
     */
    private function alreadyInvoicedCents(ServiceOrder $order, Invoice $except): array
    {
        $totals = [];

        $priors = $order->invoices()
            ->whereKeyNot($except->id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', InvoiceType::CreditNote->value))
            ->with('items')
            ->get();

        foreach ($priors as $prior) {
            foreach ($prior->items as $item) {
                $totals[$item->sort_order]['net'] = ($totals[$item->sort_order]['net'] ?? 0)
                    + Money::toCents((float) $item->line_total_net);
                $totals[$item->sort_order]['gross'] = ($totals[$item->sort_order]['gross'] ?? 0)
                    + Money::toCents((float) $item->line_total_gross);
            }
        }

        return $totals;
    }

    /** Totals from the invoice's own (snapshotted) lines, in cents. */
    private function recalculate(Invoice $invoice): void
    {
        $net = $vat = $gross = 0;

        foreach ($invoice->items()->get() as $item) {
            $lineNet = Money::toCents((float) $item->line_total_net);
            $lineGross = Money::toCents((float) $item->line_total_gross);
            $net += $lineNet;
            $vat += $lineGross - $lineNet;
            $gross += $lineGross;
        }

        $invoice->forceFill([
            'subtotal_net' => Money::toEuros($net),
            'vat_total' => Money::toEuros($vat),
            'total_gross' => Money::toEuros($gross),
            'amount_due' => Money::toEuros($gross),
            // Legacy mirror columns so old admin screens keep rendering.
            'amount' => Money::toEuros($net),
            'tax' => Money::toEuros($vat),
            'total' => Money::toEuros($gross),
        ])->save();
    }
}
