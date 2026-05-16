<?php

namespace App\Enums;

enum SubscriptionState: string
{
    case TrialActive = 'trial_active';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public static function entitled(): array
    {
        return [self::TrialActive, self::Active];
    }

    public function entitlesAccess(): bool
    {
        return in_array($this, self::entitled(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }
}
