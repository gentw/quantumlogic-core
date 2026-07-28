<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VAT determination for an Austrian seller (config: billing.seller_country).
 *
 * - Domestic (AT): standard rate — per service/line, default 20%
 * - EU B2B (valid UID, other member state): 0%, reverse charge, mandatory note
 * - EU B2C: seller-country rate (general B2B/B2C place-of-supply rules)
 * - Non-EU: 0% (export of services)
 *
 * The resolved rate is stored on the invoice line at issue time; historical
 * invoices are never recomputed from config. Wording of the reverse-charge
 * note is pending accountant sign-off — see the feature spec's open items.
 */
class TaxService
{
    public const REVERSE_CHARGE_NOTE = 'Reverse charge — Steuerschuldnerschaft des Leistungsempfängers (Art. 196 MwStSystRL)';

    /** EU member states, ISO 3166-1 alpha-2 (2026). */
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Resolve the VAT treatment for one line.
     *
     * @param  string|null  $countryCode  customer country, ISO alpha-2; null falls back to domestic
     * @param  string|null  $vatId  customer UID; a plausible one makes an EU sale B2B
     * @param  float|null  $lineRate  the service/line rate; null falls back to config
     */
    public function resolve(?string $countryCode, ?string $vatId, ?float $lineRate = null): TaxResolution
    {
        $seller = strtoupper((string) config('billing.seller_country'));
        $country = strtoupper(trim((string) $countryCode)) ?: $seller;
        $standardRate = $lineRate ?? (float) config('billing.vat_rate');

        if ($country === $seller) {
            return new TaxResolution($standardRate, false, null);
        }

        if (! in_array($country, self::EU_COUNTRIES, true)) {
            // Export of services — out of scope of EU VAT.
            return new TaxResolution(0.0, false, null);
        }

        if ($this->isUsableVatId($vatId, $country)) {
            return new TaxResolution(0.0, true, self::REVERSE_CHARGE_NOTE);
        }

        // EU B2C: taxed at the seller's rate.
        return new TaxResolution($standardRate, false, null);
    }

    /**
     * Whether the UID makes this sale B2B. Format check always applies; the
     * VIES lookup only runs when billing.vies_live_check is on, and fails
     * open on service errors (VIES is regularly unavailable) — the UID was
     * admin-entered or format-checked either way, and the invoice records
     * what was decided.
     */
    public function isUsableVatId(?string $vatId, string $countryCode): bool
    {
        $vatId = strtoupper(preg_replace('/\s+/', '', (string) $vatId));

        if ($vatId === '' || ! str_starts_with($vatId, $countryCode)) {
            return false;
        }

        if (! preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vatId)) {
            return false;
        }

        if (! config('billing.vies_live_check')) {
            return true;
        }

        return $this->checkVies($countryCode, substr($vatId, 2));
    }

    private function checkVies(string $countryCode, string $number): bool
    {
        try {
            $response = Http::timeout(5)->get(
                "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$countryCode}/vat/{$number}"
            );

            if ($response->successful()) {
                return (bool) $response->json('isValid', false);
            }
        } catch (\Throwable $e) {
            Log::error('VIES lookup failed', [
                'country' => $countryCode,
                'error' => $e->getMessage(),
            ]);
        }

        // Fail open: an unreachable VIES must not block invoicing.
        return true;
    }
}
