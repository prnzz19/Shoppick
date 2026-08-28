<?php

namespace App\Services\Payment;

use App\Models\Order;

interface PaymentServiceInterface
{
    /**
     * Human friendly name for the payment method.
     */
    public function method(): string;

    /**
     * Default status an order gets when initiated with this method.
     */
    public function initialOrderStatus(): string;

    /**
     * Whether a real (offline/online) gateway is configured, or it is a placeholder.
     */
    public function isConfigured(): bool;

    /**
     * Charge / capture a payment for the given order.
     * Returns an array with keys: success (bool), reference, message, details.
     */
    public function charge(Order $order, array $data = []): array;

    /**
     * Verify an existing transaction / simulate confirmation.
     */
    public function verify(Order $order, array $data = []): array;
}
