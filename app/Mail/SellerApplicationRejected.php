<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SellerApplicationRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sellerName,
        public string $shopName,
        public string $reason,
        public string $resubmitUrl,
        public string $decision = 'rejected',
    ) {}

    public function build(): static
    {
        return $this->subject('An Important Update Regarding Your SHOPPICK Seller Application')
            ->view('emails/seller-application-rejected')
            ->text('emails/seller-application-rejected-text');
    }
}
