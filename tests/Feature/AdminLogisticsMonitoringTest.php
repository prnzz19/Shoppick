<?php

namespace Tests\Feature;

use App\Models\{Order, SellerOrder, SellerProfile, Shipment, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogisticsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $user->assignRole($role);
        return $user;
    }

    private function shipment(string $status, ?User $rider = null, ?User $pickup = null): Shipment
    {
        $seller = $this->user('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Monitor Shop', 'slug' => 'monitor-'.$seller->id, 'status' => 'active']);
        $buyer = $this->user('buyer');
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'MON-ORDER-'.$seller->id, 'status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'total' => 100, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => $order->order_number.'-S1', 'status' => 'shipped', 'subtotal' => 100, 'seller_total' => 100]);

        return Shipment::create(['shipment_number' => 'MON-TRACK-'.$seller->id, 'parcel_code' => 'MON-PARCEL-'.$seller->id, 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'status' => $status, 'rider_id' => $rider?->id, 'pickup_rider_id' => $pickup?->id]);
    }

    public function test_admin_can_monitor_real_counts_search_details_and_rider_activity_without_mutations(): void
    {
        $admin = $this->user('admin');
        $this->user('logistics');
        $rider = $this->user('rider');
        $active = $this->shipment('out_for_delivery', $rider, $rider);
        $delivered = $this->shipment('delivered', $rider);
        $this->shipment('completed', $rider);
        $this->shipment('returned', $rider);
        $this->shipment('delivery_failed', $rider);
        $this->shipment('at_sorting_center', null, $rider);
        $pickup = $this->shipment('pickup_accepted', null, $rider);
        $active->events()->create(['status' => 'out_for_delivery', 'note' => 'Parcel left sorting center.', 'actor_id' => $rider->id]);
        $before = Shipment::orderBy('id')->get()->toArray();

        $this->actingAs($admin)->get(route('admin.logistics.overview'))->assertOk()
            ->assertSee('Logistics Management')->assertViewHas('summary', fn ($summary) => $summary === [
                'Logistics Staff' => 1, 'Riders' => 1, 'Active Shipments' => 3, 'Out for Delivery' => 1, 'Delivered' => 2, 'Failed / Returned' => 2,
            ]);
        foreach ([$active->shipment_number, $active->parcel_code, $active->order->order_number, $active->sellerOrder->seller_order_number] as $term) {
            $this->get(route('admin.logistics.deliveries.index', ['q' => $term]))->assertOk()->assertSee($active->shipment_number)->assertDontSee($delivered->shipment_number);
        }
        $this->get(route('admin.logistics.deliveries.index', ['status' => 'delivered']))->assertOk()->assertSee($delivered->shipment_number)->assertDontSee($active->shipment_number);
        $this->get(route('admin.logistics.deliveries.index', ['q' => 'no-such-tracking']))->assertOk()->assertSee('No deliveries found.');
        $response = $this->get(route('admin.logistics.deliveries.show', $active))->assertOk()->assertSee('Parcel left sorting center.')->assertSee('Monitor Shop')->assertSee('Not recorded');
        foreach (['Assign Rider', 'Reassign Rider', 'Mark Delivered', 'Upload Proof of Delivery', 'Accept Parcel', 'Change Hub'] as $action) {
            $response->assertDontSee($action);
        }
        $this->get(route('admin.logistics.riders.index'))->assertOk()->assertViewHas('riders', fn ($rows) => $rows->first()->current_assignments === 3 && $rows->first()->completed_deliveries === 2);
        $this->get(route('admin.logistics.riders.show', $rider))->assertOk()->assertSee($pickup->shipment_number);
        $this->get(route('admin.logistics.riders.show', $admin))->assertNotFound();
        $this->assertSame($before, Shipment::orderBy('id')->get()->toArray());
    }

    public function test_monitoring_routes_are_admin_only_and_expose_no_operational_endpoints(): void
    {
        $shipment = $this->shipment('ready_for_pickup');
        $rider = $this->user('rider');
        $urls = [
            route('admin.logistics.overview'), route('admin.logistics.deliveries.index'),
            route('admin.logistics.deliveries.show', $shipment), route('admin.logistics.riders.index'),
            route('admin.logistics.riders.show', $rider), route('admin.logistics.deliveries.proof', $shipment),
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach (['buyer', 'seller', 'logistics', 'rider'] as $role) {
            $this->actingAs($this->user($role));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
        }
        $this->actingAs($this->user('admin'));
        $this->post(route('logistics.shipments.assign', $shipment), ['rider_id' => $rider->id])->assertForbidden();
        $this->post(route('logistics.shipments.status', $shipment), ['status' => 'delivered'])->assertForbidden();
        $this->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'deliver'])->assertForbidden();
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'admin.logistics.')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
        }
        $this->assertSame('ready_for_pickup', $shipment->fresh()->status);
    }

    public function test_existing_logistics_and_rider_actions_appear_in_admin_without_copying_records(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $admin = $this->user('admin');
        $logistics = $this->user('logistics');
        foreach (['assign_shipments', 'manage_shipments', 'view_shipments'] as $slug) {
            $permission = \App\Models\Permission::create(['name' => $slug, 'slug' => $slug, 'guard_name' => 'web', 'group' => 'Logistics']);
            $logistics->roles()->first()->permissions()->attach($permission);
        }
        $pickup = $this->user('rider');
        $delivery = $this->user('rider');
        foreach ([$pickup, $delivery] as $rider) {
            \App\Models\RiderProfile::create([
                'user_id' => $rider->id, 'account_status' => 'active', 'availability' => 'available',
                'driver_license_number' => 'TEST-'.$rider->id, 'driver_license_classification' => 'Test',
                'driver_license_expires_at' => now()->addYear(), 'driver_license_status' => 'verified',
                'driver_license_front_path' => 'test/front.jpg', 'driver_license_back_path' => 'test/back.jpg',
            ]);
        }
        $shipment = $this->shipment('ready_for_pickup');
        $shipment->order->payments()->create(['method' => 'cod', 'status' => 'pending', 'gateway' => 'cod', 'amount' => 100]);
        $area = \App\Models\DeliveryArea::create(['code' => 'MON-AREA', 'name' => 'Monitor Area', 'province' => 'Laguna', 'is_active' => true]);
        $delivery->riderProfile->deliveryAreas()->attach($area);
        $count = Shipment::count();

        $assertShared = function (string $status) use ($admin, $shipment, $count) {
            $fresh = $shipment->fresh();
            $this->assertSame($status, $fresh->status);
            $this->assertSame($count, Shipment::count());
            $this->actingAs($admin)->get(route('admin.logistics.deliveries.index', ['q' => $fresh->shipment_number]))
                ->assertOk()->assertViewHas('shipments', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $fresh->id && $rows->first()->status === $status && $rows->first()->rider_id === $fresh->rider_id && $rows->first()->updated_at->equalTo($fresh->updated_at));
            $this->get(route('admin.logistics.deliveries.show', $fresh))->assertOk()
                ->assertViewHas('shipment', fn ($record) => $record->events->pluck('id')->all() === $fresh->events()->orderBy('created_at')->orderBy('id')->pluck('id')->all());
        };

        $this->actingAs($logistics)->post(route('logistics.pickup.assign', $shipment), ['rider_id' => $pickup->id])->assertSessionHasNoErrors();
        $assertShared('pickup_assigned');
        $this->actingAs($pickup)->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'accept_pickup'])->assertSessionHasNoErrors();
        $this->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'confirm_pickup', 'parcel_code' => $shipment->parcel_code])->assertSessionHasNoErrors();
        $assertShared('picked_up');
        $this->actingAs($pickup)->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'arrive_sorting'])->assertSessionHasNoErrors();
        $this->actingAs($logistics)->post(route('logistics.incoming.receive', $shipment))->assertSessionHasNoErrors();
        $assertShared('at_sorting_center');
        $this->actingAs($logistics)->post(route('logistics.incoming.scan', $shipment), ['parcel_code' => $shipment->parcel_code])->assertSessionHasNoErrors();
        $this->post(route('logistics.sorting.update', $shipment), ['delivery_area_id' => $area->id])->assertSessionHasNoErrors();
        $this->post(route('logistics.delivery.assign', $shipment), ['rider_id' => $delivery->id])->assertSessionHasNoErrors();
        $assertShared('assigned_to_rider');

        $this->actingAs($delivery)->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'accept_delivery'])->assertSessionHasNoErrors();
        $this->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'start_delivery'])->assertSessionHasNoErrors();
        $assertShared('out_for_delivery');
        $this->get(route('admin.logistics.overview'))->assertViewHas('summary', fn ($data) => $data['Out for Delivery'] === 1);
        $this->actingAs($logistics)->get(route('logistics.tracking', ['shipment' => $shipment->id]))->assertOk()->assertSee('out_for_delivery');
        $this->actingAs($shipment->order->user)->get(route('orders.show', $shipment->order->order_number))->assertOk()->assertSee('out_for_delivery');

        $this->actingAs($delivery)->post(route('rider.shipments.pod', $shipment), [
            'recipient_name' => 'Monitoring Recipient',
            'photo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('delivery.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7n8AAAAASUVORK5CYII=')),
        ])->assertSessionHasNoErrors();
        $proof = $shipment->fresh()->proofOfDelivery;
        $this->assertNotNull($proof);
        $this->actingAs($admin)->get(route('admin.logistics.deliveries.show', $shipment))->assertOk()->assertSee('Monitoring Recipient')->assertSee('View proof of delivery');
        $this->get(route('admin.logistics.deliveries.proof', $shipment))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertDatabaseCount('proof_of_deliveries', 1);
        $this->actingAs($delivery)->post(route('rider.shipments.cod-collection', $shipment))->assertSessionHasNoErrors();
        $this->post(route('rider.shipments.parcel-action', $shipment), ['action' => 'delivered'])->assertSessionHasNoErrors();
        $assertShared('delivered');
        $this->get(route('admin.logistics.overview'))->assertViewHas('summary', fn ($data) => $data['Delivered'] === 1 && $data['Out for Delivery'] === 0);
        $this->get(route('admin.logistics.riders.show', $delivery))->assertOk()->assertViewHas('rider', fn ($rider) => $rider->completed_deliveries === 1 && $rider->last_activity_at !== null);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($proof->photo_path);
        $this->get(route('admin.logistics.deliveries.proof', $shipment))->assertNotFound();
        $this->assertDatabaseCount('shipments', $count);
    }

}
