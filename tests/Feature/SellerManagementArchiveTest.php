<?php

namespace Tests\Feature;

use App\Models\{AdminActivityLog, Category, Order, Permission, Product, Role, SellerApplication, SellerOrder, SellerProfile, Shipment, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerManagementArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['manage_sellers', 'manage_users', 'view_shops', 'approve_shops'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'guard_name' => 'web']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['is_active' => true])->assignRole('admin');
    }

    private function seller(): array
    {
        $user = User::factory()->create(['is_active' => true])->assignRole('buyer', 'seller');
        $profile = SellerProfile::create(['user_id' => $user->id, 'status' => 'approved', 'approved_at' => now()]);
        $shop = Store::create(['user_id' => $user->id, 'seller_profile_id' => $profile->id, 'name' => 'Archive Test Shop', 'slug' => 'archive-'. $user->id, 'status' => 'active']);
        return [$user, $profile, $shop];
    }

    public function test_user_archive_is_removed_and_old_column_does_not_block_buyer_login(): void
    {
        // Simulate the retired column without shipping its obsolete migration.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'archived_at')) {
            \Illuminate\Support\Facades\Schema::table('users', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->timestamp('archived_at')->nullable());
        }
        $admin = $this->admin();
        $user = User::factory()->create(['is_active' => true])->assignRole('buyer');
        $user->forceFill(['archived_at' => now()])->save();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertDontSee('Archive User?')->assertDontSee('Archived')->assertSee(route('admin.users.edit', $user));
        $this->get(route('admin.users.edit', $user))->assertOk();
        $this->get(route('admin.users.index', ['tab' => 'archived']))->assertViewHas('tab', 'all');
        foreach (['archive', 'restore'] as $action) $this->patch('/admin/users/'.$user->id.'/'.$action)->assertNotFound();
        $this->delete('/admin/users/'.$user->id.'/permanent')->assertNotFound();
        auth()->logout();
        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get(route('orders.index'))->assertOk();
    }

    public function test_shop_archive_hides_marketplace_and_preserves_business_records_and_status(): void
    {
        $admin = $this->admin();
        [$user, $profile, $shop] = $this->seller();
        $category = Category::create(['name' => 'Test', 'slug' => 'archive-test']);
        $product = Product::create(['store_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Archive Product', 'slug' => 'archive-product', 'price' => 10, 'stock' => 5, 'is_active' => true, 'publication_status' => 'published']);
        $order = Order::create(['user_id' => $user->id, 'order_number' => 'ARCHIVE-ORDER', 'payment_method' => 'cod', 'payment_status' => 'paid', 'total' => 10]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $shop->id, 'seller_order_number' => 'ARCHIVE-SO', 'subtotal' => 10, 'seller_total' => 10]);
        $shipment = Shipment::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'store_id' => $shop->id, 'shipment_number' => 'ARCHIVE-SH']);
        $payment = $order->payments()->create(['method' => 'cod', 'amount' => 10]);
        $this->actingAs($admin)->patch(route('admin.shops.archive', $shop))->assertSessionHas('success');
        $this->assertNotNull($shop->fresh()->archived_at);
        $this->assertSame('active', $shop->fresh()->status);
        $this->assertNull($profile->fresh()->archived_at);
        $this->assertFalse(Store::marketplaceActive()->whereKey($shop->id)->exists());
        $this->assertFalse(Product::active()->whereKey($product->id)->exists());
        $this->get(route('admin.shops.index'))->assertDontSee($shop->name);
        $this->get(route('admin.shops.index', ['tab' => 'archived', 'q' => $user->email]))->assertSee($shop->name)->assertSee('Restore')->assertSee('Delete');
        $this->delete(route('admin.shops.force-delete', $shop))->assertSessionHas('error');
        $this->assertSame($shop->id, $sellerOrder->fresh()->store->id);
        $this->assertSame($shop->id, $shipment->fresh()->store->id);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->patch(route('admin.shops.restore', $shop))->assertSessionHas('success');
        $this->assertNull($shop->fresh()->archived_at);
        $this->assertTrue(Store::marketplaceActive()->whereKey($shop->id)->exists());
        $shop->update(['status' => 'suspended']);
        $this->patch(route('admin.shops.archive', $shop))->assertSessionHas('success');
        $this->patch(route('admin.shops.restore', $shop))->assertSessionHas('success');
        $this->assertSame('suspended', $shop->fresh()->status);
        $this->assertFalse($shop->fresh()->isMarketplaceActive());
    }

    public function test_seller_archive_preserves_buyer_access_and_separate_shop_state(): void
    {
        $admin = $this->admin();
        [$user, $profile, $shop] = $this->seller();
        $this->actingAs($admin)->patch(route('admin.sellers.archive', $profile))->assertSessionHas('success');
        $this->assertNotNull($profile->fresh()->archived_at);
        $this->assertNull($user->fresh()->archived_at);
        $this->assertNull($shop->fresh()->archived_at);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->hasRole('buyer') && $user->hasRole('seller'));
        $this->get(route('admin.sellers.index'))->assertDontSee($user->email);
        $this->get(route('admin.sellers.index', ['tab' => 'archived', 'q' => $user->email]))->assertSee($user->email)->assertSee('Restore');
        $this->delete(route('admin.sellers.force-delete', $profile))->assertSessionHas('error');
        $this->actingAs($user)->get(route('orders.index'))->assertOk();
        $this->get(route('seller.dashboard'))->assertForbidden()->assertSee('Your seller access is currently archived.');
        $this->assertFalse($user->fresh()->hasApprovedSellerAccess());
        $this->assertFalse(Store::marketplaceActive()->whereKey($shop->id)->exists());
        DB::table('mobile_api_tokens')->insert(['user_id' => $user->id, 'name' => 'test', 'token_hash' => hash('sha256', 'seller-archive-token')]);
        $this->withToken('seller-archive-token')->getJson('/api/v1/profile')->assertOk();
        $this->getJson('/api/v1/seller/orders')->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.shops.archive', $shop))->assertSessionHas('success');
        $this->patch(route('admin.sellers.restore', $profile))->assertSessionHas('success');
        $this->assertNull($profile->fresh()->archived_at);
        $this->assertNotNull($shop->fresh()->archived_at);
        $this->assertFalse($user->fresh()->hasApprovedSellerAccess());
        $this->patch(route('admin.shops.restore', $shop))->assertSessionHas('success');
        $this->assertTrue($user->fresh()->hasApprovedSellerAccess());
        $this->actingAs($user->fresh())->get(route('seller.dashboard'))->assertOk();
    }

    public function test_application_archive_preserves_decision_roles_and_seller_access(): void
    {
        $admin = $this->admin();
        [$user, $profile, $shop] = $this->seller();
        $application = SellerApplication::create(['user_id' => $user->id, 'store_name' => $shop->name, 'phone' => '09123456789', 'address' => 'Manila', 'status' => 'approved']);
        $this->actingAs($admin)->patch(route('admin.sellers.applications.archive', $application))->assertSessionHas('success');
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertTrue($user->fresh()->hasApprovedSellerAccess());
        $this->assertNull($profile->fresh()->archived_at);
        $this->assertNull($shop->fresh()->archived_at);
        $this->get(route('admin.sellers.applications.index'))->assertDontSee($user->email);
        $this->get(route('admin.sellers.applications.index', ['tab' => 'archived', 'status' => 'approved', 'q' => $user->email]))->assertSee($user->email)->assertSee('Approved');
        $this->delete(route('admin.sellers.applications.force-delete', $application))->assertSessionHas('error');
        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->patch(route('admin.sellers.applications.restore', $application))->assertSessionHas('success');
        $this->assertNull($application->fresh()->archived_at);
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_safe_archived_entities_can_be_deleted_without_deleting_the_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create()->assignRole('buyer', 'seller');
        $profile = SellerProfile::create(['user_id' => $user->id, 'status' => 'approved']);
        $shop = Store::create(['user_id' => $user->id, 'seller_profile_id' => $profile->id, 'name' => 'Empty Shop', 'slug' => 'empty-archive', 'status' => 'active']);
        $this->actingAs($admin)->delete(route('admin.shops.force-delete', $shop))->assertUnprocessable();
        $this->patch(route('admin.shops.archive', $shop))->assertSessionHas('success');
        $this->delete(route('admin.shops.force-delete', $shop))->assertSessionHas('success');
        $this->assertDatabaseMissing('stores', ['id' => $shop->id]);
        $this->patch(route('admin.sellers.archive', $profile))->assertSessionHas('success');
        $this->delete(route('admin.sellers.force-delete', $profile))->assertSessionHas('success');
        $this->assertDatabaseMissing('seller_profiles', ['id' => $profile->id]);
        $application = SellerApplication::create(['user_id' => $user->id, 'store_name' => 'Rejected Application', 'phone' => '09123456789', 'address' => 'Manila', 'status' => 'rejected']);
        $this->patch(route('admin.sellers.applications.archive', $application))->assertSessionHas('success');
        $this->delete(route('admin.sellers.applications.force-delete', $application))->assertSessionHas('success');
        $this->assertDatabaseMissing('seller_applications', ['id' => $application->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue($user->hasRole('buyer'));
        $this->get(route('admin.sellers.applications.index', ['tab' => 'archived']))->assertViewHas('archiveCounts', fn($counts) => $counts['archived'] === 0);
    }

    public function test_every_entity_mutation_requires_admin_permission(): void
    {
        [$user, $profile, $shop] = $this->seller();
        $application = SellerApplication::create(['user_id' => $user->id, 'store_name' => 'Request', 'phone' => '09123456789', 'address' => 'Manila', 'status' => 'pending']);
        foreach (['buyer', 'seller', 'rider', 'logistics', 'admin'] as $role) {
            $actor = User::factory()->create(['is_active' => true])->assignRole($role);
            foreach (['admin.shops' => $shop, 'admin.sellers' => $profile, 'admin.sellers.applications' => $application] as $prefix => $target) {
                $this->actingAs($actor)->patch(route($prefix.'.archive', $target))->assertForbidden();
                $this->patch(route($prefix.'.restore', $target))->assertForbidden();
                $this->delete(route($prefix.'.force-delete', $target))->assertForbidden();
            }
        }
        $this->assertNull($shop->fresh()->archived_at);
        $this->assertNull($profile->fresh()->archived_at);
        $this->assertNull($application->fresh()->archived_at);
    }

    public function test_archived_application_cannot_be_reviewed_and_failed_audit_rolls_back_archive(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create()->assignRole('buyer');
        $application = SellerApplication::create(['user_id' => $user->id, 'store_name' => 'Request', 'phone' => '09123456789', 'address' => 'Manila', 'status' => 'pending']);
        $this->actingAs($admin)->patch(route('admin.sellers.applications.archive', $application))->assertSessionHas('success');
        $this->post(route('admin.sellers.applications.review', $application), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $application->fresh()->status);
        $this->patch(route('admin.sellers.applications.restore', $application))->assertSessionHas('success');
        AdminActivityLog::creating(function () { throw new \RuntimeException('Private failure'); });
        $this->patch(route('admin.sellers.applications.archive', $application))->assertSessionHas('error');
        $this->assertNull($application->fresh()->archived_at);
    }

    public function test_failed_permanent_delete_rolls_back_and_preserves_the_buyer(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create()->assignRole('buyer');
        $shop = Store::create(['user_id' => $user->id, 'name' => 'Empty', 'slug' => 'delete-rollback', 'status' => 'suspended']);
        $this->actingAs($admin)->patch(route('admin.shops.archive', $shop))->assertSessionHas('success');
        Store::forceDeleting(function () { throw new \RuntimeException('Private delete failure'); });
        $this->delete(route('admin.shops.force-delete', $shop))->assertSessionHas('error');
        $this->assertNotNull($shop->fresh()->archived_at);
        $this->assertSame('suspended', $shop->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'seller_management.deleted', 'target_id' => $shop->id, 'target_type' => Store::class]);
    }
}
