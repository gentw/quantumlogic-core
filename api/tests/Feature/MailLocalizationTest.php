<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Mail\SendCodeResetPassword;
use App\Mail\SendUserRegisterConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The emails have to come out in the recipient's language, and the templates
 * have to have no English left hardcoded in them.
 */
class MailLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        App::setLocale(config('app.locale'));
        parent::tearDown();
    }

    public function test_the_otp_email_renders_in_german(): void
    {
        App::setLocale('de');

        $rendered = (new OtpMail('123456', now()->addMinutes(10)->format('H:i')))->render();

        $this->assertStringContainsString('Bestätigen Sie Ihre Identität', $rendered);
        $this->assertStringContainsString('123456', $rendered);
        $this->assertStringNotContainsString('Confirm your identity', $rendered);
    }

    public function test_the_otp_email_renders_in_albanian(): void
    {
        App::setLocale('sq');

        $rendered = (new OtpMail('123456', now()->addMinutes(10)->format('H:i')))->render();

        $this->assertStringContainsString('Konfirmoni identitetin tuaj', $rendered);
    }

    public function test_the_reset_email_subject_is_translated(): void
    {
        App::setLocale('de');

        $this->assertSame('Passwort zurücksetzen', (new SendCodeResetPassword('token-123'))->build()->subject);
    }

    /**
     * The role reaches the Mailable as a key, not as a word, so it translates
     * with the rest of the sentence instead of staying Albanian everywhere.
     */
    public function test_the_new_user_role_is_translated_rather_than_interpolated_raw(): void
    {
        App::setLocale('de');

        $rendered = (new SendUserRegisterConfirmation('Ada', 'client', 'ada@example.test', 'secret'))->render();

        $this->assertStringContainsString('Kunde/Kundin', $rendered);
        $this->assertStringNotContainsString('klient', $rendered);
    }

    /** No stale hard-coded host in the welcome mail's call to action. */
    public function test_the_new_user_email_links_to_the_configured_frontend(): void
    {
        config(['app.frontend_url' => 'https://portal.example.test']);

        $rendered = (new SendUserRegisterConfirmation('Ada', 'client', 'ada@example.test', 'secret'))->render();

        $this->assertStringContainsString('https://portal.example.test', $rendered);
        $this->assertStringNotContainsString('bitemybytes', $rendered);
    }

    /**
     * A queued reminder runs long after the request that created it, so the
     * recipient's own locale has to come off the row.
     */
    public function test_a_user_declares_their_preferred_locale_for_mail(): void
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'Mail '.uniqid(),
            'email' => uniqid('mail-locale-').'@example.test',
            'password' => 'secret-password',
            'role' => 'client',
            'locale' => 'sq',
        ]);

        $this->assertSame('sq', $user->preferredLocale());
    }

    public function test_every_mail_key_exists_in_every_locale(): void
    {
        $reference = Lang::get('mail', [], 'en');

        foreach (['de', 'sq'] as $locale) {
            $translated = Lang::get('mail', [], $locale);

            foreach ($reference as $group => $entries) {
                foreach (array_keys($entries) as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $translated[$group] ?? [],
                        "mail.{$group}.{$key} is missing from the {$locale} translation",
                    );
                }
            }
        }
    }
}
