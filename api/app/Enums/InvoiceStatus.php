<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    // A bank-transfer proof is uploaded and waiting for admin review; the
    // invoice is NOT paid until an admin accepts.
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Legacy plan-tier values still present on historical rows. */
    case Unpaid = 'unpaid';
    case Failed = 'failed';

    /** Whether the invoice can still be edited (numbers, lines, amounts). */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }

    /**
     * Whether money may still be taken against this invoice.
     *
     * AwaitingConfirmation counts: a pending bank-transfer proof settles
     * nothing, so the client must stay free to pay by card or PayPal instead.
     * Kept here rather than repeated per rail — the four call sites had already
     * drifted, leaving the card rails refusing invoices the bank rail accepted.
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::Sent, self::AwaitingConfirmation, self::Unpaid], true);
    }
}
