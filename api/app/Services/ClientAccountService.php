<?php

namespace App\Services;

use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    ): array {
        return DB::transaction(function () use ($email, $name, $company, $vatId, $countryCode) {
            $existing = User::where('email', $email)->lockForUpdate()->first();

            if ($existing) {
                return ['user' => $existing, 'created' => false];
            }

            [$firstName, $surname] = array_pad(explode(' ', trim($name), 2), 2, '');

            $user = User::create([
                'name' => $firstName,
                'surname' => $surname,
                'email' => $email,
                // Provisional: no password until the deposit settles and the
                // set-password link is used. A random hash keeps login
                // impossible in the meantime.
                'password' => Str::random(64),
                'role' => 'client',
                'origin' => 'guest_checkout',
                'company_name' => $company,
                'vat_id' => $vatId,
                'country_code' => $countryCode,
            ]);

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

        $code = (string) random_int(100000, 999999);

        ResetCodePassword::updateOrCreate(
            ['email' => $user->email],
            ['token' => $code],
        );

        Log::info('Guest checkout account activated', ['user_id' => $user->id]);

        return $code;
    }
}
