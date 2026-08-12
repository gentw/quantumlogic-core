<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LocaleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Which language a visitor gets, and in what order the signals are trusted.
 */
class LocaleResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private function locales(): LocaleService
    {
        return app(LocaleService::class);
    }

    private function request(array $headers = []): Request
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return Request::create('/api/v1/locale', 'GET', [], [], [], $server);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test',
            'surname' => 'Locale '.uniqid(),
            'email' => uniqid('locale-test-').'@example.test',
            'password' => 'secret-password',
            'role' => 'client',
        ], $attributes));
    }

    public function test_austria_and_germany_resolve_to_german(): void
    {
        $this->assertSame('de', $this->locales()->fromCountry('AT'));
        $this->assertSame('de', $this->locales()->fromCountry('DE'));
    }

    public function test_albania_and_kosovo_resolve_to_albanian(): void
    {
        $this->assertSame('sq', $this->locales()->fromCountry('AL'));
        $this->assertSame('sq', $this->locales()->fromCountry('XK'));
    }

    public function test_an_unmapped_country_falls_through_to_the_fallback(): void
    {
        $this->assertNull($this->locales()->fromCountry('JP'));
        $this->assertSame('en', $this->locales()->resolve($this->request(['CF-IPCountry' => 'JP'])));
    }

    public function test_the_cloudflare_header_decides_the_language(): void
    {
        $this->assertSame('de', $this->locales()->resolve($this->request(['CF-IPCountry' => 'AT'])));
    }

    /** XX is Cloudflare's "unknown", not a country — it must not be looked up. */
    public function test_cloudflares_unknown_country_is_ignored(): void
    {
        $this->assertNull($this->locales()->countryFor($this->request(['CF-IPCountry' => 'XX'])));
    }

    public function test_accept_language_is_used_when_there_is_no_country(): void
    {
        $this->assertSame('de', $this->locales()->resolve($this->request(['Accept-Language' => 'de-AT,de;q=0.9,en;q=0.8'])));
    }

    /** Geolocation is a guess; a stated country outranks the browser's language. */
    public function test_country_outranks_accept_language(): void
    {
        $resolved = $this->locales()->resolve($this->request([
            'CF-IPCountry' => 'AL',
            'Accept-Language' => 'de-AT,de;q=0.9',
        ]));

        $this->assertSame('sq', $resolved);
    }

    public function test_a_saved_choice_outranks_everything_else(): void
    {
        $user = $this->makeUser(['locale' => 'sq']);

        $resolved = $this->locales()->resolve($this->request([
            'CF-IPCountry' => 'AT',
            'Accept-Language' => 'en-GB',
        ]), $user);

        $this->assertSame('sq', $resolved);
    }

    /** A locale dropped from config must not keep being served. */
    public function test_an_unsupported_saved_choice_is_ignored(): void
    {
        $user = $this->makeUser(['locale' => 'fr']);

        $this->assertNull($this->locales()->forUser($user));
        $this->assertSame('de', $this->locales()->resolve($this->request(['CF-IPCountry' => 'AT']), $user));
    }

    public function test_the_public_endpoint_reports_the_resolved_locale(): void
    {
        $this->withHeader('CF-IPCountry', 'AT')
            ->getJson('/api/v1/locale')
            ->assertOk()
            ->assertJson([
                'locale' => 'de',
                'supported' => ['en', 'de', 'sq'],
                'fallback' => 'en',
                'detected_country' => 'AT',
            ]);
    }

    public function test_saving_a_locale_requires_a_supported_value(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('/api/v1/user/locale', ['locale' => 'fr'])
            ->assertStatus(422);
    }

    public function test_saving_a_locale_persists_it(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/user/locale', ['locale' => 'de'])
            ->assertOk()
            ->assertJson(['success' => true, 'locale' => 'de']);

        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_saving_a_locale_requires_authentication(): void
    {
        $this->postJson('/api/v1/user/locale', ['locale' => 'de'])->assertStatus(401);
    }
}
