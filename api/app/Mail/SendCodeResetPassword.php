<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendCodeResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    /**
     * Create a new message instance.
     */
    public function __construct($token)
    {
        $this->code = $token;
    }

    public function build()
    {
        return $this->markdown('emails.reset_password')->subject(__('mail.reset.subject'));
    }
}
