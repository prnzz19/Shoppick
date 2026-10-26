<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Permission, Role, RiderProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderWebsiteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'logistics', 'rider'] as $slug) Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'Manage riders', 'slug' => 'manage_riders', 'guard_name' => 'web', 'group' => 'Logistics']);
        Role::where('slug', 'logistics')->first()->permissions()->attach($permission);
    }

    public function test_public_rider_registration_is_available_and_legacy_rider_data_is_preserved(): void
    {
        $legacy = User::factory()->create(['is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved']);
        $legacy->assignRole('rider');
        $profile = RiderProfile::create(['user_id' => $legacy->id, 'account_status' => 'active', 'availability' => 'off_duty']);

        $this->get('/rider/register')->assertOk()->assertSee('Choose Logistics Company');
        $this->get('/register/rider')->assertRedirect(route('rider.register'));
        $this->post('/rider/register', [])->assertSessionHasErrors(['first_name', 'logistics_provider_id']);
        $this->post('/register/rider', [])->assertSessionHasErrors(['first_name', 'logistics_provider_id']);
        $this->get('/register')->assertOk()->assertSee('Rider')->assertSee('Logistics Company');

        $this->assertDatabaseHas('users', ['id' => $legacy->id]);
        $this->assertDatabaseHas('rider_profiles', ['id' => $profile->id, 'logistics_user_id' => null]);
    }

    public function test_legacy_unassigned_riders_are_not_exposed_to_logistics_accounts(): void
    {
        $logistics = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved']);
        $logistics->assignRole('logistics');
        $provider=LogisticsProvider::create(['owner_user_id'=>$logistics->id,'company_name'=>'Scoped Provider','status'=>'approved','is_active'=>true]);
        $provider->users()->attach($logistics->id,['membership_role'=>'owner','is_active'=>true]);
        $legacy = User::factory()->create(['name' => 'Legacy Unassigned Rider', 'is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved']);
        $legacy->assignRole('rider');
        $profile = RiderProfile::create(['user_id' => $legacy->id, 'account_status' => 'active', 'availability' => 'off_duty']);

        $this->actingAs($logistics)->get(route('logistics.riders'))->assertOk()->assertDontSee($legacy->email);
        $this->get(route('logistics.riders', ['view' => $profile->id]))->assertNotFound();
    }

    public function test_existing_independent_rider_portal_access_remains_available(): void
    {
        $rider = User::factory()->create(['is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved']);
        $rider->assignRole('rider');
        RiderProfile::create(['user_id' => $rider->id, 'account_status' => 'active', 'availability' => 'off_duty']);

        $this->post(route('rider.login.submit'), ['email' => $rider->email, 'password' => 'password'])->assertRedirect(route('rider.dashboard'));
        $this->get(route('rider.dashboard'))->assertOk();
        $this->assertFalse($rider->hasRole('buyer'));
    }
}
