<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
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
}
