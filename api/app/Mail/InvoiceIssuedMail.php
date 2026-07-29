<?php

namespace App\Mail;

class InvoiceIssuedMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, float $total, string $dueDate, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: "Invoice {$invoiceNumber} – QuantumLogic",
            heading: 'You have a new invoice',
            body: "We have issued invoice {$invoiceNumber}. You can view and pay it online.",
            details: ['Invoice' => $invoiceNumber, 'Amount' => '€'.number_format($total, 2), 'Due date' => $dueDate],
            ctaUrl: $url,
            ctaLabel: 'View & pay invoice',
        );
    }
}
