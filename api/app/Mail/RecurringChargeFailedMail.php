<?php

namespace App\Mail;

class RecurringChargeFailedMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, string $reason, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: 'We could not charge your recurring service',
            heading: 'Action needed on your recurring service',
            body: "The automatic charge failed: {$reason}\nWe will retry — you can also pay the open invoice directly or update your saved card. Your service keeps running while we sort this out.",
            details: ['Invoice' => $invoiceNumber],
            ctaUrl: $url,
            ctaLabel: 'Pay open invoice',
        );
    }
}
