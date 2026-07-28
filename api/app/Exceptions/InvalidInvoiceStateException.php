<?php

namespace App\Exceptions;

use App\Models\Invoice;
use Exception;

/** A lifecycle action was attempted on an invoice whose status forbids it. */
class InvalidInvoiceStateException extends Exception
{
    public static function make(Invoice $invoice, string $action): self
    {
        return new self(sprintf(
            'Cannot %s invoice %s in status "%s".',
            $action,
            $invoice->invoice_number ?? $invoice->getKey(),
            $invoice->status?->value ?? 'unknown'
        ));
    }
}
