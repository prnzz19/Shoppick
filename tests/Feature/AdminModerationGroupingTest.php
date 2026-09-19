<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ModerationScan;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductImageModerationEnrollmentService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModerationGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_is_grouped_by_shop_with_combined_filters_for_admin(): void
    {
        $permission = Permission::create(['name' => 'Moderate Products', 'slug' => 'moderate_products', 'group' => 'Moderation', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permission);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $alphaSeller = User::factory()->create(['name' => 'Alpha Seller']);
        $betaSeller = User::factory()->create(['name' => 'Beta Seller']);
        $pendingSeller = User::factory()->create(['name' => 'Pending Seller']);
        $alphaProfile = SellerProfile::create(['user_id' => $alphaSeller->id, 'status' => 'approved']);
        $betaProfile = SellerProfile::create(['user_id' => $betaSeller->id, 'status' => 'approved']);
        $pendingProfile = SellerProfile::create(['user_id' => $pendingSeller->id, 'status' => 'pending']);
        $alpha = Store::create(['user_id' => $alphaSeller->id, 'seller_profile_id' => $alphaProfile->id, 'name' => 'Alpha Shop', 'slug' => 'alpha-shop', 'status' => 'active']);
        $beta = Store::create(['user_id' => $betaSeller->id, 'seller_profile_id' => $betaProfile->id, 'name' => 'Beta Shop', 'slug' => 'beta-shop', 'status' => 'active']);
        $pendingStore = Store::create(['user_id' => $pendingSeller->id, 'seller_profile_id' => $pendingProfile->id, 'name' => 'Pending Shop', 'slug' => 'pending-shop', 'status' => 'pending']);
        $category = Category::create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $pendingProduct = Product::create(['store_id' => $alpha->id, 'category_id' => $category->id, 'name' => 'Orange Chair', 'slug' => 'orange-chair', 'price' => 100, 'stock' => 4]);
        $approvedProduct = Product::create(['store_id' => $alpha->id, 'category_id' => $category->id, 'name' => 'Clean Desk', 'slug' => 'clean-desk', 'price' => 200, 'stock' => 3]);
        $flaggedProduct = Product::create(['store_id' => $beta->id, 'category_id' => $category->id, 'name' => 'Flagged Lamp', 'slug' => 'flagged-lamp', 'price' => 300, 'stock' => 2]);
        $pendingShopProduct = Product::create(['store_id' => $pendingStore->id, 'category_id' => $category->id, 'name' => 'Pending Shop Draft', 'slug' => 'pending-shop-draft', 'price' => 50, 'stock' => 2, 'publication_status' => 'draft', 'is_active' => false]);

        $pending = $this->scan($pendingProduct, $alpha, $alphaSeller, 'pending_scan');
        $this->scan($approvedProduct, $alpha, $alphaSeller, 'approved');
        $flagged = $this->scan($flaggedProduct, $beta, $betaSeller, 'flagged');
        $this->scan($pendingShopProduct, $pendingStore, $pendingSeller, 'pending_scan');

        $this->actingAs($admin)->get(route('admin.moderation.index'))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Seller: Alpha Seller')->assertSee('Beta Shop')
            ->assertSee('2 moderated products')->assertSee('Expand All')->assertSee('Collapse All')
            ->assertDontSee('Pending Shop Draft')->assertDontSee('Seller: Pending Seller')
            ->assertSee('aria-controls="shop-moderation-'.$alpha->id.'"', false)
            ->assertSee(route('admin.moderation.show', $pending));

        $this->get(route('admin.moderation.index', ['shop' => $beta->id, 'status' => 'flagged']))->assertOk()
            ->assertSee('Beta Shop')->assertSee('Flagged Lamp')->assertDontSee('Seller: Alpha Seller')
            ->assertSee("x-show=\"open['{$beta->id}']\"", false);
        $this->get(route('admin.moderation.index', ['q' => 'Orange Chair']))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Orange Chair')->assertDontSee('Seller: Beta Seller');

        $this->actingAs($admin)->get(route('admin.moderation.index', ['status' => 'flagged']))->assertOk()
            ->assertSee('Beta Shop')->assertSee(route('admin.moderation.show', $flagged));

        $flaggedProduct->update(['publication_status' => 'published', 'is_active' => false]);
        $betaProfile->update(['status' => 'pending']);
        $this->post(route('admin.moderation.review', $flagged), ['decision' => 'approved', 'notes' => 'The image itself is acceptable.'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $flaggedProduct->fresh()->moderation_status);
        $this->assertFalse($flaggedProduct->fresh()->is_active);
    }

    public function test_reconciliation_enrolls_each_eligible_image_once_and_groups_same_named_shops_by_id(): void
    {
        $permission = Permission::create(['name' => 'Moderate Products', 'slug' => 'moderate_products', 'group' => 'Moderation', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permission);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);

        $stores = collect(['first@example.test', 'second@example.test'])->map(function ($email, $index) use ($category) {
            $seller = User::factory()->create(['name' => 'Seller '.($index + 1), 'email' => $email, 'is_active' => true]);
            $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
            $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Same Display Name', 'slug' => 'same-name-'.($index + 1), 'status' => 'active']);
            $product = Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Product '.($index + 1), 'slug' => 'product-'.($index + 1), 'price' => 100, 'stock' => 2, 'publication_status' => 'published', 'is_active' => true]);
            ProductImage::withoutEvents(fn () => ProductImage::create(['product_id' => $product->id, 'path' => 'products/product-'.($index + 1).'.jpg', 'is_primary' => true]));

            return $store;
        });

        $inactiveSeller = User::factory()->create(['is_active' => false]);
        $inactiveProfile = SellerProfile::create(['user_id' => $inactiveSeller->id, 'status' => 'approved']);
        $inactiveStore = Store::create(['user_id' => $inactiveSeller->id, 'seller_profile_id' => $inactiveProfile->id, 'name' => 'Inactive Shop', 'slug' => 'inactive-shop', 'status' => 'active']);
        $inactiveProduct = Product::create(['store_id' => $inactiveStore->id, 'category_id' => $category->id, 'name' => 'Inactive Product', 'slug' => 'inactive-product', 'price' => 100, 'stock' => 2]);
        ProductImage::withoutEvents(fn () => ProductImage::create(['product_id' => $inactiveProduct->id, 'path' => 'products/inactive.jpg', 'is_primary' => true]));

        $service = app(ProductImageModerationEnrollmentService::class);
        $first = $service->reconcile();
        $second = $service->reconcile();

        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('moderation_scans', 2);
        $this->assertDatabaseMissing('moderation_scans', ['product_id' => $inactiveProduct->id]);

        $response = $this->actingAs($admin)->get(route('admin.moderation.index'));
        $response->assertOk()->assertSee('first@example.test')->assertSee('second@example.test');
        foreach ($stores as $store) {
            $response->assertSee('Shop #'.$store->id);
        }
    }

    public function test_future_seller_product_images_enter_moderation_automatically(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['services.image_moderation.queued' => true]);
        $seller = User::factory()->create(['is_active' => true]);
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Future Shop', 'slug' => 'future-shop', 'status' => 'active']);
        $category = Category::create(['name' => 'Future Category', 'slug' => 'future-category', 'is_active' => true]);

        $product = app(ProductService::class)->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Future Product',
            'price' => 150,
            'stock' => 3,
            'publication_status' => 'published',
            'is_active' => true,
            'images' => [UploadedFile::fake()->create('future.jpg', 10, 'image/jpeg')],
        ]);

        $this->assertDatabaseHas('moderation_scans', [
            'product_id' => $product->id,
            'product_image_id' => $product->images->first()->id,
            'store_id' => $store->id,
            'seller_id' => $seller->id,
            'status' => 'pending_scan',
        ]);
        $this->assertSame('pending_scan', $product->fresh()->moderation_status);
        $this->assertFalse($product->fresh()->is_active);
    }

    private function scan(Product $product, Store $store, User $seller, string $status): ModerationScan
    {
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'products/'.$product->slug.'.jpg', 'is_primary' => true]);

        $scan = ModerationScan::updateOrCreate(['product_image_id' => $image->id], [
            'product_id' => $product->id, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'status' => $status, 'provider' => 'test', 'risk_level' => $status === 'flagged' ? 'high' : 'low',
        ]);

        return $scan;
    }
}
