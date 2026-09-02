<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUserTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_tabs_scope_multi_role_users_and_compose_with_existing_filters(): void
    {
        $superAdmin = User::factory()->create(['email' => 'root@shoppick.test', 'is_active' => true]);
        $superAdmin->assignRole('super_admin');

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

        $this->actingAs($superAdmin)
            ->get(route('superadmin.users.index', ['tab' => 'buyers']))
            ->assertOk()
            ->assertSee('buyer@shoppick.test')
            ->assertSee('multi@shoppick.test')
            ->assertDontSee('seller@shoppick.test')
            ->assertDontSee('logistics@shoppick.test');

        $this->get(route('superadmin.users.index', ['tab' => 'sellers', 'q' => 'Tech', 'status' => 'active']))
            ->assertOk()
            ->assertSee('seller@shoppick.test')
            ->assertDontSee('inactive@shoppick.test')
            ->assertDontSee('multi@shoppick.test');

        $this->get(route('superadmin.users.index', ['tab' => 'other']))
            ->assertOk()
            ->assertSee('root@shoppick.test')
            ->assertSee('logistics@shoppick.test')
            ->assertDontSee('buyer@shoppick.test')
            ->assertDontSee('seller@shoppick.test')
            ->assertViewHas('tabCounts', ['all' => 6, 'buyers' => 2, 'sellers' => 3, 'other' => 2]);
    }
}
