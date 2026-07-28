<?php

namespace App\Services;

/**
 * The outcome of a VAT determination for one line: the rate to store on the
 * line and, when reverse-charged, the note the invoice must legally carry.
 */
final class TaxResolution
{
    public function __construct(
        public readonly float $rate,
        public readonly bool $reverseCharge,
        public readonly ?string $note,
    ) {}
}
