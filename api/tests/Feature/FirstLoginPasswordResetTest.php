<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ⚠️ CHARACTERISATION TEST — documents current behaviour, does not endorse it.
 *
 * AuthenticationController::store() contains a first-login branch that, for any
 * account with `first_time = 1`, overwrites the stored password with a hash of
 * the account's own phone number — and does so *before* the submitted password
 * is ever checked. The rewrite is triggered by an unauthenticated request that
 * only has to name the phone number, which is also the username.
 *
 * On the current database that is 5,151 of 5,164 client accounts.
 *
 * These tests exist so the behaviour is visible and so that whoever changes it
 * has to make a deliberate decision rather than discovering it by accident.
 * They are expected to fail once it is fixed — that failure is the point.
 *
 * Predates this branch; see AuthenticationController::store(), phone branch.
 */
class FirstLoginPasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeUntouchedAccount(): User
    {
        return User::create([
            'name' => 'Test',
            'surname' => 'FirstLogin '.uniqid(),
            'email' => uniqid('first-login-').'@example.test',
            'phone' => (string) random_int(100000000, 999999999),
            'password' => 'a-password-the-owner-chose',
            'role' => 'client',
            'first_time' => 1,
        ]);
    }

    public function test_a_failed_login_rewrites_the_password_to_the_phone_number(): void
    {
        $user = $this->makeUntouchedAccount();

        $this->postJson('/api/v1/login', [
            'phone' => $user->phone,
            'password' => 'not-the-password',
        ])->assertStatus(401);

        $user->refresh();

        // The owner's password is gone, replaced by their own phone number.
        $this->assertTrue(Hash::check($user->phone, $user->password));
        $this->assertFalse(Hash::check('a-password-the-owner-chose', $user->password));
        $this->assertSame(0, (int) $user->first_time);
    }

    public function test_the_rewritten_password_then_authenticates(): void
    {
        $user = $this->makeUntouchedAccount();

        // Step one: any request naming the phone number triggers the rewrite.
        $this->postJson('/api/v1/login', ['phone' => $user->phone, 'password' => 'anything'])
            ->assertStatus(401);

        // Step two: the phone number is now the password.
        $this->postJson('/api/v1/login', ['phone' => $user->phone, 'password' => $user->phone])
            ->assertOk();
    }
}
