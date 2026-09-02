<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_is_grouped_by_shop_and_supports_empty_shops_and_combined_filters(): void
    {
        $permission=Permission::create(['name'=>'Manage Inventory','slug'=>'manage_inventory','group'=>'Products','guard_name'=>'web']);
        $adminRole=Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);
        $adminRole->permissions()->attach($permission);
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $sellerA=User::factory()->create(['name'=>'Alpha Seller']);$sellerA->assignRole('seller');
        $sellerB=User::factory()->create(['name'=>'Beta Seller']);$sellerB->assignRole('seller');
        $sellerC=User::factory()->create(['name'=>'Empty Seller']);$sellerC->assignRole('seller');
        $alpha=Store::create(['user_id'=>$sellerA->id,'name'=>'Alpha Shop','slug'=>'alpha-shop','status'=>'active']);
        $beta=Store::create(['user_id'=>$sellerB->id,'name'=>'Beta Shop','slug'=>'beta-shop','status'=>'active']);
        $empty=Store::create(['user_id'=>$sellerC->id,'name'=>'Empty Shop','slug'=>'empty-shop','status'=>'active']);
        $category=Category::create(['name'=>'General','slug'=>'general','is_active'=>true]);
        $healthy=Product::create(['store_id'=>$alpha->id,'category_id'=>$category->id,'name'=>'Healthy Item','slug'=>'healthy-item','sku'=>'ALPHA-10','price'=>100,'stock'=>10,'low_stock_threshold'=>2,'is_active'=>true]);
        $low=Product::create(['store_id'=>$alpha->id,'category_id'=>$category->id,'name'=>'Low Item','slug'=>'low-item','sku'=>'ALPHA-LOW','price'=>100,'stock'=>3,'low_stock_threshold'=>5,'is_active'=>true]);
        $out=Product::create(['store_id'=>$beta->id,'category_id'=>$category->id,'name'=>'Out Item','slug'=>'out-item','sku'=>'BETA-OUT','price'=>100,'stock'=>0,'low_stock_threshold'=>5,'is_active'=>true]);

        $this->actingAs($admin)->get(route('admin.inventory.index'))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Seller: Alpha Seller')->assertSee('Beta Shop')
            ->assertSee('Empty Shop')->assertSee('No inventory items yet.')->assertSee('Healthy Item')->assertSee('Out Item')
            ->assertSee('Expand All')->assertSee('Collapse All')->assertSee('aria-controls="shop-inventory-'.$alpha->id.'"',false)
            ->assertSee('x-show="open['.$alpha->id.']"',false)->assertSee('type="button"',false);

        $this->get(route('admin.inventory.index',['shop'=>$alpha->id,'scope'=>'low']))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Low Item')->assertDontSee('Healthy Item')->assertDontSee('Seller: Beta Seller');
        $this->get(route('admin.inventory.index',['scope'=>'out']))->assertOk()
            ->assertSee('Beta Shop')->assertSee('Out Item')->assertDontSee('Low Item')->assertDontSee('Seller: Empty Seller');
        $this->get(route('admin.inventory.index',['q'=>'Beta Seller']))->assertOk()
            ->assertSee('Beta Shop')->assertSee('Out Item')->assertDontSee('Seller: Alpha Seller');

        $this->post(route('admin.inventory.stock',$healthy),['stock'=>7])->assertSessionHasNoErrors();
        $this->assertSame(7,$healthy->fresh()->stock);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'inventory.updated','target_id'=>$healthy->id]);
    }
}
