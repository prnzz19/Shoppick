<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicRegistrationRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['buyer', 'rider', 'logistics', 'admin'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
    }

    public function test_public_registration_selector_exposes_only_buyer_rider_and_logistics_company(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee(route('register.buyer'))
            ->assertSee(route('rider.register'))
            ->assertSee(route('register.logistics'))
            ->assertSee('Buyer')
            ->assertSee('Rider')
            ->assertSee('Logistics Company')
            ->assertDontSee('Register as Seller');
    }

    public function test_buyer_registration_page_remains_available_with_google_option(): void
    {
        $this->get(route('register.buyer'))
            ->assertOk()
            ->assertSee('Register as Buyer')
            ->assertSee('Continue with Google');
    }

    public function test_rider_registration_lists_only_approved_active_logistics_providers(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved']);
        $owner->assignRole('logistics');

        LogisticsProvider::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Approved Fleet',
            'status' => 'approved',
            'is_active' => true,
        ]);
        LogisticsProvider::create([
            'company_name' => 'Pending Fleet',
            'status' => 'pending',
            'is_active' => false,
        ]);

        $this->get(route('rider.register'))
            ->assertOk()
            ->assertSee('Approved Fleet')
            ->assertDontSee('Pending Fleet');
    }

    public function test_logistics_company_application_creates_a_pending_inactive_provider(): void
    {
        Storage::fake('local');

        $this->post(route('register.logistics.submit'), [
            'company_name' => 'New Logistics Co.',
            'trade_name' => 'NewLog',
            'company_email' => 'company@example.test',
            'company_phone' => '+639171111111',
            'company_logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
            'contact_name' => 'Jordan Manager',
            'representative_position' => 'Operations Manager',
            'representative_email' => 'logistics-company@example.test',
            'representative_phone' => '+639171234567',
            'representative_id' => UploadedFile::fake()->create('representative.pdf', 10, 'application/pdf'),
            'street' => '1 Market Street', 'barangay' => 'Central', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000',
            'registration_number' => 'SEC-2026-001',
            'business_registration' => UploadedFile::fake()->create('registration.pdf', 10, 'application/pdf'),
            'permit' => UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf'),
            'tax_registration_info' => 'TIN 123-456-789',
            'tax_registration_document' => UploadedFile::fake()->create('tax.pdf', 10, 'application/pdf'),
            'areas_served' => 'Metro Manila', 'pickup_coverage_areas' => 'Manila, Makati', 'delivery_coverage_areas' => 'Metro Manila',
            'operating_hours' => 'Monday-Saturday 08:00-18:00', 'number_of_riders' => 12, 'number_of_vehicles' => 8,
            'supported_vehicle_types' => ['motorcycle','van'],
            'operations_contact_name' => 'Ops Lead', 'operations_contact_phone' => '+639171111112', 'operations_contact_email' => 'ops@example.test',
            'support_contact_name' => 'Support Lead', 'support_contact_phone' => '+639171111113', 'support_contact_email' => 'support@example.test',
            'emergency_contact_name' => 'Emergency Lead', 'emergency_contact_phone' => '+639171111114', 'emergency_contact_email' => 'emergency@example.test',
            'password' => 'Password@2026',
            'password_confirmation' => 'Password@2026',
            'terms' => '1',
        ])->assertRedirect(route('registration.verification'));

        $user = User::where('email', 'logistics-company@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('logistics'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'registration_type' => 'logistics',
            'registration_status' => 'pending',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('logistics_providers', [
            'owner_user_id' => $user->id,
            'company_name' => 'New Logistics Co.',
            'status' => 'pending',
            'is_active' => false,
        ]);
        $provider=LogisticsProvider::where('owner_user_id',$user->id)->firstOrFail();
        $this->assertDatabaseHas('logistics_provider_user',['logistics_provider_id'=>$provider->id,'user_id'=>$user->id,'membership_role'=>'owner']);
        Storage::disk('local')->assertExists($provider->representative_id_path);
        Storage::disk('local')->assertExists($provider->business_registration_path);
    }

    public function test_logistics_company_registration_requires_the_standard_provisional_fields(): void
    {
        $this->post(route('register.logistics.submit'), [])->assertSessionHasErrors([
            'company_name','trade_name','company_email','company_phone','company_logo',
            'contact_name','representative_position','representative_email','representative_phone','representative_id',
            'street','barangay','city','province','registration_number',
            'business_registration','permit','tax_registration_info','tax_registration_document',
            'areas_served','pickup_coverage_areas','delivery_coverage_areas','operating_hours',
            'number_of_riders','number_of_vehicles','supported_vehicle_types',
            'operations_contact_name','operations_contact_phone','operations_contact_email',
            'support_contact_name','support_contact_phone','support_contact_email',
            'emergency_contact_name','emergency_contact_phone',
            'password','terms',
        ]);
    }
}
