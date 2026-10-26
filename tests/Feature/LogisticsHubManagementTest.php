<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsProvider, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsHubManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['logistics', 'admin'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
        $permission = Permission::create(['name' => 'Manage Hubs', 'slug' => 'manage_hubs', 'group' => 'Logistics', 'guard_name' => 'web']);
        Role::where('slug', 'logistics')->firstOrFail()->permissions()->attach($permission);
    }

    private function logistics(): array
    {
        $user = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $user->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $user->id, 'company_name' => 'Hub Test Logistics', 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($user->id, ['membership_role' => 'owner', 'is_active' => true]);

        return [$user, $provider];
    }

    public function test_logistics_can_create_edit_deactivate_and_reactivate_only_its_own_center(): void
    {
        [$logistics, $provider] = $this->logistics();
        [, $otherProvider] = $this->logistics();
        $otherHub = LogisticsHub::create(['logistics_provider_id' => $otherProvider->id, 'code' => 'OTHER-HUB', 'name' => 'Other Center', 'address' => 'Other address', 'status' => 'active']);

        $this->actingAs($logistics)->get(route('logistics.hubs'))->assertOk()->assertSee('Add Hub');
        $this->actingAs($logistics)->post(route('logistics.hubs.store'), [
            'code' => 'OWN-HUB', 'name' => 'Owned Center', 'address' => 'Owned address', 'capacity' => 100, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $hub = LogisticsHub::where('code', 'OWN-HUB')->firstOrFail();
        $this->assertSame($provider->id, $hub->logistics_provider_id);
        $this->actingAs($logistics)->get(route('logistics.hubs', ['view' => $hub->id]))->assertOk()->assertSee('Manage Owned Center')->assertSee('Save Sorting Center Changes');
        $this->actingAs($logistics)->put(route('logistics.hubs.update', $hub), [
            'code' => 'OWN-HUB-EDITED', 'name' => 'Edited Center', 'address' => 'Edited address', 'capacity' => 120, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('logistics_hubs', ['id' => $hub->id, 'name' => 'Edited Center', 'capacity' => 120]);

        $this->actingAs($logistics)->patch(route('logistics.hubs.status', $hub), ['status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $hub->fresh()->status);
        $this->actingAs($logistics)->patch(route('logistics.hubs.status', $hub), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('active', $hub->fresh()->status);

        $this->actingAs($logistics)->put(route('logistics.hubs.update', $otherHub), [
            'code' => 'BLOCKED', 'name' => 'Blocked', 'address' => 'Blocked', 'status' => 'active',
        ])->assertNotFound();
        $this->assertSame('Other Center', $otherHub->fresh()->name);
    }

    public function test_admin_sorting_centers_page_has_no_management_mutation_routes(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.logistics.centers.index'))->assertOk();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.logistics.centers.store'));
    }
}
