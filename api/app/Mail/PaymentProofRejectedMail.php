<?php

namespace App\Mail;

class PaymentProofRejectedMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, string $reason, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: "Payment proof rejected – {$invoiceNumber}",
            heading: 'We could not accept your payment proof',
            body: "Reason: {$reason}\nThe invoice is open again — please pay online or upload a corrected proof.",
            ctaUrl: $url,
            ctaLabel: 'View invoice',
        );
    }
}
