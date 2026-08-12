<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Login codes: who needs one, issuing it, and checking it.
 *
 * The generate-persist-mail block used to be copy-pasted at three points in
 * AuthenticationController, each with its own `rand()` call.
 */
class TwoFactorService
{
    /**
     * Whether this account is asked for a code at login.
     *
     * A stored preference always wins; the role default only decides what a new
     * account starts with (see config/two_factor.php).
     */
    public function isRequiredFor(User $user): bool
    {
        return (bool) $user->two_factor_enabled;
    }

    /** What a newly created account of this role starts with. */
    public function defaultForRole(?string $role): bool
    {
        return in_array($role, config('two_factor.default_on_roles', []), true);
    }

    /**
     * Generate a code, store it against the identifier, and mail it.
     *
     * `$identifier` is whatever the login form sent — an email for clients, or
     * the address kept in the `phone` column for staff — because that is the
     * value `verify-otp` will send back and the `otps` table is keyed on it.
     *
     * @return string the code, so callers can assert on it in tests
     */
    public function issueFor(User $user, string $identifier): string
    {
        $code = $this->generateCode();
        $expiresAt = Carbon::now()->addMinutes((int) config('two_factor.code_ttl_minutes', 10));

        Otp::updateOrCreate(
            ['phone' => $identifier],
            ['otp' => $code, 'expires_at' => $expiresAt],
        );

        Mail::to($user)->send(new OtpMail($code, $expiresAt->format('H:i')));

        return $code;
    }

    /**
     * Consume a code. Returns the matching row, or null when it is wrong or has
     * expired. Single-use: a valid code is deleted before this returns, so it
     * cannot be replayed.
     */
    public function consume(string $identifier, string $code): bool
    {
        $otp = Otp::where('phone', $identifier)
            ->where('otp', $code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->delete();

        return true;
    }

    /**
     * A six-digit code from a cryptographically secure source.
     *
     * `rand()` is a Mersenne Twister and its output is predictable from a
     * handful of observed values — which is a real problem for something whose
     * only job is to be unguessable.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
