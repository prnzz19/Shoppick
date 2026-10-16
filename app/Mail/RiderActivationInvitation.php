<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RiderActivationInvitation extends Mailable
{
    public function __construct(public string $riderName, public string $providerName, public string $activationUrl) {}

    public function build(): static
    {
        return $this->subject('Activate your SHOPPICK Rider account')
            ->view('emails.rider-activation')
            ->text('emails.rider-activation-text');
    }
}
