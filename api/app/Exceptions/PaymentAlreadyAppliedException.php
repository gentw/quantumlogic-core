<?php

namespace App\Exceptions;

use App\Models\Invoice;
use Exception;

/**
 * A new payment (different idempotency key) arrived for an invoice that is
 * already settled. Replays of the SAME payment never throw this — they
 * short-circuit to a no-op.
 */
class PaymentAlreadyAppliedException extends Exception
{
    public static function for(Invoice $invoice): self
    {
        return new self(sprintf(
            'Invoice %s is already fully paid; refusing to apply another payment.',
            $invoice->invoice_number ?? $invoice->getKey()
        ));
    }
}
