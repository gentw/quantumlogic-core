<?php

namespace Tests\Feature\Billing;

use App\Models\Service;
use App\Models\ServiceCoupon;
use Illuminate\Support\Str;

/**
 * The admin side of discount codes. Authenticates with actingAs on the `api`
 * guard rather than minting a Passport token — the middleware only asks
 * Auth::check() and the role, so a token adds nothing but setup cost.
 */
class AdminServiceCouponTest extends BillingTestCase
{
    public function test_an_admin_creates_a_code_and_gets_a_ready_made_order_link(): void
    {
        $service = Service::publiclyOrderable()->first();

        // Generated, not a literal: `code` is globally unique, so a fixed
        // string couples this test to every other one that names a code.
        $mixedCase = 'For-'.Str::random(8);

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/v1/admin/billing/services/{$service->id}/coupons", [
                'code' => $mixedCase,
                'label' => 'Eros Sefa — referral',
                'discount_percent' => 25,
            ])->assertCreated();

        $response->assertJsonPath('data.code', mb_strtolower($mixedCase));
        $this->assertSame(25.0, (float) $response->json('data.discount_percent'));
        $this->assertStringEndsWith(
            "/order/{$service->slug}?coupon=".mb_strtolower($mixedCase),
            $response->json('data.order_url'),
        );
    }

    public function test_a_duplicate_code_is_refused_whatever_its_case(): void
    {
        $service = Service::publiclyOrderable()->first();
        $taken = Str::lower(Str::random(12));

        ServiceCoupon::create([
            'service_id' => $service->id,
            'code' => $taken,
            'discount_percent' => 10,
            'active' => true,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/v1/admin/billing/services/{$service->id}/coupons", [
                'code' => mb_strtoupper($taken),
                'discount_percent' => 15,
            ])->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_deactivating_keeps_the_row_so_the_discount_stays_explainable(): void
    {
        $service = Service::first();
        $coupon = ServiceCoupon::create([
            'service_id' => $service->id,
            'code' => uniqid('code-'),
            'discount_percent' => 10,
            'active' => true,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson("/api/v1/admin/billing/coupons/{$coupon->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.redeemable', false);

        $this->assertNotNull($coupon->fresh(), 'the code is deactivated, never deleted');
    }

    public function test_a_client_cannot_reach_the_coupon_endpoints(): void
    {
        $service = Service::first();

        $this->actingAs($this->makeClient(), 'api')
            ->getJson("/api/v1/admin/billing/services/{$service->id}/coupons")
            ->assertStatus(403);
    }
}
