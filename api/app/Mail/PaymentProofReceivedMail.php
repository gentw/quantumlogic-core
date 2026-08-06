<?php

namespace App\Mail;

class PaymentProofReceivedMail extends BillingMail
{
    public function __construct(string $adminName, string $invoiceNumber, string $uploaderName, string $url)
    {
        parent::__construct(
            userName: $adminName,
            mailSubject: "Payment proof uploaded – {$invoiceNumber}",
            heading: 'A bank transfer needs review',
            body: "{$uploaderName} uploaded a payment proof for invoice {$invoiceNumber}. The invoice stays open until you accept it.",
            ctaUrl: $url,
            ctaLabel: 'Open reconciliation queue',
        );
    }
}
