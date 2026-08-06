<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    /**
     * Terminal states short-circuit idempotent re-application — a webhook
     * replay against a payment in one of these must be a no-op.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Refunded], true);
    }
}
