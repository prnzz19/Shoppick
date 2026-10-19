<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Order, Payment, RiderProfile, Role, SellerOrder, SellerProfile, Shipment, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class RiderMobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'buyer', 'seller', 'logistics', 'rider'] as $slug) Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
    }

    private function provider(): LogisticsProvider
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $owner->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $owner->id, 'company_name' => 'API Provider', 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($owner->id, ['membership_role' => 'owner', 'is_active' => true]);
        return $provider;
    }

    private function rider(?LogisticsProvider $provider = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved']);
        $user->assignRole('rider');
        RiderProfile::create(['user_id' => $user->id, 'logistics_provider_id' => $provider?->id, 'account_status' => 'active', 'availability' => 'available']);
        return $user->fresh('riderProfile');
    }

    private function token(User $user): string
    {
        $plain = Str::random(64);
        DB::table('mobile_api_tokens')->insert(['user_id' => $user->id, 'name' => 'rider-api-test', 'token_hash' => hash('sha256', $plain), 'created_at' => now(), 'updated_at' => now()]);
        return $plain;
    }

    private function shipment(User $rider, string $status = 'assigned_to_rider', bool $cod = false): Shipment
    {
        $provider = $rider->riderProfile->provider;
        $seller = User::factory()->create(['is_active' => true]); $seller->assignRole('seller');
        $sellerProfile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $sellerProfile->id, 'name' => 'API Shop '.Str::random(5), 'slug' => 'api-shop-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $buyer = User::factory()->create(); $buyer->assignRole('buyer');
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'API-ORDER-'.Str::random(8), 'status' => 'processing', 'payment_method' => $cod ? 'cod' : 'card', 'payment_status' => $cod ? 'unpaid' : 'paid', 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120, 'buyer_name' => 'Recipient', 'buyer_phone' => '09170000000', 'shipping_address' => ['address_line' => 'Safe street', 'city' => 'Calamba']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => 'API-SELLER-'.Str::random(8), 'status' => 'ready_to_ship', 'subtotal' => 100, 'shipping_fee' => 20, 'seller_total' => 100]);
        if ($cod) Payment::create(['order_id' => $order->id, 'method' => 'cod', 'gateway' => 'cod', 'status' => 'pending', 'amount' => 120]);
        return Shipment::create(['logistics_provider_id' => $provider?->id, 'shipment_number' => 'API-SHIP-'.Str::random(8), 'parcel_code' => 'API-WAY-'.Str::random(8), 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'pickup_rider_id' => null, 'rider_id' => $rider->id, 'status' => $status, 'assigned_at' => now(), 'delivery_address' => $order->shipping_address]);
    }

    private function headers(User $rider): array { return ['Authorization' => 'Bearer '.$this->token($rider), 'Accept' => 'application/json']; }

    public function test_api_requires_bearer_and_rider_role(): void
    {
        $this->getJson('/api/v1/rider/jobs')->assertUnauthorized();
        foreach (['buyer', 'seller', 'admin', 'logistics'] as $role) {
            $user = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']); $user->assignRole($role);
            $this->withHeaders($this->headers($user))->getJson('/api/v1/rider/jobs')->assertForbidden()->assertJsonStructure(['message']);
        }
    }

    public function test_jobs_are_owned_and_actions_are_server_generated(): void
    {
        $provider = $this->provider(); $rider = $this->rider($provider); $other = $this->rider($provider);
        $own = $this->shipment($rider); $foreign = $this->shipment($other);
        $this->withHeaders($this->headers($rider))->getJson('/api/v1/rider/jobs')->assertOk()->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.allowed_actions.0', 'accept_delivery');
        $this->withHeaders($this->headers($rider))->getJson('/api/v1/rider/jobs/'.$foreign->id)->assertNotFound();
        $this->withHeaders($this->headers($rider))->postJson('/api/v1/rider/shipments/'.$foreign->id.'/accept')->assertNotFound();
        $otherProvider = $this->provider(); $otherProviderRider = $this->rider($otherProvider); $otherProviderShipment = $this->shipment($otherProviderRider);
        $otherProviderShipment->update(['rider_id' => $rider->id]);
        $this->withHeaders($this->headers($rider))->getJson('/api/v1/rider/jobs/'.$otherProviderShipment->id)->assertNotFound();
    }

    public function test_accept_action_and_stale_versions_return_json_contracts(): void
    {
        $rider = $this->rider($this->provider()); $shipment = $this->shipment($rider);
        $headers = $this->headers($rider);
        $this->withHeaders($headers)->postJson('/api/v1/rider/shipments/'.$shipment->id.'/accept')->assertOk()->assertJsonPath('data.status', 'assigned_to_rider');
        $this->withHeaders($headers)->postJson('/api/v1/rider/shipments/'.$shipment->id.'/accept')->assertUnprocessable()->assertJsonStructure(['errors']);
        $this->withHeaders($headers)->postJson('/api/v1/rider/shipments/'.$shipment->id.'/action', ['action' => 'start_delivery', 'version' => str_repeat('0', 64)])->assertUnprocessable()->assertJsonStructure(['errors']);
    }

    public function test_availability_and_pod_cod_delivery_flow(): void
    {
        $podRoot = sys_get_temp_dir().'\\shoppick-rider-api-'.Str::random(8); @mkdir($podRoot, 0777, true); Storage::set('local', Storage::build(['driver' => 'local', 'root' => $podRoot])); config(['logging.default' => 'stderr']); $rider = $this->rider($this->provider()); $shipment = $this->shipment($rider, 'out_for_delivery', true); $headers = $this->headers($rider);
        $this->withHeaders($headers)->patchJson('/api/v1/rider/availability', ['availability' => 'off_duty'])->assertUnprocessable();
        $pod = $this->withHeaders($headers)->post('/api/v1/rider/shipments/'.$shipment->id.'/pod', ['recipient_name' => 'Real Recipient', 'photo' => UploadedFile::fake()->create('proof.jpg', 10, 'image/jpeg')]);
        $pod->assertOk()->assertJsonPath('data.status', 'pending');
        $this->withHeaders($headers)->postJson('/api/v1/rider/shipments/'.$shipment->id.'/cod')->assertOk()->assertJsonPath('data.status', 'cod_collected');
        $this->withHeaders($headers)->postJson('/api/v1/rider/shipments/'.$shipment->id.'/action', ['action' => 'delivered'])->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertDatabaseHas('proof_of_deliveries', ['shipment_id' => $shipment->id, 'recipient_name' => 'Real Recipient']);
    }

    public function test_decline_is_available_as_json_and_assignment_is_released(): void
    {
        $rider = $this->rider($this->provider()); $shipment = $this->shipment($rider, 'pickup_assigned'); $shipment->update(['pickup_rider_id' => $rider->id, 'rider_id' => null]);
        $response = $this->withHeaders($this->headers($rider))->postJson('/api/v1/rider/shipments/'.$shipment->id.'/decline', ['reason' => 'unavailable']);
        $response->assertOk()->assertJsonPath('data.status', 'ready_for_pickup');
        $this->assertNull($shipment->fresh()->pickup_rider_id);
    }
}
