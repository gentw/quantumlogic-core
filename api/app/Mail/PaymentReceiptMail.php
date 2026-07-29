<?php

namespace App\Mail;

class PaymentReceiptMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, float $amount, float $remaining, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: "Payment received – {$invoiceNumber}",
            heading: 'Thank you for your payment',
            body: 'We have received your payment.'.($remaining > 0 ? ' A remaining balance is still open.' : ''),
            details: array_filter([
                'Invoice' => $invoiceNumber,
                'Amount paid' => '€'.number_format($amount, 2),
                'Still open' => $remaining > 0 ? '€'.number_format($remaining, 2) : null,
            ]),
            ctaUrl: $url,
            ctaLabel: 'View invoice',
        );
    }
}
