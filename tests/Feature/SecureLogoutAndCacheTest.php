<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecureLogoutAndCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
    }

    public function test_authenticated_html_is_private_and_uncacheable_without_changing_public_pages(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        foreach (['private', 'no-store', 'no-cache', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
        $response->assertHeader('Pragma', 'no-cache')->assertHeader('Expires', '0');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $publicResponse = $this->get(route('login'))->assertOk();
        $this->assertStringNotContainsString('no-store', strtolower((string) $publicResponse->headers->get('Cache-Control')));
    }

    public function test_logout_invalidates_session_and_protected_pages_reject_follow_up_requests(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
