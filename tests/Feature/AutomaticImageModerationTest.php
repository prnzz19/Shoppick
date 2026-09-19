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
use App\Services\AdminSidebarCounts;
use App\Services\Moderation\ImageModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutomaticImageModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.image_moderation.queued' => false]);

        $permission = Permission::create(['name' => 'Moderate Products', 'slug' => 'moderate_products', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permission);
        $sellerRole = Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole($adminRole);
        $this->seller = User::factory()->create(['is_active' => true]);
        $this->seller->assignRole($sellerRole);
        $profile = SellerProfile::create(['user_id' => $this->seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $this->seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Future Seller Shop', 'slug' => 'future-seller-shop', 'status' => 'active']);
        $category = Category::create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $this->product = Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Wireless Mouse', 'slug' => 'wireless-mouse', 'price' => 500, 'stock' => 8, 'publication_status' => 'published', 'is_active' => true]);
    }

    public function test_safe_upload_is_automatically_approved_without_increasing_attention_count(): void
    {
        $this->provider(['status' => 'safe', 'category' => 'safe', 'risk_level' => 'low']);
        $image = $this->upload('products/mouse.jpg');
        $scan = $image->moderationScans()->firstOrFail();

        $this->assertSame('approved', $scan->status);
        $this->assertSame('automatic', $scan->review_type);
        $this->assertSame('safe', $scan->moderation_result);
        $this->assertNotNull($scan->reviewed_at);
        $this->assertTrue($this->product->fresh()->is_active);
        $this->assertSame(0, app(AdminSidebarCounts::class)->get()['moderation']);
    }

    public function test_failed_scan_is_held_and_can_be_manually_rejected_with_reason(): void
    {
        $this->app->instance(ImageModerationService::class, new class implements ImageModerationService
        {
            public function scan(string $absolutePath): array
            {
                throw new \RuntimeException('Provider offline');
            }
        });
        $scan = $this->upload('products/failure.jpg')->moderationScans()->firstOrFail();
        $this->assertSame('scan_failed', $scan->status);
        $this->assertFalse($this->product->fresh()->is_active);

        $this->actingAs($this->admin)->post(route('admin.moderation.review', $scan), [
            'decision' => 'rejected', 'reason' => 'Product image violates SHOPPICK marketplace image policy.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('moderation_scans', ['id' => $scan->id, 'status' => 'rejected', 'review_type' => 'manual']);
        $this->assertDatabaseHas('notifications_custom', ['user_id' => $this->seller->id, 'title' => 'Your product image was rejected']);
    }

    public function test_replacing_rejected_image_runs_a_new_scan_and_restores_eligibility(): void
    {
        $old = ProductImage::withoutEvents(fn () => ProductImage::create(['product_id' => $this->product->id, 'path' => 'products/rejected.jpg', 'is_primary' => true]));
        ModerationScan::create(['product_id' => $this->product->id, 'product_image_id' => $old->id, 'seller_id' => $this->seller->id, 'store_id' => $this->product->store_id, 'provider' => 'test', 'status' => 'rejected', 'risk_level' => 'high', 'review_type' => 'manual']);
        $this->product->update(['moderation_status' => 'rejected', 'is_active' => false]);
        $old->delete();

        $this->provider(['status' => 'safe', 'category' => 'safe', 'risk_level' => 'low']);
        $new = $this->upload('products/replacement.jpg');
        $this->assertSame('approved', $new->moderationScans()->firstOrFail()->status);
        $this->assertSame('approved', $this->product->fresh()->moderation_status);
        $this->assertTrue($this->product->fresh()->is_active);
    }

    private function upload(string $path): ProductImage
    {
        Storage::disk('public')->put($path, 'test-image-content');

        return ProductImage::create(['product_id' => $this->product->id, 'path' => $path, 'is_primary' => true]);
    }

    private function provider(array $result): void
    {
        $this->app->instance(ImageModerationService::class, new class($result) implements ImageModerationService
        {
            public function __construct(private array $result) {}

            public function scan(string $absolutePath): array
            {
                return $this->result;
            }
        });
    }
}
