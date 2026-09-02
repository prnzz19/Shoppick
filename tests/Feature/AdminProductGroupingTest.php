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
use Tests\TestCase;

class AdminProductGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_products_are_grouped_by_real_shop_and_filters_remove_unrelated_groups(): void
    {
        [$admin, $sellerA, $alpha, $sellerB, $beta, $category] = $this->catalogActors();

        $this->actingAs($sellerA)->post(route('seller.products.store'), [
            'name'=>'Alpha New Product','category_id'=>$category->id,'price'=>150,'stock'=>4,'low_stock_threshold'=>5,'publication_action'=>'publish',
        ])->assertSessionHasNoErrors();
        $this->actingAs($sellerB)->post(route('seller.products.store'), [
            'name'=>'Beta Search Product','category_id'=>$category->id,'price'=>250,'stock'=>0,'low_stock_threshold'=>2,'publication_action'=>'publish',
        ])->assertSessionHasNoErrors();
        Product::create(['category_id'=>$category->id,'name'=>'Legacy Product','slug'=>'legacy-product','price'=>50,'stock'=>1,'is_active'=>false,'publication_status'=>'draft','moderation_status'=>'clean']);

        $response = $this->actingAs($admin)->get(route('admin.products.index'));
        $response->assertOk()->assertViewHas('productGroups', fn ($groups) => $groups->pluck('store.name')->filter()->values()->all() === ['Alpha Shop','Beta Shop'])
            ->assertSee('Seller: Alpha Seller')->assertSee('Seller: Beta Seller')
            ->assertSee('Alpha New Product')->assertSee('Beta Search Product')->assertSee('Legacy Product')
            ->assertSee('Expand All')->assertSee('Collapse All')
            ->assertSee('aria-controls="shop-products-'.$alpha->id.'"',false)
            ->assertSee('Products: 1')->assertSee('Low Stock: 1')->assertSee('Out of Stock: 1');

        $this->get(route('admin.products.index',['q'=>'Beta Search']))->assertOk()
            ->assertSee('Beta Shop')->assertSee('Beta Search Product')
            ->assertDontSee('Alpha New Product')
            ->assertViewHas('productGroups', fn ($groups) => $groups->count() === 1 && $groups->first()->store->is($beta));

        $this->get(route('admin.products.index',['shop_id'=>$alpha->id]))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Alpha New Product')
            ->assertDontSee('Beta Search Product')
            ->assertViewHas('productGroups', fn ($groups) => $groups->count() === 1 && $groups->first()->store->is($alpha));

        $this->get(route('admin.products.index',['shop_id'=>'unassigned']))->assertOk()
            ->assertSee('Unassigned / Legacy Products')->assertSee('Legacy Product')->assertDontSee('Alpha New Product')
            ->assertViewHas('productGroups', fn ($groups) => $groups->count() === 1 && $groups->first()->store === null);

        $this->get(route('admin.products.index',['category_id'=>$category->id,'status'=>'out_of_stock']))->assertOk()
            ->assertSee('Beta Search Product')->assertDontSee('Alpha New Product');
    }

    public function test_grouped_product_pagination_preserves_filters(): void
    {
        [$admin, , $alpha, , , $category] = $this->catalogActors();
        foreach (range(1, 13) as $number) {
            Product::create([
                'store_id'=>$alpha->id,'category_id'=>$category->id,'name'=>'Paged Product '.$number,
                'slug'=>'paged-product-'.$number,'price'=>100,'stock'=>5,'is_active'=>true,
                'publication_status'=>'published','moderation_status'=>'clean',
            ]);
        }

        $this->actingAs($admin)->get(route('admin.products.index',['shop_id'=>$alpha->id,'status'=>'active']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 13 && $products->hasPages())
            ->assertSee('shop_id='.$alpha->id, false)
            ->assertSee('status=active', false);
    }

    private function catalogActors(): array
    {
        $permission = Permission::create(['name'=>'Manage Products','slug'=>'manage_products','group'=>'Products','guard_name'=>'web']);
        $adminRole = Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);
        $adminRole->permissions()->attach($permission);
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $admin = User::factory()->create(['is_active'=>true]); $admin->assignRole('admin');

        $sellerA = User::factory()->create(['name'=>'Alpha Seller','is_active'=>true]); $sellerA->assignRole('seller');
        $profileA = SellerProfile::create(['user_id'=>$sellerA->id,'status'=>'approved']);
        $alpha = Store::create(['user_id'=>$sellerA->id,'seller_profile_id'=>$profileA->id,'name'=>'Alpha Shop','slug'=>'alpha-products-shop','status'=>'active']);

        $sellerB = User::factory()->create(['name'=>'Beta Seller','is_active'=>true]); $sellerB->assignRole('seller');
        $profileB = SellerProfile::create(['user_id'=>$sellerB->id,'status'=>'approved']);
        $beta = Store::create(['user_id'=>$sellerB->id,'seller_profile_id'=>$profileB->id,'name'=>'Beta Shop','slug'=>'beta-products-shop','status'=>'active']);
        $category = Category::create(['name'=>'Grouped Products','slug'=>'grouped-products','is_active'=>true]);

        return [$admin, $sellerA, $alpha, $sellerB, $beta, $category];
    }
}
