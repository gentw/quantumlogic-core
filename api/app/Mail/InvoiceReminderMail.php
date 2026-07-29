<?php

namespace App\Mail;

class InvoiceReminderMail extends BillingMail
{
    public function __construct(string $userName, string $invoiceNumber, float $amountDue, string $dueDate, string $url)
    {
        parent::__construct(
            userName: $userName,
            mailSubject: "Reminder: invoice {$invoiceNumber} is awaiting payment",
            heading: 'Friendly payment reminder',
            body: "Invoice {$invoiceNumber} is still open. If you have already paid, please disregard this note.",
            details: ['Invoice' => $invoiceNumber, 'Open amount' => '€'.number_format($amountDue, 2), 'Was due' => $dueDate],
            ctaUrl: $url,
            ctaLabel: 'Pay now',
        );
    }
}
