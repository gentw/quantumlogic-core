<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendUserRegisterConfirmation extends Mailable
{
    use Queueable, SerializesModels;
    
    public $name, $role, $username, $password;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $role, $username, $password)
    {
        $this->name = $name;
        $this->role = $role;
        $this->username = $username;
        $this->password = $password;
    }

    public function build()
    {
        return $this->markdown('emails.new_user')->subject('Mirë se vini në Delta Connect');
    }
}
