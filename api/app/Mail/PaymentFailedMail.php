<?php

namespace App\Mail;

class PaymentFailedMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, string $reason, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: "Payment failed – {$invoiceNumber}",
            heading: 'Your payment did not go through',
            body: "The payment for invoice {$invoiceNumber} failed: {$reason}\nPlease try again or choose another payment method.",
            ctaUrl: $url,
            ctaLabel: 'Try again',
        );
    }
}
