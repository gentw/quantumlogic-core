<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

/**
 * Logging out has to actually end the session server-side. Runs inside a
 * rolled-back transaction against the real schema, same as the billing tests.
 */
class LogoutTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Test',
            'surname' => 'Logout '.uniqid(),
            'email' => uniqid('logout-test-').'@example.test',
            'password' => 'secret-password',
            'role' => 'client',
        ]);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertStatus(401);
    }

    /**
     * The guard can authenticate a user that has no Passport token behind it.
     * That branch used to fall off the end of the method and return an empty
     * 200 body, leaving the SPA with nothing to read.
     */
    public function test_logout_answers_json_even_without_a_token_to_revoke(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_logout_revokes_the_access_token(): void
    {
        $user = $this->makeUser();
        $accessToken = $user->createToken('appToken');

        $this->withHeader('Authorization', 'Bearer '.$accessToken->accessToken)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertTrue(Token::find($accessToken->token->id)->revoked);
    }

    /**
     * Revoking only the access token left its refresh token live, so
     * /v1/token/refresh could mint a replacement for a session the user had
     * just ended.
     */
    public function test_logout_revokes_the_refresh_token_too(): void
    {
        $user = $this->makeUser();
        $accessToken = $user->createToken('appToken');

        $refreshToken = RefreshToken::create([
            'id' => hash('sha256', uniqid('refresh', true)),
            'access_token_id' => $accessToken->token->id,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$accessToken->accessToken)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertTrue(RefreshToken::find($refreshToken->id)->revoked);
    }
}
