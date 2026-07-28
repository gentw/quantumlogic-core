<?php

namespace App\Exceptions;

use App\Models\ServiceOrder;
use Exception;

/** A transition was attempted on a service order whose status forbids it. */
class InvalidOrderStateException extends Exception
{
    public static function make(ServiceOrder $order, string $action): self
    {
        return new self(sprintf(
            'Cannot %s order %s in status "%s".',
            $action,
            $order->order_number ?? $order->getKey(),
            $order->status?->value ?? 'unknown'
        ));
    }
}
