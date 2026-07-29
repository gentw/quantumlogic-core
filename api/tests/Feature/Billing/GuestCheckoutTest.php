<?php

namespace Tests\Feature\Billing;

use App\Models\ResetCodePassword;
use App\Models\User;
use App\Services\ClientAccountService;

class GuestCheckoutTest extends BillingTestCase
{
    public function test_a_new_email_creates_exactly_one_provisional_client(): void
    {
        $accounts = app(ClientAccountService::class);
        $email = uniqid('guest-').'@example.test';

        ['user' => $user, 'created' => $created] = $accounts->findOrCreateForBilling($email, 'Anna Muster', 'Muster GmbH', 'ATU12345678', 'AT');

        $this->assertTrue($created);
        $this->assertSame('client', $user->role);
        $this->assertSame('guest_checkout', $user->origin);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(1, User::where('email', $email)->count());
    }

    public function test_a_repeat_email_never_duplicates_the_account(): void
    {
        $accounts = app(ClientAccountService::class);
        $email = uniqid('guest-').'@example.test';

        $first = $accounts->findOrCreateForBilling($email, 'Anna Muster');
        $second = $accounts->findOrCreateForBilling($email, 'Someone Else');

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['user']->id, $second['user']->id);
        $this->assertSame(1, User::where('email', $email)->count());
    }

    public function test_activation_verifies_the_account_and_creates_the_set_password_code_once(): void
    {
        $accounts = app(ClientAccountService::class);
        ['user' => $user] = $accounts->findOrCreateForBilling(uniqid('guest-').'@example.test', 'Anna Muster');

        $code = $accounts->activateAfterDeposit($user);

        $this->assertNotNull($code);
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertTrue(ResetCodePassword::where('email', $user->email)->exists());

        $this->assertNull($accounts->activateAfterDeposit($user), 'activation must be idempotent');
    }

    public function test_activation_never_touches_non_guest_accounts(): void
    {
        $client = $this->makeClient(['email_verified_at' => null]);

        $this->assertNull(app(ClientAccountService::class)->activateAfterDeposit($client));
        $this->assertNull($client->refresh()->email_verified_at);
    }

    public function test_an_expired_public_token_is_indistinguishable_from_an_unknown_one(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $invoice->forceFill([
            'public_token' => str_repeat('a', 64),
            'public_token_expires_at' => now()->subDay(),
        ])->save();

        $this->getJson('/api/v1/public/invoices/'.str_repeat('a', 64))->assertStatus(404);
        $this->getJson('/api/v1/public/invoices/completely-unknown-token')->assertStatus(404);
    }

    public function test_the_public_payload_exposes_no_account_data(): void
    {
        $client = $this->makeClient();
        $invoice = $this->issueInvoice($this->makeOrder($client));

        $invoice->forceFill([
            'public_token' => str_repeat('b', 64),
            'public_token_expires_at' => now()->addDay(),
        ])->save();

        $payload = $this->getJson('/api/v1/public/invoices/'.str_repeat('b', 64))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('user', $payload);
        $this->assertArrayNotHasKey('billed_to', $payload);
        $this->assertStringNotContainsString($client->email, json_encode($payload));
    }
}
