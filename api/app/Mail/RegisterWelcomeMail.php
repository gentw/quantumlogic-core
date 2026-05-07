<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $otp;
    public $expiresAt;
    /**
     * Create a new message instance.
     */
    public function __construct($name, $otp, $expiresAt)
    {
        $this->name = $name;
        $this->otp = $otp;
        $this->expiresAt = $expiresAt;
    }

    public function build()
    {
        return $this->markdown('emails.send_otp_welcome')->subject('Welcome to SentriGate – Verify Your Account');
    }
}
