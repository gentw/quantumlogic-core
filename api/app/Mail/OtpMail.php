<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public $expiresAt;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $expiresAt)
    {
        $this->otp = $otp;
        $this->expiresAt = $expiresAt;
    }

    public function build()
    {
        return $this->markdown('emails.send_otp')->subject('OTP Code');
    }
}
