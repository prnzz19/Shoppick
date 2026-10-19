<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Permission, Role, RiderProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'admin', 'logistics', 'rider'] as $slug) Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'Manage riders', 'slug' => 'manage_riders', 'guard_name' => 'web', 'group' => 'Logistics']);
        Role::where('slug', 'logistics')->first()->permissions()->attach($permission);
        Storage::fake('local');
    }

    public function test_public_rider_registration_uses_provider_owned_review_routes(): void
    {
        $this->get(route('rider.register'))->assertOk()->assertSee('Choose Logistics Company')->assertSee('Select an approved provider');
        $this->get(route('register.rider'))->assertRedirect(route('rider.register'));
        $this->post(route('rider.register.submit'), [])->assertSessionHasErrors(['first_name', 'email', 'logistics_provider_id']);
        $this->post(route('register.rider.submit'), [])->assertSessionHasErrors(['first_name', 'email', 'logistics_provider_id']);
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('logistics.rider-applications'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('logistics.riders.application.review'));
    }

    public function test_owner_only_can_read_private_rider_documents(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved'])->assignRole('logistics');
        $other = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved'])->assignRole('logistics');
        $ownerProvider=LogisticsProvider::create(['owner_user_id'=>$owner->id,'company_name'=>'Owner Provider','status'=>'approved','is_active'=>true]);
        $ownerProvider->users()->attach($owner->id,['membership_role'=>'owner','is_active'=>true]);
        $otherProvider=LogisticsProvider::create(['owner_user_id'=>$other->id,'company_name'=>'Other Provider','status'=>'approved','is_active'=>true]);
        $otherProvider->users()->attach($other->id,['membership_role'=>'owner','is_active'=>true]);
        $rider = User::factory()->create(['is_active' => false, 'registration_type' => 'rider', 'registration_status' => 'approved'])->assignRole('rider');
        Storage::disk('local')->put('rider-documents/licenses/license.pdf', 'private');
        Storage::disk('local')->put('rider-documents/vehicles/or-cr.pdf', 'private');
        $profile = RiderProfile::create(['user_id' => $rider->id, 'logistics_user_id' => $owner->id, 'logistics_provider_id'=>$ownerProvider->id, 'account_status' => 'pending_activation', 'availability' => 'unavailable', 'driver_license_path' => 'rider-documents/licenses/license.pdf', 'or_cr_path' => 'rider-documents/vehicles/or-cr.pdf']);

        $this->actingAs($owner)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertOk();
        $this->get(route('logistics.riders.or-cr', $profile))->assertOk();
        $this->actingAs($other)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertNotFound();
        $this->get(route('logistics.riders.or-cr', $profile))->assertNotFound();
        $buyer = User::factory()->create(['is_active' => true])->assignRole('buyer');
        $this->actingAs($buyer)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertForbidden();
    }
}
