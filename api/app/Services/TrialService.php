<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Exceptions\TrialAbuseException;
use App\Models\Subscription;
use App\Models\User;

/**
 * Trial-abuse rules. The subscription record is the source of truth — we
 * never count an unpaid trial invoice as evidence (the old PreventTrialAbuse
 * middleware did, and silently failed because trial invoices were created as
 * `unpaid`).
 *
 * A user is blocked from getting a new trial if any of these signals match a
 * previous trial:
 *   - same user_id has ever had a trial (lifetime, not current state)
 *   - same trial_ip
 *   - same trial_device_hash
 *   - another user with the same email already used a trial
 */
class TrialService
{
    public function assertEligible(
        User $user,
        ?string $ip,
        ?string $deviceHash,
    ): void {
        if (! $deviceHash) {
            throw new TrialAbuseException('Invalid device fingerprint.', 'fingerprint_missing');
        }

        if ($this->userHasUsedTrial($user)) {
            throw new TrialAbuseException('Trial already used by this account.', 'user_used');
        }

        if ($this->emailHasUsedTrial($user)) {
            throw new TrialAbuseException('Trial already used by this email.', 'email_used');
        }

        if ($ip && $this->ipHasUsedTrial($ip)) {
            throw new TrialAbuseException('Trial already used from this network.', 'ip_used');
        }

        if ($this->deviceHasUsedTrial($deviceHash)) {
            throw new TrialAbuseException('Trial already used from this device.', 'device_used');
        }
    }

    private function userHasUsedTrial(User $user): bool
    {
        return Subscription::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('trial_used_at')
                    ->orWhere('trial_used', true)
                    ->orWhere('state', SubscriptionState::TrialActive->value);
            })
            ->exists();
    }

    private function emailHasUsedTrial(User $user): bool
    {
        if (empty($user->email)) {
            return false;
        }

        return Subscription::query()
            ->whereNotNull('trial_used_at')
            ->whereHas('user', function ($q) use ($user) {
                $q->where('email', $user->email)->where('id', '!=', $user->id);
            })
            ->exists();
    }

    private function ipHasUsedTrial(string $ip): bool
    {
        return Subscription::where('trial_ip', $ip)->exists();
    }

    private function deviceHasUsedTrial(string $deviceHash): bool
    {
        return Subscription::where('trial_device_hash', $deviceHash)->exists();
    }
}
