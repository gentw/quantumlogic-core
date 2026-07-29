<?php

namespace App\Mail;

class GuestWelcomeMail extends BillingMail
{
    public function __construct(string $userName, string $setPasswordUrl)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: 'Welcome to QuantumLogic – set your password',
            heading: 'Your account is ready',
            body: "Thanks for your order — the deposit is in and work is starting.\nSet a password to follow your order, invoices and payments in the client portal.",
            ctaUrl: $setPasswordUrl,
            ctaLabel: 'Set your password',
        );
    }
}
