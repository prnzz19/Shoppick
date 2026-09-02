<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder implementation for e-wallet / card gateways.
 *
 * This service simulates a successful payment so the whole ordering flow can be
 * tested without a real gateway. Real providers (GCash, Maya, Stripe, etc.) can
 * be integrated later by swapping the internals of this class — the rest of the
 * application only depends on PaymentServiceInterface.
 */
class GatewayService implements PaymentServiceInterface
{
    protected string $method;

    public function __construct(string $method)
    {
        $this->method = $method;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function initialOrderStatus(): string
    {
        return 'pending';
    }

    public function isConfigured(): bool
    {
        // Set to false until a real payment gateway is integrated.
        return false;
    }

    public function charge(Order $order, array $data = []): array
    {
        // Simulate a successful gateway capture for development/demo purposes.
        Log::info("Simulating payment via {$this->method} for order {$order->order_number}");

        $reference = strtoupper($this->method) . '-' . strtoupper(bin2hex(random_bytes(4)));

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $order->payments()->create([
            'method' => $this->method,
            'status' => 'paid',
            'reference' => $reference,
            'transaction_id' => $reference,
            'gateway' => $this->method,
            'details' => $data,
            'amount' => $order->total,
            'paid_at' => now(),
        ]);

        return [
            'success' => true,
            'payment_status' => 'paid',
            'settled' => true,
            'reference' => $reference,
            'message' => 'Payment successful (simulated).',
            'details' => ['simulated' => true],
        ];
    }

    public function verify(Order $order, array $data = []): array
    {
        return ['success' => true, 'message' => 'Payment confirmed.'];
    }
}
