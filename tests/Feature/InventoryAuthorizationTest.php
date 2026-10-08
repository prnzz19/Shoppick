<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $seller;

    protected Product $product;

    protected Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        foreach (['manage_inventory', 'manage_products'] as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'group' => 'Products', 'guard_name' => 'web']);
            $adminRole->permissions()->attach($permission);
        }
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
        $category = Category::create(['name' => 'Inventory', 'slug' => 'inventory', 'is_active' => true]);
        [$this->seller, $this->product] = $this->sellerProduct('Alpha', $category);
        [, $this->otherProduct] = $this->sellerProduct('Beta', $category);
    }

    private function sellerProduct(string $name, Category $category): array
    {
        $seller = User::factory()->create(['name' => $name.' Seller', 'is_active' => true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id,
            'name' => $name.' Shop', 'slug' => strtolower($name).'-inventory', 'status' => 'active']);
        $product = Product::create(['store_id' => $store->id, 'category_id' => $category->id,
            'name' => $name.' Product', 'slug' => strtolower($name).'-inventory-product',
            'sku' => strtoupper($name).'-SKU', 'price' => 100, 'stock' => 10,
            'low_stock_threshold' => 5, 'sold_count' => 12, 'is_active' => true]);
        $product->variants()->create(['type' => 'Size', 'value' => 'Large', 'sku' => $name.'-L', 'stock' => 4]);

        return [$seller, $product];
    }

    public function test_admin_inventory_displays_stock_without_edit_controls(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk()->assertSee('Alpha Product')->assertSee('ALPHA-SKU')
            ->assertSee('Beta Shop')->assertSee('Seller: Alpha Seller')->assertSee('In stock')
            ->assertSee('Inventory is managed by sellers.')->assertSee('Sold')
            ->assertSee('Low Stock')->assertSee('Out of Stock')
            ->assertSee('Expand All')->assertSee('Collapse All')
            ->assertDontSee('Update Stock')->assertDontSee('>Set</button>', false)
            ->assertDontSee('type="number"', false)->assertDontSee('name="stock"', false)
            ->assertDontSee('/admin/inventory/'.$this->product->id.'/stock', false);
        $this->assertFalse(Route::has('admin.inventory.stock'));
    }

    public function test_removed_admin_stock_endpoint_cannot_change_inventory(): void
    {
        $this->actingAs($this->admin);
        foreach (['post', 'put', 'patch'] as $method) {
            $this->{$method}('/admin/inventory/'.$this->product->id.'/stock',
                ['stock' => 99, 'low_stock_threshold' => 50])->assertNotFound();
        }
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(5, $this->product->fresh()->low_stock_threshold);
        $this->assertSame(4, $this->product->variants()->first()->stock);
    }

    public function test_seller_updates_own_product_and_variants_and_admin_sees_new_stock(): void
    {
        $variant = $this->product->variants()->first();
        $foreignVariant = $this->otherProduct->variants()->first();
        $this->actingAs($this->seller)->get(route('seller.inventory.index'))->assertOk()
            ->assertSee('name="stock"', false)->assertSee('Update Stock')->assertDontSee('Beta Product');
        $this->post(route('seller.inventory.update', $this->product), [
            'stock' => 3, 'low_stock_threshold' => 5,
            'variants' => [$variant->id => 2, $foreignVariant->id => 99],
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Inventory updated.');
        $this->assertSame(3, $this->product->fresh()->stock);
        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame(4, $foreignVariant->fresh()->stock);
        $this->actingAs($this->admin)->get(route('admin.inventory.index', ['shop' => $this->product->store_id, 'scope' => 'low']))
            ->assertOk()->assertSee('Alpha Product')->assertDontSee('Beta Product')
            ->assertViewHas('shops', fn ($shops) => $shops->first()->products->first()->stock === 3)
            ->assertDontSee('name="stock"', false);
    }

    public function test_seller_cannot_update_another_sellers_product_or_variant_stock(): void
    {
        $variant = $this->otherProduct->variants()->first();
        $this->actingAs($this->seller)->post(route('seller.inventory.update', $this->otherProduct), [
            'stock' => 99, 'low_stock_threshold' => 50, 'variants' => [$variant->id => 99],
        ])->assertForbidden();
        $this->assertSame(10, $this->otherProduct->fresh()->stock);
        $this->assertSame(5, $this->otherProduct->fresh()->low_stock_threshold);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_admin_cannot_use_seller_inventory_endpoint(): void
    {
        $this->actingAs($this->admin)->post(route('seller.inventory.update', $this->product),
            ['stock' => 99, 'low_stock_threshold' => 50])->assertForbidden();
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_admin_product_routes_cannot_bypass_inventory_restriction(): void
    {
        $variant = $this->product->variants()->first();
        $this->actingAs($this->admin)->get(route('admin.products.edit', $this->product))->assertOk()
            ->assertDontSee('name="stock"', false)->assertDontSee('name="low_stock_threshold"', false)
            ->assertDontSee('name="variants[', false)->assertDontSee('addVariantRow');
        foreach ([['stock' => 99], ['low_stock_threshold' => 50], ['variants' => []],
            ['variants' => [['id' => $variant->id, 'type' => 'Size', 'value' => 'Large', 'stock' => 99]]]] as $payload) {
            $this->put(route('admin.products.update', $this->product), $payload)->assertForbidden();
            $this->post(route('admin.products.store'), $payload)->assertForbidden();
        }
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(5, $this->product->fresh()->low_stock_threshold);
        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_admin_can_edit_product_details_without_touching_inventory(): void
    {
        $variant = $this->product->variants()->first();
        $this->actingAs($this->admin)->put(route('admin.products.update', $this->product), [
            'name' => 'Updated Alpha Product', 'category_id' => $this->product->category_id, 'price' => 110,
        ])->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();
        $this->assertSame('Updated Alpha Product', $this->product->fresh()->name);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(5, $this->product->fresh()->low_stock_threshold);
        $this->assertSame(4, $variant->fresh()->stock);
    }
}
