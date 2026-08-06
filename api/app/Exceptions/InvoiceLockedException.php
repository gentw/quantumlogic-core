<?php

namespace App\Exceptions;

use App\Models\Invoice;
use Exception;

/**
 * Thrown when something tries to modify the commercial content of an issued
 * invoice. Issued invoices are immutable (§11 UStG) — corrections happen via
 * credit note, never by editing.
 */
class InvoiceLockedException extends Exception
{
    public static function for(Invoice $invoice, array $fields): self
    {
        return new self(sprintf(
            'Invoice %s is locked; attempted to change: %s. Issue a credit note instead.',
            $invoice->invoice_number ?? $invoice->getKey(),
            implode(', ', $fields)
        ));
    }
}
