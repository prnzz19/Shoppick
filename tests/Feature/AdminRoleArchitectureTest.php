<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_has_five_roles_and_exactly_one_active_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['admin', 'buyer', 'logistics', 'rider', 'seller'],
            Role::orderBy('slug')->pluck('slug')->all()
        );
        $this->assertSame(1, User::where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->count());
        $this->assertDatabaseHas('users', ['email'=>'admin@shoppick.test', 'is_active'=>true]);
        $this->assertDatabaseMissing('users', ['email'=>'superadmin@shoppick.test']);
        $this->assertSame(Permission::count(), Role::where('slug', 'admin')->firstOrFail()->permissions()->count());
    }

    public function test_admin_login_and_legacy_bookmark_use_admin_dashboard(): void
    {
        $role = Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);
        $user = User::factory()->create(['password'=>'password','is_active'=>true]);
        $user->assignRole($role);

        $this->post(route('login.submit'), ['email'=>$user->email,'password'=>'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get('/superadmin/dashboard')->assertRedirect('/admin/dashboard');
    }

    public function test_demo_credentials_authenticate_but_are_not_displayed_publicly(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get(route('login'))->assertOk()
            ->assertDontSee('Demo accounts')
            ->assertDontSee('admin@shoppick.test')
            ->assertDontSee('seller@shoppick.test')
            ->assertDontSee('buyer@shoppick.test')
            ->assertDontSee('logistics@shoppick.test')
            ->assertDontSee('superadmin@shoppick.test');

        $admin = User::where('email', 'admin@shoppick.test')->firstOrFail();
        $this->post(route('login.submit'), ['email'=>'admin@shoppick.test', 'password'=>'password'])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_non_admin_roles_cannot_access_admin_routes(): void
    {
        foreach (['buyer', 'seller', 'logistics', 'rider'] as $slug) {
            Role::firstOrCreate(['slug'=>$slug], ['name'=>ucfirst($slug),'guard_name'=>'web']);
            $user = User::factory()->create(['is_active'=>true]);
            $user->assignRole($slug);
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    public function test_user_management_cannot_assign_another_admin(): void
    {
        $manage = Permission::create(['name'=>'Manage Users','slug'=>'manage_users','guard_name'=>'web']);
        $adminRole = Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);
        $adminRole->permissions()->attach($manage);
        $admin = User::factory()->create(['is_active'=>true]);
        $admin->assignRole($adminRole);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name'=>'Second Admin', 'email'=>'second-admin@shoppick.test',
            'password'=>'password', 'password_confirmation'=>'password',
            'roles'=>[$adminRole->id], 'is_active'=>1,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email'=>'second-admin@shoppick.test']);
    }
}
