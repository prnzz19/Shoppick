<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SortingCenterDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SortingCenterDemoSeeder is restricted to local/testing environments.');
        }
        DB::transaction(function () {
            $logistics = User::where('email', 'logistics@shoppick.test')->firstOrFail();
            $riders = ['juan' => User::where('email', 'rider.juan@shoppick.test')->firstOrFail(), 'maria' => User::where('email', 'rider.maria@shoppick.test')->firstOrFail(), 'carlo' => User::where('email', 'rider.carlo@shoppick.test')->firstOrFail()];
            $areas = ['a' => DeliveryArea::updateOrCreate(['code' => 'DEMO-AREA-A'], ['name' => 'Area A — Santa Cruz', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangays' => [], 'is_active' => true]), 'b' => DeliveryArea::updateOrCreate(['code' => 'DEMO-AREA-B'], ['name' => 'Area B — Pagsanjan', 'province' => 'Laguna', 'municipality' => 'Pagsanjan', 'barangays' => [], 'is_active' => true]), 'c' => DeliveryArea::updateOrCreate(['code' => 'DEMO-AREA-C'], ['name' => 'Area C — Los Baños', 'province' => 'Laguna', 'municipality' => 'Los Baños', 'barangays' => [], 'is_active' => true])];
            foreach (['juan' => ['Motorcycle', 'ABC-1234', 'a'], 'maria' => ['Motorcycle', 'XYZ-5678', 'b'], 'carlo' => ['Tricycle', 'AAA-1111', 'c']] as $key => $data) {
                $profile = $riders[$key]->riderProfile;
                $profile->update(['vehicle_type' => $data[0], 'plate_number' => $data[1]]);
                $profile->deliveryAreas()->syncWithoutDetaching([$areas[$data[2]]->id]);
            }
            $examples = [['n' => 21, 'parcel' => 'PARCEL-2001', 'shop' => 1, 'city' => 'Pagsanjan', 'area' => 'b', 'status' => 'ready_for_pickup', 'pickup' => null, 'delivery' => null], ['n' => 22, 'parcel' => 'PARCEL-2002', 'shop' => 2, 'city' => 'Santa Cruz', 'area' => 'a', 'status' => 'picked_up', 'pickup' => 'juan', 'delivery' => null], ['n' => 23, 'parcel' => 'PARCEL-2003', 'shop' => 3, 'city' => 'Los Baños', 'area' => 'c', 'status' => 'at_sorting_center', 'pickup' => 'carlo', 'delivery' => null], ['n' => 24, 'parcel' => 'PARCEL-2004', 'shop' => 1, 'city' => 'Pagsanjan', 'area' => 'b', 'status' => 'sorted', 'pickup' => 'juan', 'delivery' => null], ['n' => 25, 'parcel' => 'PARCEL-2005', 'shop' => 2, 'city' => 'Santa Cruz', 'area' => 'a', 'status' => 'assigned_to_rider', 'pickup' => 'maria', 'delivery' => 'juan'], ['n' => 26, 'parcel' => 'PARCEL-2006', 'shop' => 1, 'city' => 'Pagsanjan', 'area' => 'b', 'status' => 'out_for_delivery', 'pickup' => 'juan', 'delivery' => 'maria'], ['n' => 27, 'parcel' => 'PARCEL-2007', 'shop' => 3, 'city' => 'Los Baños', 'area' => 'c', 'status' => 'delivery_failed', 'pickup' => 'maria', 'delivery' => 'carlo'], ['n' => 28, 'parcel' => 'PARCEL-2008', 'shop' => 2, 'city' => 'Santa Cruz', 'area' => 'a', 'status' => 'returned', 'pickup' => 'carlo', 'delivery' => 'juan']];
            foreach ($examples as $example) {
                $this->parcel($example, $logistics, $riders, $areas);
            }
        });
    }

    private function parcel(array $x, User $logistics, array $riders, array $areas): void
    {
        $store = Store::where('slug', 'demo-shop-'.$x['shop'])->firstOrFail();
        $product = Product::where('slug', 'logistics-demo-item-'.$x['shop'])->firstOrFail();
        $buyer = User::where('email', 'demo.buyer'.$x['shop'].'@shoppick.test')->firstOrFail();
        $order = Order::updateOrCreate(['order_number' => 'ORD-DEMO-'.$x['n']], ['user_id' => $buyer->id, 'status' => in_array($x['status'], ['ready_for_pickup'], true) ? 'packed' : 'shipped', 'payment_method' => 'card', 'payment_status' => 'paid', 'subtotal' => 100, 'shipping_fee' => 60, 'total' => 160, 'buyer_name' => $buyer->name, 'buyer_phone' => $buyer->phone, 'shipping_address' => ['address_line' => '123 Demo Street', 'barangay' => 'Demo Barangay', 'city' => $x['city'], 'province' => 'Laguna'], 'paid_at' => now()->subDay()]);
        $sellerOrder = SellerOrder::updateOrCreate(['seller_order_number' => 'ORD-DEMO-'.$x['n'].'-S1'], ['order_id' => $order->id, 'store_id' => $store->id, 'status' => $x['status'] === 'ready_for_pickup' ? 'ready_to_ship' : 'shipped', 'subtotal' => 100, 'shipping_fee' => 60, 'seller_total' => 100]);
        OrderItem::updateOrCreate(['order_id' => $order->id, 'product_id' => $product->id], ['seller_order_id' => $sellerOrder->id, 'product_name' => $product->name, 'sku' => $product->sku, 'price' => 100, 'quantity' => 1, 'total' => 100]);
        Payment::updateOrCreate(['reference' => 'SORT-DEMO-'.$x['n']], ['order_id' => $order->id, 'method' => 'card', 'status' => 'paid', 'amount' => 160, 'paid_at' => now()->subDay()]);
        $advanced = in_array($x['status'], ['at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'returned'], true);
        $sorted = in_array($x['status'], ['sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'returned'], true);
        $assigned = in_array($x['status'], ['assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'returned'], true);
        $readyAt = now()->subHours(4);
        $assignedAt = $x['pickup'] ? $readyAt->copy()->addMinutes(20) : null;
        $acceptedAt = $x['pickup'] ? $readyAt->copy()->addMinutes(30) : null;
        $pickedUpAt = $x['pickup'] ? $readyAt->copy()->addMinutes(40) : null;
        $receivedAt = $advanced ? $readyAt->copy()->addHour() : null;
        $sortedAt = $sorted ? $readyAt->copy()->addHour()->addMinutes(44) : null;
        $shipment = Shipment::updateOrCreate(['shipment_number' => 'DEMO-'.$x['parcel']], ['parcel_code' => $x['parcel'], 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'pickup_rider_id' => $x['pickup'] ? $riders[$x['pickup']]->id : null, 'rider_id' => $x['delivery'] ? $riders[$x['delivery']]->id : null, 'delivery_area_id' => $sorted ? $areas[$x['area']]->id : null, 'status' => $x['status'], 'pickup_address' => ['address' => $store->location], 'delivery_address' => $order->shipping_address, 'ready_at' => now()->subHours(4), 'picked_up_at' => $x['pickup'] ? now()->subHours(3) : null, 'pickup_arrived_at' => $advanced ? now()->subHours(2) : null, 'received_at' => $advanced ? now()->subHours(2) : null, 'received_by' => $advanced ? $logistics->id : null, 'parcel_scanned_at' => $advanced ? now()->subMinutes(110) : null, 'sorted_at' => $sorted ? now()->subMinutes(90) : null, 'sorted_by' => $sorted ? $logistics->id : null, 'delivery_assigned_at' => $assigned ? now()->subHour() : null, 'delivery_assigned_by' => $assigned ? $logistics->id : null, 'delivery_accepted_at' => in_array($x['status'], ['out_for_delivery', 'delivery_failed', 'returned'], true) ? now()->subMinutes(50) : null, 'failure_reason' => in_array($x['status'], ['delivery_failed', 'returned'], true) ? 'Recipient unavailable' : null, 'returned_at' => $x['status'] === 'returned' ? now()->subMinutes(10) : null, 'internal_notes' => 'DEMO ONLY — database-backed Sorting Center example.']);
        $shipment->update(['ready_at'=>$readyAt,'assigned_at'=>$assignedAt,'pickup_accepted_at'=>$acceptedAt,'picked_up_at'=>$pickedUpAt,'pickup_arrived_at'=>$advanced ? $receivedAt->copy()->subMinutes(5) : null,'received_at'=>$receivedAt,'parcel_scanned_at'=>$advanced ? $receivedAt->copy()->addMinutes(10) : null,'sorted_at'=>$sortedAt]);
        $statuses = array_values(array_filter(['ready_for_pickup', $x['pickup'] ? 'pickup_assigned' : null, $x['pickup'] ? 'pickup_accepted' : null, $x['pickup'] ? 'picked_up' : null, $advanced ? 'at_sorting_center' : null, $sorted ? 'sorted' : null, $assigned ? 'assigned_to_rider' : null, in_array($x['status'], ['out_for_delivery', 'delivery_failed', 'returned'], true) ? 'out_for_delivery' : null, $x['status'] === 'delivery_failed' ? 'delivery_failed' : null, $x['status'] === 'returned' ? 'returned' : null]));
        $eventTimes = ['ready_for_pickup'=>$readyAt,'pickup_assigned'=>$assignedAt,'pickup_accepted'=>$acceptedAt,'picked_up'=>$pickedUpAt,'at_sorting_center'=>$receivedAt,'sorted'=>$sortedAt];
        foreach ($statuses as $i => $status) {
            $pickupActor = in_array($status, ['pickup_accepted','picked_up'], true) && $x['pickup'] ? $riders[$x['pickup']] : null;
            $actor = $pickupActor ?: ($i ? $logistics : null);
            $note = match($status) {
                'ready_for_pickup' => 'DEMO ONLY — Seller completed Parcel preparation.',
                'pickup_assigned' => 'DEMO ONLY — '.$riders[$x['pickup']]->name.' assigned for Seller pickup.',
                'pickup_accepted' => 'DEMO ONLY — '.$riders[$x['pickup']]->name.' accepted the pickup assignment.',
                'picked_up' => 'DEMO ONLY — Parcel collected from Seller by '.$riders[$x['pickup']]->name.'.',
                'at_sorting_center' => 'DEMO ONLY — Parcel received at SHOPPICK Sorting Center.',
                'sorted' => 'DEMO ONLY — Assigned to '.$areas[$x['area']]->name.'.',
                default => 'DEMO ONLY — '.str($status)->headline().'.',
            };
            $at = $eventTimes[$status] ?? now()->subMinutes((count($statuses) - $i) * 10);
            ShipmentEvent::updateOrCreate(['shipment_id' => $shipment->id, 'status' => $status], ['actor_id' => $actor?->id, 'location' => $status === 'sorted' ? $areas[$x['area']]->name : null, 'note' => $note, 'created_at' => $at, 'updated_at' => $at]);
        }
    }
}
