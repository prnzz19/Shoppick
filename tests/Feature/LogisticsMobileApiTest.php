<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Permission;
use App\Models\ProofOfDelivery;
use App\Models\RiderProfile;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsMobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'admin', 'logistics', 'rider'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
        foreach (['view_logistics_dashboard', 'view_shipments', 'manage_shipments', 'assign_shipments', 'manage_riders', 'manage_logistics_settings'] as $slug) {
            $p = Permission::create(['name' => $slug, 'slug' => $slug, 'guard_name' => 'web', 'group' => 'Logistics']);
            Role::where('slug', 'logistics')->first()->permissions()->attach($p);
        }
    }

    private function actor(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'password' => 'password']);
        $u->assignRole($role);
        if ($role === 'rider') {
            RiderProfile::create(['user_id' => $u->id, 'account_status' => 'active', 'availability' => 'available', 'driver_license_number' => 'TEST-'.$u->id, 'driver_license_expires_at' => now()->addYear(), 'driver_license_front_path' => 'test/front', 'driver_license_back_path' => 'test/back', 'driver_license_status' => 'verified']);
        }

        return $u;
    }

    private function token(User $u): string
    {
        $plain = bin2hex(random_bytes(32));
        DB::table('mobile_api_tokens')->insert(['user_id' => $u->id, 'name' => 'logistics-mobile', 'token_hash' => hash('sha256', $plain), 'created_at' => now(), 'updated_at' => now()]);

        return $plain;
    }

    private function shipment(string $status = 'ready_for_pickup', ?User $rider = null): Shipment
    {
        $buyer = $this->actor('buyer');
        $seller = $this->actor('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Parcel Shop', 'slug' => 'shop-'.$seller->id, 'status' => 'active']);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'SP-MOBILE-'.$buyer->id, 'status' => 'ready_to_ship', 'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => ['address_line' => '1 Test Street', 'city' => 'Manila']]);
        $order->payments()->create(['method' => 'cod', 'status' => 'pending', 'gateway' => 'cod', 'amount' => 120]);
        $so = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => $order->order_number.'-S1', 'status' => 'ready_to_ship', 'subtotal' => 100, 'shipping_fee' => 20, 'seller_total' => 100]);

        return Shipment::create(['shipment_number' => Shipment::number(), 'parcel_code' => 'PCL-'.$so->id, 'seller_order_id' => $so->id, 'order_id' => $order->id, 'store_id' => $store->id, 'status' => $status, 'rider_id' => $rider?->id, 'delivery_address' => $order->shipping_address, 'ready_at' => now()]);
    }

    public function test_tracking_is_scoped_and_only_own_active_delivery_accepts_valid_gps(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $rider = $this->actor('rider');
        $other = $this->actor('rider');
        $manager = $this->actor('logistics');
        $s = $this->shipment('out_for_delivery', $rider);
        $url = '/api/v1/rider/deliveries/'.$s->id;
        $gps = ['latitude' => 14.5995, 'longitude' => 120.9842, 'accuracy' => 12, 'recorded_at' => now()->toIso8601String()];
        $this->getJson($url.'/tracking')->assertUnauthorized();
        $this->withToken($this->token($this->actor('buyer')))->getJson('/api/v1/logistics/deliveries/'.$s->id.'/tracking')->assertForbidden();
        $this->withToken($this->token($other))->getJson($url.'/tracking')->assertNotFound();
        $this->postJson($url.'/location', $gps)->assertNotFound();
        $this->withToken($this->token($rider))->getJson($url.'/tracking')->assertOk()->assertJsonPath('can_share_location', true)->assertJsonPath('destination', null);
        $this->postJson($url.'/location', array_replace($gps, ['latitude' => 91]))->assertUnprocessable();
        $this->postJson($url.'/location', array_replace($gps, ['longitude' => -181]))->assertUnprocessable();
        $this->postJson($url.'/location', $gps)->assertCreated();
        $this->assertDatabaseHas('shipment_tracking_points', ['shipment_id' => $s->id, 'rider_id' => $rider->id, 'source' => 'device']);
        $this->withToken($this->token($manager))->getJson('/api/v1/logistics/deliveries/'.$s->id.'/tracking')->assertOk()->assertJsonCount(1, 'points')->assertJsonPath('current_rider_location.rider_id', $rider->id);
        Role::where('slug', 'logistics')->first()->permissions()->detach(Permission::where('slug', 'view_shipments')->value('id'));
        $this->getJson('/api/v1/logistics/deliveries/'.$s->id.'/tracking')->assertForbidden();
        $s->update(['status' => 'delivered']);
        $this->withToken($this->token($rider))->getJson($url.'/tracking')->assertOk()->assertJsonPath('can_share_location', false)->assertJsonPath('current_rider_location', null);
        $this->postJson($url.'/location', $gps)->assertUnprocessable();
    }

    public function test_tracking_uses_only_actual_coordinates_and_pickup_assignment(): void
    {
        $rider = $this->actor('rider');
        $s = $this->shipment('pickup_accepted');
        $s->update(['pickup_rider_id' => $rider->id, 'pickup_address' => ['latitude' => 14.5, 'longitude' => 121]]);
        $s->events()->create(['status' => 'pickup_accepted', 'metadata' => ['latitude' => 14.5, 'longitude' => 121]]);
        $s->events()->create(['status' => 'picked_up']);
        $this->withToken($this->token($rider));
        $url = '/api/v1/rider/deliveries/'.$s->id;
        $this->getJson($url.'/tracking')->assertOk()->assertJsonPath('origin.latitude', 14.5)->assertJsonPath('events.1.coordinates', null)->assertJsonPath('can_share_location', true);
        $this->postJson($url.'/location', ['latitude' => 14.5, 'longitude' => 121, 'recorded_at' => now()->subHour()->toIso8601String()])->assertUnprocessable();
        $s->update(['status' => 'picked_up', 'pickup_arrived_at' => now()]);
        $this->postJson($url.'/location', ['latitude' => 14.5, 'longitude' => 121, 'recorded_at' => now()->toIso8601String()])->assertUnprocessable();
    }

    public function test_terminal_and_inactive_shipments_never_accept_location(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $rider = $this->actor('rider');
        $s = $this->shipment('out_for_delivery', $rider);
        $this->withToken($this->token($rider));
        foreach (['delivered', 'completed', 'returned', 'delivery_failed', 'exception', 'assigned_to_rider', 'at_sorting_center'] as $status) {
            $s->update(['status' => $status]);
            $this->postJson('/api/v1/rider/deliveries/'.$s->id.'/location', ['latitude' => 14.5, 'longitude' => 121, 'recorded_at' => now()->toIso8601String()])->assertUnprocessable();
        }
        $this->assertDatabaseCount('shipment_tracking_points', 0);
    }

    public function test_login_rejects_marketplace_and_admin_accounts_and_uses_scoped_expiring_tokens(): void
    {
        foreach (['buyer', 'seller', 'admin'] as $role) {
            $u = $this->actor($role);
            $this->postJson('/api/v1/logistics/login', ['email' => $u->email, 'password' => 'password'])->assertForbidden()->assertJsonPath('message', 'This account does not have access to SHOPPICK Logistics.');
        }
        $u = $this->actor('logistics');
        $response = $this->postJson('/api/v1/logistics/login', ['email' => $u->email, 'password' => 'password'])->assertCreated()->assertJsonPath('user.role', 'logistics');
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/logistics/profile')->assertOk();
        $this->withToken($token)->getJson('/api/v1/rider/dashboard')->assertForbidden();
        DB::table('mobile_api_tokens')->update(['created_at' => now()->subDays(31)]);
        $this->withToken($token)->getJson('/api/v1/logistics/profile')->assertUnauthorized();
    }

    public function test_rider_cannot_read_or_mutate_another_riders_delivery_or_manager_routes(): void
    {
        $owner = $this->actor('rider');
        $other = $this->actor('rider');
        $s = $this->shipment('out_for_delivery', $owner);
        $this->withToken($this->token($other));
        $this->getJson('/api/v1/rider/deliveries')->assertOk()->assertJsonCount(0, 'deliveries.data');
        $this->getJson('/api/v1/rider/deliveries/'.$s->id)->assertNotFound();
        $this->patchJson('/api/v1/rider/deliveries/'.$s->id.'/status', ['action' => 'failed', 'reason' => 'recipient_unavailable'])->assertNotFound();
        $this->getJson('/api/v1/logistics/riders')->assertForbidden();
        $this->getJson('/api/v1/logistics/providers')->assertForbidden();
        $this->getJson('/api/v1/logistics/dashboard')->assertForbidden();
        $this->assertSame('out_for_delivery', $s->fresh()->status);
    }

    public function test_pending_suspended_and_revoked_accounts_are_denied(): void
    {
        $rider = $this->actor('rider');
        $token = $this->token($rider);
        $rider->riderProfile->update(['account_status' => 'suspended']);
        $this->withToken($token)->getJson('/api/v1/rider/dashboard')->assertForbidden();
        $rider->riderProfile->update(['account_status' => 'active']);
        $rider->update(['registration_type' => 'rider', 'registration_status' => 'pending']);
        $this->withToken($token)->getJson('/api/v1/rider/dashboard')->assertForbidden();
        $rider->update(['registration_status' => 'approved', 'is_active' => false]);
        $this->withToken($token)->getJson('/api/v1/rider/dashboard')->assertForbidden();
    }

    public function test_assignment_search_filters_and_provider_counts_use_real_records(): void
    {
        $manager = $this->actor('logistics');
        $rider = $this->actor('rider');
        $s = $this->shipment();
        $p = LogisticsProvider::create(['name' => 'Local Courier', 'code' => 'LOCAL']);
        $rider->riderProfile->forceFill(['logistics_provider_id' => $p->id])->save();
        $s->forceFill(['logistics_provider_id' => $p->id])->save();
        $this->withToken($this->token($manager));
        foreach ([$s->shipment_number, $s->order->order_number, $s->order->buyer_name, 'Parcel Shop', 'Local Courier'] as $term) {
            $this->getJson('/api/v1/logistics/deliveries?q='.urlencode($term))->assertOk()->assertJsonCount(1, 'deliveries.data');
        }
        $this->getJson('/api/v1/logistics/deliveries?status=delivered')->assertOk()->assertJsonCount(0, 'deliveries.data');
        $this->getJson('/api/v1/logistics/dashboard')->assertOk()->assertJsonPath('summary.Pending pickups', 1);
        $this->getJson('/api/v1/logistics/providers')->assertOk()->assertJsonPath('providers.data.0.active_deliveries', 1)->assertJsonPath('providers.data.0.active_riders', 1);
        $this->getJson('/api/v1/logistics/riders?delivery_id='.$s->id)->assertOk()->assertJsonCount(1, 'riders.data');
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $rider->id])->assertOk()->assertJsonPath('delivery.status', 'pickup_assigned');
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $rider->id])->assertUnprocessable();
        $this->getJson('/api/v1/logistics/deliveries?q='.urlencode($rider->name))->assertOk()->assertJsonCount(1, 'deliveries.data');
        $this->assertDatabaseHas('notifications_custom', ['user_id' => $rider->id, 'title' => 'New parcel pickup assignment']);
    }

    public function test_unavailable_unlicensed_and_wrong_provider_riders_cannot_be_assigned(): void
    {
        $manager = $this->actor('logistics');
        $rider = $this->actor('rider');
        $s = $this->shipment();
        $this->withToken($this->token($manager));
        $url = '/api/v1/logistics/deliveries/'.$s->id.'/assign-rider';
        $rider->riderProfile->update(['availability' => 'off_duty']);
        $this->patchJson($url, ['rider_id' => $rider->id])->assertUnprocessable();
        $rider->riderProfile->update(['availability' => 'available', 'driver_license_expires_at' => now()->subDay()]);
        $this->patchJson($url, ['rider_id' => $rider->id])->assertUnprocessable();
        $rider->riderProfile->update(['driver_license_expires_at' => now()->addYear()]);
        $p = LogisticsProvider::create(['name' => 'Other Courier', 'code' => 'OTHER']);
        $s->forceFill(['logistics_provider_id' => $p->id])->save();
        $this->patchJson($url, ['rider_id' => $rider->id])->assertUnprocessable();
    }

    public function test_pickup_sorting_and_delivery_assignment_follow_existing_workflow(): void
    {
        $manager = $this->actor('logistics');
        $pickup = $this->actor('rider');
        $rider = $this->actor('rider');
        $s = $this->shipment();
        $mt = $this->token($manager);
        $pt = $this->token($pickup);
        $rt = $this->token($rider);
        $this->withToken($mt)->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $pickup->id])->assertOk();
        $url = '/api/v1/rider/deliveries/'.$s->id.'/status';
        $this->withToken($pt)->patchJson($url, ['action' => 'delivered'])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'confirm_pickup', 'parcel_code' => $s->parcel_code])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'accept_pickup'])->assertOk();
        $this->patchJson($url, ['action' => 'confirm_pickup', 'parcel_code' => 'WRONG'])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'confirm_pickup', 'parcel_code' => $s->parcel_code])->assertOk()->assertJsonPath('delivery.status', 'picked_up');
        $this->patchJson($url, ['action' => 'arrive_sorting'])->assertOk();
        $managerUrl = '/api/v1/logistics/deliveries/'.$s->id.'/status';
        $this->withToken($mt)->patchJson($managerUrl, ['action' => 'receive'])->assertOk()->assertJsonPath('delivery.status', 'at_sorting_center');
        $this->patchJson($managerUrl, ['action' => 'scan', 'parcel_code' => $s->parcel_code])->assertOk();
        $area = DeliveryArea::create(['name' => 'Manila', 'code' => 'MAN', 'province' => 'Metro Manila', 'is_active' => true]);
        $this->patchJson($managerUrl, ['action' => 'sort', 'delivery_area_id' => $area->id])->assertOk();
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $rider->id])->assertUnprocessable();
        $rider->riderProfile->deliveryAreas()->attach($area);
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $rider->id])->assertOk();
        $this->withToken($rt)->patchJson($url, ['action' => 'start_delivery'])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'accept_delivery'])->assertOk();
        $this->patchJson($url, ['action' => 'start_delivery'])->assertOk()->assertJsonPath('delivery.status', 'out_for_delivery');
    }

    public function test_proof_and_explicit_cod_collection_are_required_before_delivery(): void
    {
        Storage::fake('local');
        $rider = $this->actor('rider');
        $s = $this->shipment('out_for_delivery', $rider);
        $this->withToken($this->token($rider));
        $url = '/api/v1/rider/deliveries/'.$s->id;
        $this->patchJson($url.'/status', ['action' => 'delivered'])->assertUnprocessable();
        $this->postJson($url.'/proof', ['recipient_name' => 'Buyer', 'photo' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertUnprocessable();
        $image = UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII='));
        $this->postJson($url.'/proof', ['recipient_name' => 'Buyer', 'photo' => $image])->assertOk();
        $proof = ProofOfDelivery::firstOrFail();
        Storage::disk('local')->assertExists($proof->photo_path);
        $this->patchJson($url.'/status', ['action' => 'delivered'])->assertUnprocessable();
        $this->patchJson($url.'/status', ['action' => 'collect_cod'])->assertOk();
        $this->assertSame('cod_collected', $s->order->fresh()->payment_status);
        $this->patchJson($url.'/status', ['action' => 'delivered'])->assertOk()->assertJsonPath('delivery.status', 'delivered');
        $this->assertSame('paid', $s->order->fresh()->payment_status);
        $this->patchJson($url.'/status', ['action' => 'delivered'])->assertUnprocessable();
        $this->getJson('/api/v1/rider/deliveries?history=1')->assertJsonCount(1, 'deliveries.data');
    }

    public function test_failed_delivery_requires_reason_and_only_manager_can_retry_or_return(): void
    {
        $rider = $this->actor('rider');
        $manager = $this->actor('logistics');
        $s = $this->shipment('out_for_delivery', $rider);
        $this->withToken($this->token($rider));
        $url = '/api/v1/rider/deliveries/'.$s->id.'/status';
        $this->patchJson($url, ['action' => 'failed'])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'failed', 'reason' => 'other'])->assertUnprocessable();
        $this->patchJson($url, ['action' => 'failed', 'reason' => 'recipient_unavailable'])->assertOk()->assertJsonPath('delivery.status', 'delivery_failed');
        $this->patchJson($url, ['action' => 'reschedule'])->assertUnprocessable();
        $this->assertSame('cod', $s->order->fresh()->payment_status);
        $this->withToken($this->token($manager));
        $this->getJson('/api/v1/logistics/dashboard')->assertJsonPath('summary.Failed deliveries', 1);
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/status', ['action' => 'reschedule'])->assertOk()->assertJsonPath('delivery.status', 'assigned_to_rider');
        $s->update(['status' => 'delivery_failed']);
        $this->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/status', ['action' => 'return', 'details' => 'Unable to complete delivery'])->assertOk()->assertJsonPath('delivery.status', 'returned');
    }

    public function test_permissions_notifications_and_logout_are_scoped(): void
    {
        $manager = $this->actor('logistics');
        $s = $this->shipment();
        $token = $this->token($manager);
        Role::where('slug', 'logistics')->first()->permissions()->detach(Permission::where('slug', 'assign_shipments')->value('id'));
        $this->withToken($token)->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/assign-rider', ['rider_id' => $this->actor('rider')->id])->assertForbidden();
        $manager->notificationsData()->create(['type' => 'logistics', 'title' => 'Own notice', 'body' => 'Test']);
        $other = $this->actor('rider');
        $other->notificationsData()->create(['type' => 'logistics', 'title' => 'Private notice', 'body' => 'Other']);
        $this->getJson('/api/v1/logistics/notifications')->assertJsonCount(1, 'notifications.data');
        $this->postJson('/api/v1/logistics/notifications/read-all')->assertOk();
        $this->assertNull($other->notificationsData()->first()->read_at);
        $this->postJson('/api/v1/logistics/logout')->assertOk();
        $this->getJson('/api/v1/logistics/profile')->assertUnauthorized();
    }

    public function test_retry_resets_acceptance_and_requires_new_proof_without_changing_payment(): void
    {
        $rider = $this->actor('rider');
        $manager = $this->actor('logistics');
        $s = $this->shipment('delivery_failed', $rider);
        $s->update(['delivery_accepted_at' => now()->subHour()]);
        $pod = ProofOfDelivery::create(['shipment_id' => $s->id, 'submitted_by' => $rider->id, 'recipient_name' => 'Previous attempt', 'photo_path' => 'pod/old.jpg', 'status' => 'pending', 'submitted_at' => now()->subHour()]);
        $this->withToken($this->token($manager))->patchJson('/api/v1/logistics/deliveries/'.$s->id.'/status', ['action' => 'reschedule'])->assertOk();
        $this->assertNull($s->fresh()->delivery_accepted_at);
        $this->assertSame('rejected', $pod->fresh()->status);
        $this->assertSame('cod', $s->order->fresh()->payment_status);
        $s->update(['status' => 'out_for_delivery']);
        $this->withToken($this->token($rider))->patchJson('/api/v1/rider/deliveries/'.$s->id.'/status', ['action' => 'delivered'])->assertUnprocessable();
    }
}
