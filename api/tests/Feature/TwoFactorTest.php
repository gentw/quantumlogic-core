<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Two-factor is a per-user setting: on by default for staff, off for clients,
 * and changeable by the account owner behind their password.
 */
class TwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeUser(string $role = 'client', array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test',
            'surname' => 'TwoFactor '.uniqid(),
            'email' => uniqid('2fa-test-').'@example.test',
            'phone' => (string) random_int(100000000, 999999999),
            'password' => 'secret-password',
            'role' => $role,
        ], $attributes));
    }

    public function test_a_new_client_starts_without_two_factor(): void
    {
        $this->assertFalse($this->makeUser('client')->two_factor_enabled);
    }

    public function test_new_staff_start_with_two_factor_on(): void
    {
        $this->assertTrue($this->makeUser('admin')->two_factor_enabled);
        $this->assertTrue($this->makeUser('agent')->two_factor_enabled);
    }

    public function test_an_explicit_value_beats_the_role_default(): void
    {
        $this->assertFalse($this->makeUser('admin', ['two_factor_enabled' => false])->two_factor_enabled);
    }

    /**
     * The whole point of the change: a client with two-factor off logs straight
     * in. Login used to always answer with a checkpoint redirect and never
     * minted a token at all on this path.
     */
    public function test_a_client_without_two_factor_gets_a_token_immediately(): void
    {
        $user = $this->makeUser('client', ['two_factor_enabled' => false]);

        $response = $this->postJson('/api/v1/login', [
            'phone' => $user->email,
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('token.token'));
        $this->assertNull($response->json('redirect'));
        Mail::assertNothingSent();
    }

    public function test_a_client_with_two_factor_on_is_sent_to_the_checkpoint(): void
    {
        $user = $this->makeUser('client', ['two_factor_enabled' => true]);

        $this->postJson('/api/v1/login', [
            'phone' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJson(['redirect' => 'checkpoint']);

        $this->assertDatabaseHas('otps', ['phone' => $user->email]);
        Mail::assertSent(\App\Mail\OtpMail::class);
    }

    public function test_bad_credentials_are_rejected_without_issuing_a_code(): void
    {
        $user = $this->makeUser('client', ['two_factor_enabled' => false]);

        $this->postJson('/api/v1/login', [
            'phone' => $user->email,
            'password' => 'not-the-password',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('otps', ['phone' => $user->email]);
    }

    /** An unknown address used to dereference null and return a 500. */
    public function test_an_unknown_email_is_a_401_not_a_server_error(): void
    {
        $this->postJson('/api/v1/login', [
            'phone' => 'nobody-'.uniqid().'@example.test',
            'password' => 'secret-password',
        ])->assertStatus(401);
    }

    public function test_an_unknown_phone_number_is_a_401_not_a_server_error(): void
    {
        $this->postJson('/api/v1/login', [
            'phone' => '999888777666',
            'password' => 'secret-password',
        ])->assertStatus(401);
    }

    public function test_a_code_is_single_use(): void
    {
        $user = $this->makeUser('client');
        $code = app(TwoFactorService::class)->issueFor($user, $user->email);

        $this->assertTrue(app(TwoFactorService::class)->consume($user->email, $code));
        $this->assertFalse(app(TwoFactorService::class)->consume($user->email, $code));
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->makeUser('client');
        $code = app(TwoFactorService::class)->issueFor($user, $user->email);

        Otp::where('phone', $user->email)->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse(app(TwoFactorService::class)->consume($user->email, $code));
    }

    /** rand() is predictable from a few observations; codes must not be. */
    public function test_codes_are_six_digits_and_vary(): void
    {
        $user = $this->makeUser('client');
        $service = app(TwoFactorService::class);

        $codes = collect(range(1, 20))->map(fn () => $service->issueFor($user, $user->email));

        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^\d{6}$/', $code));
        $this->assertGreaterThan(15, $codes->unique()->count());
    }

    public function test_reading_the_setting_reports_state_and_delivery_address(): void
    {
        $user = $this->makeUser('client');

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/user/two-factor')
            ->assertOk()
            ->assertJson([
                'enabled' => false,
                'default_for_role' => false,
                'delivery_email' => $user->email,
            ]);
    }

    public function test_turning_it_on_requires_the_current_password(): void
    {
        $user = $this->makeUser('client');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/user/two-factor', ['enabled' => true, 'current_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_turning_it_on_persists_with_the_right_password(): void
    {
        $user = $this->makeUser('client');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/user/two-factor', ['enabled' => true, 'current_password' => 'secret-password'])
            ->assertOk()
            ->assertJson(['success' => true, 'enabled' => true]);

        $this->assertTrue($user->fresh()->two_factor_enabled);
    }

    /** Turning it off from a stolen session is the case the password guards. */
    public function test_turning_it_off_also_requires_the_current_password(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/user/two-factor', ['enabled' => false, 'current_password' => 'wrong'])
            ->assertStatus(422);

        $this->assertTrue($user->fresh()->two_factor_enabled);
    }

    public function test_it_cannot_be_switched_on_without_an_address_to_mail(): void
    {
        $user = $this->makeUser('client', ['email' => null]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/user/two-factor', ['enabled' => true, 'current_password' => 'secret-password'])
            ->assertStatus(422);

        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_the_setting_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/user/two-factor')->assertStatus(401);
        $this->postJson('/api/v1/user/two-factor', ['enabled' => true, 'current_password' => 'x'])->assertStatus(401);
    }

    /**
     * Staff sign in through a different branch of store(): the lookup matches
     * the submitted email against the `phone` column, which is where admin and
     * agent addresses are kept. Four of the six real staff accounts use this
     * path, and it is the branch that previously minted a token with no code at
     * all, so both outcomes need covering.
     */
    public function test_staff_with_two_factor_on_are_sent_to_the_checkpoint(): void
    {
        $admin = $this->makeUser('admin', [
            'phone' => 'staff-'.uniqid().'@example.test',
            'email' => 'staff-inbox-'.uniqid().'@example.test',
        ]);

        $this->postJson('/api/v1/login', ['phone' => $admin->phone, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJson(['redirect' => 'checkpoint']);

        $this->assertDatabaseHas('otps', ['phone' => $admin->phone]);
        Mail::assertSent(\App\Mail\OtpMail::class);
    }

    /**
     * The code is keyed on what was typed at login, but the account is found by
     * the `email` column first — which for staff holds a different address than
     * the one they signed in with. Without the phone-column fallback this
     * returns 401 after a valid code.
     */
    public function test_staff_can_complete_the_checkpoint_and_get_a_token(): void
    {
        $admin = $this->makeUser('admin', [
            'phone' => 'staff-'.uniqid().'@example.test',
            'email' => 'staff-inbox-'.uniqid().'@example.test',
        ]);

        $code = app(TwoFactorService::class)->issueFor($admin, $admin->phone);

        $response = $this->postJson('/api/v1/verify-otp', ['phone' => $admin->phone, 'otp' => $code])
            ->assertOk();

        $this->assertNotEmpty($response->json('token.token'));
        $this->assertSame($admin->id, $response->json('user.id'));
    }

    public function test_staff_with_two_factor_off_sign_in_directly(): void
    {
        $agent = $this->makeUser('agent', [
            'phone' => 'staff-'.uniqid().'@example.test',
            'two_factor_enabled' => false,
        ]);

        $response = $this->postJson('/api/v1/login', ['phone' => $agent->phone, 'password' => 'secret-password'])
            ->assertOk();

        $this->assertNotEmpty($response->json('token.token'));
        Mail::assertNothingSent();
    }

    /** The other branch: staff whose phone column holds an actual number. */
    public function test_staff_logging_in_by_phone_number_also_get_a_code(): void
    {
        $agent = $this->makeUser('agent', ['phone' => (string) random_int(100000000, 999999999)]);

        // first_time is not fillable and defaults to 1; clear it so this test
        // exercises the two-factor branch rather than the first-login password
        // rewrite documented in FirstLoginPasswordResetTest.
        $agent->forceFill(['first_time' => 0])->saveQuietly();

        $this->postJson('/api/v1/login', ['phone' => $agent->phone, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJson(['redirect' => 'checkpoint']);

        $this->assertDatabaseHas('otps', ['phone' => $agent->phone]);
    }
}
