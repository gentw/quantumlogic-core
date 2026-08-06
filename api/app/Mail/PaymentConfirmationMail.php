<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $packageName,
        public float $amount,
        public string $captureId,
        public string $billingCycle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment Confirmed – QuantumLogic');
    }

    public function build(): static
    {
        return $this->view('emails.payment_confirmation');
    }
}
