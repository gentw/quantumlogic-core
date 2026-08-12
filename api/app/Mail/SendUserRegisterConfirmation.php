<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendUserRegisterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public $role;

    public $username;

    public $password;

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
        return $this->markdown('emails.new_user')->subject(__('mail.new_user.subject'));
    }
}
