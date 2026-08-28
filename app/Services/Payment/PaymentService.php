<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PaymentService
{
    /**
     * Get the payment service for a given method code.
     * Add new payment methods here without touching the rest of the app.
     *
     * @param string $method cod | gcash | maya | card
     */
    public static function driver(string $method): PaymentServiceInterface
    {
        return match (strtolower($method)) {
            'cod' => new CashOnDeliveryService(),
            'gcash', 'maya', 'card' => new GatewayService(strtolower($method)),
            default => throw new InvalidArgumentException("Unsupported payment method [{$method}]."),
        };
    }

    public static function availableMethods(): array
    {
        return [
            'cod' => 'Cash on Delivery',
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'card' => 'Card / Online',
        ];
    }
}
