<?php

namespace App\Mail;

class GuestWelcomeMail extends BillingMail
{
    /**
     * Two shapes, because guest checkout has two ways in:
     *
     * - the buyer chose a password at checkout — confirm the credentials and
     *   point them at the login page
     * - no password was set (programmatic/admin creation) — send the
     *   set-password link built on the existing ResetCodePassword flow
     *
     * @param  string|null  $setPasswordUrl  null when a password already exists
     * @param  string|null  $plainPassword  echoed back at the buyer's request;
     *                                      only ever the password they typed
     *                                      themselves, never a generated one
     */
    public function __construct(
        string $userName,
        string $email,
        ?string $setPasswordUrl = null,
        ?string $plainPassword = null,
        ?string $loginUrl = null,
    ) {
        $hasPassword = $setPasswordUrl === null;

        $body = $hasPassword
            ? "Thanks for your order — your client portal account is ready.\n\n"
                ."Email: {$email}"
                .($plainPassword !== null ? "\nPassword: {$plainPassword}" : '')
                ."\n\nYou can follow your order, invoices and payments there. "
                .'We recommend changing this password after your first sign-in.'
            : "Thanks for your order — the deposit is in and work is starting.\n"
                .'Set a password to follow your order, invoices and payments in the client portal.';

        parent::__construct(
            userName: $userName,
            mailSubject: $hasPassword
                ? 'Welcome to QuantumLogic – your account details'
                : 'Welcome to QuantumLogic – set your password',
            heading: 'Your account is ready',
            body: $body,
            ctaUrl: $setPasswordUrl ?? $loginUrl,
            ctaLabel: $hasPassword ? 'Go to the portal' : 'Set your password',
        );
    }
}
