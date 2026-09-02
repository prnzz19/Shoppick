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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_is_grouped_by_shop_with_combined_filters_for_admin_and_super_admin(): void
    {
        $permission=Permission::create(['name'=>'Moderate Products','slug'=>'moderate_products','group'=>'Moderation','guard_name'=>'web']);
        $adminRole=Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);$adminRole->permissions()->attach($permission);
        $superRole=Role::create(['name'=>'Super Admin','slug'=>'super_admin','guard_name'=>'web']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $super=User::factory()->create(['is_active'=>true]);$super->assignRole('super_admin');
        $alphaSeller=User::factory()->create(['name'=>'Alpha Seller']);
        $betaSeller=User::factory()->create(['name'=>'Beta Seller']);
        $pendingSeller=User::factory()->create(['name'=>'Pending Seller']);
        $alphaProfile=SellerProfile::create(['user_id'=>$alphaSeller->id,'status'=>'approved']);
        $betaProfile=SellerProfile::create(['user_id'=>$betaSeller->id,'status'=>'approved']);
        $pendingProfile=SellerProfile::create(['user_id'=>$pendingSeller->id,'status'=>'pending']);
        $alpha=Store::create(['user_id'=>$alphaSeller->id,'seller_profile_id'=>$alphaProfile->id,'name'=>'Alpha Shop','slug'=>'alpha-shop','status'=>'active']);
        $beta=Store::create(['user_id'=>$betaSeller->id,'seller_profile_id'=>$betaProfile->id,'name'=>'Beta Shop','slug'=>'beta-shop','status'=>'active']);
        $pendingStore=Store::create(['user_id'=>$pendingSeller->id,'seller_profile_id'=>$pendingProfile->id,'name'=>'Pending Shop','slug'=>'pending-shop','status'=>'pending']);
        $category=Category::create(['name'=>'General','slug'=>'general','is_active'=>true]);
        $pendingProduct=Product::create(['store_id'=>$alpha->id,'category_id'=>$category->id,'name'=>'Orange Chair','slug'=>'orange-chair','price'=>100,'stock'=>4]);
        $approvedProduct=Product::create(['store_id'=>$alpha->id,'category_id'=>$category->id,'name'=>'Clean Desk','slug'=>'clean-desk','price'=>200,'stock'=>3]);
        $flaggedProduct=Product::create(['store_id'=>$beta->id,'category_id'=>$category->id,'name'=>'Flagged Lamp','slug'=>'flagged-lamp','price'=>300,'stock'=>2]);
        $pendingShopProduct=Product::create(['store_id'=>$pendingStore->id,'category_id'=>$category->id,'name'=>'Pending Shop Draft','slug'=>'pending-shop-draft','price'=>50,'stock'=>2,'publication_status'=>'draft','is_active'=>false]);

        $pending=$this->scan($pendingProduct,$alpha,$alphaSeller,'pending_scan');
        $this->scan($approvedProduct,$alpha,$alphaSeller,'approved');
        $flagged=$this->scan($flaggedProduct,$beta,$betaSeller,'flagged');
        $this->scan($pendingShopProduct,$pendingStore,$pendingSeller,'pending_scan');

        $this->actingAs($admin)->get(route('admin.moderation.index'))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Seller: Alpha Seller')->assertSee('Beta Shop')
            ->assertSee('2 moderated products')->assertSee('Expand All')->assertSee('Collapse All')
            ->assertDontSee('Pending Shop Draft')->assertDontSee('Seller: Pending Seller')
            ->assertSee('aria-controls="shop-moderation-'.$alpha->id.'"',false)
            ->assertSee(route('admin.moderation.show',$pending));

        $this->get(route('admin.moderation.index',['shop'=>$beta->id,'status'=>'flagged']))->assertOk()
            ->assertSee('Beta Shop')->assertSee('Flagged Lamp')->assertDontSee('Seller: Alpha Seller')
            ->assertSee("x-show=\"open['{$beta->id}']\"",false);
        $this->get(route('admin.moderation.index',['q'=>'Orange Chair']))->assertOk()
            ->assertSee('Alpha Shop')->assertSee('Orange Chair')->assertDontSee('Seller: Beta Seller');

        $this->actingAs($super)->get(route('superadmin.moderation.index',['status'=>'flagged']))->assertOk()
            ->assertSee('Beta Shop')->assertSee(route('superadmin.moderation.show',$flagged));

        $flaggedProduct->update(['publication_status'=>'published','is_active'=>false]);
        $betaProfile->update(['status'=>'pending']);
        $this->post(route('superadmin.moderation.review',$flagged),['decision'=>'approved','notes'=>'The image itself is acceptable.'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$flaggedProduct->fresh()->moderation_status);
        $this->assertFalse($flaggedProduct->fresh()->is_active);
    }

    private function scan(Product $product, Store $store, User $seller, string $status): ModerationScan
    {
        $image=ProductImage::create(['product_id'=>$product->id,'path'=>'products/'.$product->slug.'.jpg','is_primary'=>true]);
        return ModerationScan::create(['product_id'=>$product->id,'product_image_id'=>$image->id,'seller_id'=>$seller->id,'store_id'=>$store->id,'status'=>$status,'provider'=>'test','risk_level'=>$status==='flagged'?'high':'low']);
    }
}
