<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for every Billing & Payments mail: one shared template, queued, and
 * only delivered after the surrounding DB transaction commits — services
 * fire these from inside transactions.
 */
abstract class BillingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $mailSubject,
        public string $heading,
        public string $body,
        public array $details = [],
        public ?string $ctaUrl = null,
        public string $ctaLabel = 'Open',
    ) {
        // Queueable already declares $afterCommit; set, don't redeclare.
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function build(): static
    {
        return $this->view('emails.billing', [
            'subject' => $this->mailSubject,
            'userName' => $this->userName,
            'heading' => $this->heading,
            'body' => $this->body,
            'details' => $this->details,
            'ctaUrl' => $this->ctaUrl,
            'ctaLabel' => $this->ctaLabel,
        ]);
    }
}
