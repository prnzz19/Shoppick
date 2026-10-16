<?php
namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

// Do not attach the original transport exception: it can contain SMTP diagnostics.
class BuyerVerificationDeliveryException extends \RuntimeException implements ShouldntReport
{
    public function __construct() {parent::__construct('Buyer verification email could not be delivered.');}
}
