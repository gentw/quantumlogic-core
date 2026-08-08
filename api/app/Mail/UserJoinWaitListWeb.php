<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserJoinWaitListWeb extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    /**
     * Create a new message instance.
     */
    public function __construct($name)
    {
        //
        $this->name = $name;
    }

    public function build()
    {
        return $this->markdown('emails.join_waitlist')->subject('Welcome to QuantumLogic - Beta Access & Waiting List');
    }
}
