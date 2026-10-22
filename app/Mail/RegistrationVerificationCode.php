<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RegistrationVerificationCode extends Mailable
{
    public function __construct(public string $code, public string $role = 'account') {}

    public function build(): static
    {
        return $this->subject('Verify your SHOPPICK email')
            ->view('emails.registration-verification')
            ->text('emails.registration-verification-text');
    }
}
