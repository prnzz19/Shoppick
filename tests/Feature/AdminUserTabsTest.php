<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_tabs_scope_multi_role_users_and_compose_with_existing_filters(): void
    {
        $permission = Permission::create(['name' => 'Manage Users', 'slug' => 'manage_users', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permission);
        $admin = User::factory()->create(['email' => 'root@shoppick.test', 'is_active' => true]);
        $admin->assignRole('admin');

        $buyer = User::factory()->create(['name' => 'Buyer Match', 'email' => 'buyer@shoppick.test', 'is_active' => true]);
        $buyer->assignRole('buyer');

        $seller = User::factory()->create(['name' => 'Tech Seller', 'email' => 'seller@shoppick.test', 'is_active' => true]);
        $seller->assignRole('seller');

        $inactiveSeller = User::factory()->create(['name' => 'Tech Inactive', 'email' => 'inactive@shoppick.test', 'is_active' => false]);
        $inactiveSeller->assignRole('seller');

        $multiRole = User::factory()->create(['email' => 'multi@shoppick.test', 'is_active' => true]);
        $multiRole->assignRole('buyer', 'seller');

        $logistics = User::factory()->create(['email' => 'logistics@shoppick.test', 'is_active' => true]);
        $logistics->assignRole('logistics');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['tab' => 'buyers']))
            ->assertOk()
            ->assertSee('buyer@shoppick.test')
            ->assertSee('multi@shoppick.test')
            ->assertDontSee('seller@shoppick.test')
            ->assertDontSee('logistics@shoppick.test')
            ->assertViewHas('users', fn ($users) => $users->total() === 2)
            ->assertViewHas('tabCounts', fn ($counts) => $counts['buyers'] === 2);

        $this->get(route('admin.users.index', ['tab' => 'sellers', 'q' => 'Tech', 'status' => 'active']))
            ->assertOk()
            ->assertSee('seller@shoppick.test')
            ->assertDontSee('inactive@shoppick.test')
            ->assertDontSee('multi@shoppick.test')
            ->assertViewHas('users', fn ($users) => $users->total() === 1)
            ->assertViewHas('tabCounts', ['all' => 1, 'buyers' => 0, 'sellers' => 1, 'other' => 0]);

        $this->get(route('admin.users.index', ['tab' => 'other']))
            ->assertOk()
            ->assertSee('root@shoppick.test')
            ->assertSee('logistics@shoppick.test')
            ->assertDontSee('buyer@shoppick.test')
            ->assertDontSee('seller@shoppick.test')
            ->assertViewHas('tabCounts', ['all' => 6, 'buyers' => 2, 'sellers' => 3, 'other' => 2]);
    }

    public function test_new_buyer_registration_increments_once_and_remains_once_after_seller_role_is_added(): void
    {
        Storage::fake('local');
        Role::create(['name' => 'Buyer', 'slug' => 'buyer', 'guard_name' => 'web']);
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'Manage Users', 'slug' => 'manage_users', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permission);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $existing = User::factory()->create(['is_active' => true]);
        $existing->assignRole('buyer');

        $this->post(route('register.submit'), [
            'first_name' => 'New', 'last_name' => 'Buyer', 'sex' => 'female', 'birthday' => '2000-01-01',
            'valid_id' => UploadedFile::fake()->create('new-buyer-id.jpg', 100, 'image/jpeg'),
            'email' => 'new-buyer@shoppick.test', 'phone' => '09171234567',
            'password' => 'password', 'password_confirmation' => 'password', 'address_line' => '12 Market Street',
            'barangay' => 'Central', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000',
            'country' => 'PH', 'terms' => '1',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $newBuyer = User::where('email', 'new-buyer@shoppick.test')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.users.index', ['tab' => 'buyers']))
            ->assertViewHas('users', fn ($users) => $users->total() === 2)
            ->assertViewHas('tabCounts', fn ($counts) => $counts['buyers'] === 2);

        $newBuyer->assignRole('seller');
        $this->get(route('admin.users.index', ['tab' => 'buyers']))
            ->assertViewHas('users', fn ($users) => $users->total() === 2)
            ->assertViewHas('tabCounts', fn ($counts) => $counts['buyers'] === 2);
    }
}
