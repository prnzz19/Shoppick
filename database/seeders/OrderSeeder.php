<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::where('email', 'buyer@shoppick.test')->first();
        if (! $buyer) {
            return;
        }

        $products = Product::with('images')->take(8)->get();
        if ($products->isEmpty()) {
            return;
        }

        // Ensure buyer has a default address
        $address = $buyer->addresses()->first();
        if (! $address) {
            $address = $buyer->addresses()->create([
                'full_name' => $buyer->name,
                'phone' => $buyer->phone ?? '09170000003',
                'province' => 'Metro Manila',
                'city' => 'Quezon City',
                'barangay' => 'Barangay Central',
                'postal_code' => '1100',
                'address_line' => '123 SHOPPICK Street, Brgy. Central',
                'label' => 'Home',
                'is_default' => true,
            ]);
        }

        $orderDefs = [
            ['status' => 'completed', 'completed_at' => now()->subDays(2), 'paid' => true],
            ['status' => 'completed', 'completed_at' => now()->subDays(9), 'paid' => true],
            ['status' => 'shipped', 'completed_at' => null, 'paid' => true],
            ['status' => 'processing', 'completed_at' => null, 'paid' => false],
            ['status' => 'pending', 'completed_at' => null, 'paid' => false],
            ['status' => 'cancelled', 'completed_at' => null, 'paid' => false],
        ];

        foreach ($orderDefs as $i => $def) {
            $shuffled = $products->shuffle()->take(rand(1, 3));
            $subtotal = 0;
            $items = [];

            foreach ($shuffled as $product) {
                $qty = rand(1, 3);
                $price = (float) $product->salePrice();
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;
                $items[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $price,
                    'line' => $lineTotal,
                ];
            }

            $shipping = $subtotal >= 500 ? 0 : 50;
            $orderNumber = 'SP' . 'DEMO' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            if (Order::where('order_number', $orderNumber)->exists()) {
                continue;
            }

            $order = Order::create([
                'user_id' => $buyer->id,
                'order_number' => $orderNumber,
                'status' => $def['status'],
                'payment_method' => in_array($def['status'], ['cancelled', 'pending', 'processing']) ? 'cod' : 'gcash',
                'payment_status' => $def['paid'] ? 'paid' : 'unpaid',
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_fee' => $shipping,
                'voucher_discount' => 0,
                'total' => $subtotal + $shipping,
                'buyer_name' => $buyer->name,
                'buyer_phone' => $buyer->phone,
                'shipping_address' => $address->toArray(),
                'paid_at' => $def['paid'] ? now()->subDays(2) : null,
                'completed_at' => $def['completed_at'],
                'cancelled_at' => $def['status'] === 'cancelled' ? now()->subDays(1) : null,
                'created_at' => now()->subDays($i * 2),
                'updated_at' => now()->subDays($i * 2),
            ]);

            foreach ($items as $it) {
                $product = $it['product'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->getMainImageAttribute(),
                    'sku' => $product->sku,
                    'price' => $it['price'],
                    'quantity' => $it['qty'],
                    'total' => $it['line'],
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $order->payment_method,
                'status' => $def['paid'] ? 'paid' : 'pending',
                'reference' => $def['paid'] ? 'SIM-' . strtoupper(bin2hex(random_bytes(3))) : null,
                'gateway' => $order->payment_method,
                'amount' => $order->total,
                'paid_at' => $def['paid'] ? now()->subDays(2) : null,
            ]);
        }
    }
}
