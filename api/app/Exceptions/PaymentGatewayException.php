<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * A payment provider (Stripe, PayPal) failed or answered nonsense. The
 * message is safe for clients; provider detail goes to the log via the
 * previous exception.
 */
class PaymentGatewayException extends Exception
{
    public static function provider(string $provider, string $message, ?Throwable $previous = null): self
    {
        return new self("{$provider}: {$message}", 0, $previous);
    }
}
