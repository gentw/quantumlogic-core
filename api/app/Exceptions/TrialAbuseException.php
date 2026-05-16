<?php

namespace App\Exceptions;

use RuntimeException;

class TrialAbuseException extends RuntimeException
{
    public function __construct(
        string $message = 'Trial already used.',
        public readonly string $reason = 'trial_blocked',
    ) {
        parent::__construct($message);
    }
}
