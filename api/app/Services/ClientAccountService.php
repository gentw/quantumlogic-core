<?php

namespace App\Services;

use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The one place client accounts get found-or-created for billing flows.
 * Guest checkout routes through here — deliberately NOT a fifth signup
 * endpoint; the existing signup controllers should converge on this too.
 */
class ClientAccountService
{
    /**
     * Find the account for an email or create a provisional client.
     *
     * @return array{user: User, created: bool}
     */
    public function findOrCreateForBilling(
        string $email,
        string $name,
        ?string $company = null,
        ?string $vatId = null,
        ?string $countryCode = null,
        ?string $password = null,
    ): array {
        return DB::transaction(function () use ($email, $name, $company, $vatId, $countryCode, $password) {
            $existing = User::where('email', $email)->lockForUpdate()->first();

            if ($existing) {
                // Deliberately NOT applying $password: this path is reachable
                // from a public, unauthenticated form, so writing a password
                // here would be account takeover by anyone who knows the email.
                return ['user' => $existing, 'created' => false];
            }

            [$firstName, $surname] = array_pad(explode(' ', trim($name), 2), 2, '');

            $user = User::create([
                'name' => $firstName,
                'surname' => $surname,
                'email' => $email,
                // The buyer chooses this at checkout. Already hashed, so the
                // model's `hashed` cast passes it through untouched.
                'password' => Hash::make($password ?? Str::random(64)),
                'role' => 'client',
                'origin' => 'guest_checkout',
                'company_name' => $company,
                'vat_id' => $vatId,
                'country_code' => $countryCode,
                // Prefer the billing country they just typed over the request
                // IP behind it — a stated address beats a guessed one. Falls
                // back to whatever SetLocale resolved for this request.
                'locale' => app(LocaleService::class)->fromCountry($countryCode) ?? app()->getLocale(),
            ]);

            if ($password === null) {
                // No password chosen: store a raw random string rather than a
                // hash of one. It can never satisfy Hash::check, and its shape
                // is what activateAfterDeposit reads to decide whether a
                // set-password code is still needed. Written through the query
                // builder because the model's `hashed` cast would hash it.
                $raw = Str::random(64);
                User::whereKey($user->id)->update(['password' => $raw]);
                $user->setRawAttributes(['password' => $raw] + $user->getAttributes(), true);
            }

            return ['user' => $user, 'created' => true];
        });
    }

    /**
     * A guest's deposit settled: the account becomes real. Creates the
     * set-password code through the existing ResetCodePassword flow — no
     * second token mechanism. Mail lands with the phase-7 notifications.
     */
    public function activateAfterDeposit(User $user): ?string
    {
        if ($user->origin !== 'guest_checkout' || $user->email_verified_at !== null) {
            return null;
        }

        $user->forceFill(['email_verified_at' => now(), 'is_verified' => 1])->save();

        // Buyers who chose a password at checkout can already log in, and were
        // mailed their credentials then — issuing a reset code as well would
        // hand out a second, unnecessary way into the account. A raw random
        // string (the no-password fallback) is not a valid hash, which is how
        // the two cases are told apart without a schema change.
        if (Hash::info($user->password)['algoName'] !== 'unknown') {
            Log::info('Guest checkout account activated (password set at checkout)', ['user_id' => $user->id]);

            return null;
        }

        $code = (string) random_int(100000, 999999);

        ResetCodePassword::updateOrCreate(
            ['email' => $user->email],
            ['token' => $code],
        );

        app(BillingNotifier::class)->guestWelcome($user, setPasswordCode: $code);

        Log::info('Guest checkout account activated', ['user_id' => $user->id]);

        return $code;
    }
}
