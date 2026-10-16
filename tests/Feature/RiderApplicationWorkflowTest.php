<?php

namespace Tests\Feature;

use App\Models\{Permission, Role, RiderProfile, User};
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

    public function test_public_application_and_logistics_review_routes_are_removed(): void
    {
        $this->get(route('rider.register'))->assertRedirect(route('rider.login'));
        $this->get(route('register.rider'))->assertRedirect(route('rider.login'));
        $this->post(route('rider.register.submit'), [])->assertStatus(410);
        $this->post(route('register.rider.submit'), [])->assertStatus(410);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('logistics.rider-applications'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('logistics.riders.application.review'));
    }

    public function test_owner_only_can_read_private_rider_documents(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved'])->assignRole('logistics');
        $other = User::factory()->create(['is_active' => true, 'registration_type' => 'logistics', 'registration_status' => 'approved'])->assignRole('logistics');
        $rider = User::factory()->create(['is_active' => false, 'registration_type' => 'rider', 'registration_status' => 'approved'])->assignRole('rider');
        Storage::disk('local')->put('rider-documents/licenses/license.pdf', 'private');
        Storage::disk('local')->put('rider-documents/vehicles/or-cr.pdf', 'private');
        $profile = RiderProfile::create(['user_id' => $rider->id, 'logistics_user_id' => $owner->id, 'account_status' => 'pending_activation', 'availability' => 'unavailable', 'driver_license_path' => 'rider-documents/licenses/license.pdf', 'or_cr_path' => 'rider-documents/vehicles/or-cr.pdf']);

        $this->actingAs($owner)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertOk();
        $this->get(route('logistics.riders.or-cr', $profile))->assertOk();
        $this->actingAs($other)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertNotFound();
        $this->get(route('logistics.riders.or-cr', $profile))->assertNotFound();
        $buyer = User::factory()->create(['is_active' => true])->assignRole('buyer');
        $this->actingAs($buyer)->get(route('logistics.riders.license.file', [$profile, 'front']))->assertForbidden();
    }
}
