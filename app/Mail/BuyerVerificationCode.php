<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class BuyerVerificationCode extends Mailable
{
    public function __construct(public string $code) {}
    public function build(): static {return $this->subject('Verify your SHOPPICK email')->view('emails.buyer-verification')->text('emails.buyer-verification-text');}
}
