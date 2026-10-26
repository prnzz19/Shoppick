<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RiderApplicationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;


    public function __construct(
        public string $riderName,
        public string $providerName,
        public string $loginUrl,
    ) {}

    public function build(): static
    {
        return $this->subject('Your SHOPPICK Rider Application Has Been Approved!')
            ->view('emails.rider-approval')
            ->text('emails.rider-approval-text');
    }
}
