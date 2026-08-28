<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_apply_to_be_a_seller(): void
    {
        Role::create(['name'=>'Buyer','slug'=>'buyer','guard_name'=>'web']);
        $buyer=User::factory()->create(); $buyer->assignRole('buyer');
        $this->actingAs($buyer)->post(route('seller.apply.store'), [
            'store_name'=>'Test Store','phone'=>'09171234567','address'=>'Manila',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_applications',['user_id'=>$buyer->id,'status'=>'pending']);
    }

    public function test_seller_cannot_edit_another_sellers_product(): void
    {
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $first=User::factory()->create(['is_active' => true]); $first->assignRole('seller');
        $second=User::factory()->create(['is_active' => true]); $second->assignRole('seller');
        foreach([$first,$second] as $i=>$user){ $profile=SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']); Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>'Store '.$i,'slug'=>'store-'.$i]); }
        $category=Category::create(['name'=>'Test','slug'=>'test']);
        $product=Product::create(['store_id'=>$second->store->id,'category_id'=>$category->id,'name'=>'Private','slug'=>'private','price'=>10,'stock'=>1]);
        $this->actingAs($first)->get(route('seller.products.edit',$product))->assertForbidden();
    }

    public function test_seller_center_main_pages_are_accessible(): void
    {
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $seller=User::factory()->create(['is_active'=>true]); $seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Test Store','slug'=>'test-store']);
        foreach (['seller.dashboard','seller.orders.index','seller.products.index','seller.inventory.index','seller.marketing.index','seller.reviews.index','seller.sales.index','seller.store.edit','seller.notifications.index','seller.settings.index'] as $route) {
            $this->actingAs($seller)->get(route($route))->assertOk();
        }
    }
}
