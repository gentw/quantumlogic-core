<?php

namespace Tests\Feature\Billing;

use App\Models\Service;
use App\Models\ServiceCoupon;
use App\Services\ServiceCatalogueService;
use Illuminate\Support\Str;

class ServiceCouponTest extends BillingTestCase
{
    private function makeCoupon(array $attributes = []): ServiceCoupon
    {
        return ServiceCoupon::create($attributes + [
            'code' => uniqid('code-'),
            'discount_percent' => 25,
            'active' => true,
        ]);
    }

    public function test_a_code_scoped_to_a_service_resolves_for_that_service(): void
    {
        $service = Service::first();
        $coupon = $this->makeCoupon(['service_id' => $service->id]);

        $this->assertNotNull(app(ServiceCatalogueService::class)->couponFor($coupon->code, $service));
    }

    public function test_a_code_scoped_elsewhere_does_not_apply(): void
    {
        [$mine, $other] = Service::limit(2)->get()->all();
        $coupon = $this->makeCoupon(['service_id' => $other->id]);

        $this->assertNull(app(ServiceCatalogueService::class)->couponFor($coupon->code, $mine));
    }

    public function test_a_global_code_applies_to_any_service(): void
    {
        $coupon = $this->makeCoupon(['service_id' => null]);

        $this->assertNotNull(app(ServiceCatalogueService::class)->couponFor($coupon->code, Service::first()));
    }

    public function test_codes_are_matched_case_insensitively(): void
    {
        $service = Service::first();

        // Generated, not a literal: `code` is globally unique, so a fixed
        // string couples this test to every other one that names a code.
        $mixedCase = 'For-'.Str::random(8);
        $coupon = $this->makeCoupon(['code' => $mixedCase, 'service_id' => $service->id]);

        $this->assertSame(mb_strtolower($mixedCase), $coupon->code, 'codes normalise to lowercase');
        $this->assertNotNull(app(ServiceCatalogueService::class)->couponFor(mb_strtoupper($mixedCase), $service));
    }

    public function test_inactive_expired_and_exhausted_codes_are_all_refused(): void
    {
        $service = Service::first();
        $catalogue = app(ServiceCatalogueService::class);

        $inactive = $this->makeCoupon(['service_id' => $service->id, 'active' => false]);
        $expired = $this->makeCoupon(['service_id' => $service->id, 'expires_at' => now()->subDay()]);
        $exhausted = $this->makeCoupon(['service_id' => $service->id, 'max_uses' => 1, 'used_count' => 1]);

        $this->assertNull($catalogue->couponFor($inactive->code, $service));
        $this->assertNull($catalogue->couponFor($expired->code, $service));
        $this->assertNull($catalogue->couponFor($exhausted->code, $service));
        $this->assertNull($catalogue->couponFor('never-issued', $service), 'unknown codes fail the same way');
    }

    public function test_redemption_is_counted_and_exhausts_a_single_use_code(): void
    {
        $service = Service::first();
        $catalogue = app(ServiceCatalogueService::class);
        $coupon = $this->makeCoupon(['service_id' => $service->id, 'max_uses' => 1]);

        $catalogue->redeemCoupon($coupon);

        $this->assertSame(1, $coupon->refresh()->used_count);
        $this->assertNull($catalogue->couponFor($coupon->code, $service), 'a single-use code cannot be reused');
    }

    public function test_the_public_quote_applies_the_discount_and_reports_it(): void
    {
        $service = Service::publiclyOrderable()->first();
        $coupon = $this->makeCoupon(['service_id' => $service->id, 'discount_percent' => 25]);

        $response = $this->postJson('/api/v1/public/checkout/quote', [
            'services' => [['id' => $service->id, 'quantity' => 1]],
            'country_code' => 'AT',
            'coupon' => $coupon->code,
        ])->assertOk();

        $listNet = (float) $service->default_price_net;

        // Cast rather than assertJsonPath: JSON numbers decode as int when they
        // have no fraction, and assertJsonPath compares strictly.
        $response->assertJsonPath('coupon.code', $coupon->code);
        $this->assertSame(round($listNet, 2), (float) $response->json('subtotal_net'), 'subtotal is the list price');
        $this->assertSame(round($listNet * 0.25, 2), (float) $response->json('discount_total'));
        $this->assertSame(
            round($listNet * 0.75 * 1.2, 2),
            (float) $response->json('total_gross'),
            'gross is charged on the discounted net',
        );
    }

    public function test_an_unknown_quote_coupon_changes_nothing(): void
    {
        $service = Service::publiclyOrderable()->first();

        $this->postJson('/api/v1/public/checkout/quote', [
            'services' => [['id' => $service->id, 'quantity' => 1]],
            'country_code' => 'AT',
            'coupon' => 'not-a-real-code',
        ])->assertOk()
            ->assertJsonPath('coupon', null)
            ->assertJsonPath('discount_total', 0);
    }
}
