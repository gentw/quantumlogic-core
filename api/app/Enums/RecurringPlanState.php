<?php

namespace App\Enums;

enum RecurringPlanState: string
{
    case Active = 'active';
    case Paused = 'paused';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function isChargeable(): bool
    {
        return in_array($this, [self::Active, self::PastDue], true);
    }
}
