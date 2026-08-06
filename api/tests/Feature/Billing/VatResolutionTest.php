<?php

namespace Tests\Feature\Billing;

use App\Services\TaxService;

class VatResolutionTest extends BillingTestCase
{
    private function tax(): TaxService
    {
        config(['billing.seller_country' => 'AT', 'billing.vat_rate' => 20.0, 'billing.vies_live_check' => false]);

        return app(TaxService::class);
    }

    public function test_domestic_sales_carry_the_standard_rate(): void
    {
        $resolution = $this->tax()->resolve('AT', null);

        $this->assertSame(20.0, $resolution->rate);
        $this->assertFalse($resolution->reverseCharge);
        $this->assertNull($resolution->note);
    }

    public function test_domestic_b2b_is_not_reverse_charged(): void
    {
        $resolution = $this->tax()->resolve('AT', 'ATU12345678');

        $this->assertSame(20.0, $resolution->rate);
        $this->assertFalse($resolution->reverseCharge);
    }

    public function test_eu_b2b_with_valid_uid_is_reverse_charged_with_the_mandatory_note(): void
    {
        $resolution = $this->tax()->resolve('DE', 'DE123456789');

        $this->assertSame(0.0, $resolution->rate);
        $this->assertTrue($resolution->reverseCharge);
        $this->assertSame(TaxService::REVERSE_CHARGE_NOTE, $resolution->note);
    }

    public function test_eu_b2c_pays_the_seller_rate(): void
    {
        $resolution = $this->tax()->resolve('DE', null);

        $this->assertSame(20.0, $resolution->rate);
        $this->assertFalse($resolution->reverseCharge);
    }

    public function test_a_uid_from_the_wrong_country_does_not_trigger_reverse_charge(): void
    {
        $resolution = $this->tax()->resolve('DE', 'ATU12345678');

        $this->assertSame(20.0, $resolution->rate);
        $this->assertFalse($resolution->reverseCharge);
    }

    public function test_non_eu_customers_are_zero_rated(): void
    {
        $this->assertSame(0.0, $this->tax()->resolve('US', null)->rate);
        $this->assertSame(0.0, $this->tax()->resolve('CH', 'CHE123456')->rate);
        $this->assertFalse($this->tax()->resolve('US', null)->reverseCharge);
    }

    public function test_missing_country_falls_back_to_domestic(): void
    {
        $this->assertSame(20.0, $this->tax()->resolve(null, null)->rate);
    }

    public function test_line_rate_override_survives_domestic_resolution(): void
    {
        $this->assertSame(10.0, $this->tax()->resolve('AT', null, 10.0)->rate);
    }
}
