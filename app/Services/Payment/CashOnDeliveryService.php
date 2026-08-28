<?php

namespace App\Services\Payment;

use App\Models\Order;

class CashOnDeliveryService implements PaymentServiceInterface
{
    public function method(): string
    {
        return 'cod';
    }

    public function initialOrderStatus(): string
    {
        return 'pending';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function charge(Order $order, array $data = []): array
    {
        $order->update([
            'payment_status' => 'cod',
            'paid_at' => null,
        ]);

        return [
            'success' => true,
            'reference' => 'COD-' . $order->order_number,
            'message' => 'You will pay in cash upon delivery.',
            'details' => [],
        ];
    }

    public function verify(Order $order, array $data = []): array
    {
        return ['success' => true, 'message' => 'Cash on delivery payment pending delivery.'];
    }
}
