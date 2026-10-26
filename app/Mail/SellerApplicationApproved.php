<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SellerApplicationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sellerName,
        public string $shopName,
        public string $sellerCenterUrl,
    ) {}

    public function build(): static
    {
        return $this->subject('Your SHOPPICK Seller Application Has Been Approved!')
            ->view('emails/seller-application-approved')
            ->text('emails/seller-application-approved-text');
    }
}
