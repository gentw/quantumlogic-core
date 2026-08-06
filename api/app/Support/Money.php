<?php

namespace App\Support;

/**
 * Billing math happens in integer cents — never float euros — so rounding is
 * explicit and splits can make the last position absorb the remainder.
 * Columns stay decimal(10,2); these helpers convert at the boundary.
 */
final class Money
{
    public static function toCents(float|string $euros): int
    {
        return (int) round((float) $euros * 100);
    }

    public static function toEuros(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Split $totalCents by $fraction, returning [part, rest] with the rest
     * absorbing the rounding remainder — the two always sum to the total.
     *
     * @return array{0: int, 1: int}
     */
    public static function split(int $totalCents, float $fraction): array
    {
        $part = (int) round($totalCents * $fraction);

        return [$part, $totalCents - $part];
    }
}
