<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_seller_can_edit_inactive_own_product_without_duplication(): void
    {
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $seller=User::factory()->create(['is_active'=>true]); $seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Orange Store','slug'=>'orange-store','status'=>'active']);
        $category=Category::create(['name'=>'Fruit','slug'=>'fruit','is_active'=>true]);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'orens','slug'=>'orens','price'=>95,'stock'=>5,'is_active'=>false,'moderation_status'=>'clean','publication_status'=>'draft']);
        $variant=$product->variants()->create(['type'=>'Color','value'=>'Orange','sku'=>'ORENS-ORG','price'=>95,'stock'=>5]);

        $this->actingAs($seller)->get(route('seller.products.edit',$product))
            ->assertOk()->assertSee('orens')->assertSee('95.00')->assertSee('ORENS-ORG');

        $this->put(route('seller.products.update',$product), [
            'name'=>'Orange','category_id'=>$category->id,'price'=>100,'stock'=>10,
            'low_stock_threshold'=>2,'description'=>'Fresh orange','is_active'=>1,
        ])->assertRedirect(route('seller.products.index'))->assertSessionHas('success','Product updated successfully.');

        $this->assertDatabaseCount('products',1);
        $this->assertDatabaseHas('products',['id'=>$product->id,'store_id'=>$store->id,'name'=>'Orange','price'=>100,'stock'=>10,'is_active'=>true,'publication_status'=>'published']);
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'product_id'=>$product->id,'sku'=>'ORENS-ORG','stock'=>5]);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'seller.product.updated','target_id'=>$product->id]);
        $this->get(route('products.index',['q'=>'Orange']))->assertOk()->assertSee('Orange');
    }

    public function test_seller_activation_requires_clean_moderation_and_makes_product_public(): void
    {
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $seller=User::factory()->create(['is_active'=>true]); $seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Publish Store','slug'=>'publish-store','status'=>'active']);
        $category=Category::create(['name'=>'Food','slug'=>'food','is_active'=>true]);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'New SHOPPICK Orange','slug'=>'new-shoppick-orange','price'=>100,'stock'=>10,'is_active'=>false,'moderation_status'=>'pending_scan','publication_status'=>'published']);

        $this->actingAs($seller)->post(route('seller.products.publication',$product),['action'=>'activate'])
            ->assertSessionHas('error','This product is currently under review.');
        $this->assertFalse($product->fresh()->is_active);

        $product->update(['moderation_status'=>'clean']);
        $this->post(route('seller.products.publication',$product),['action'=>'activate'])
            ->assertSessionHas('success','Product activated successfully.');
        $this->assertTrue($product->fresh()->is_active);
        $this->get(route('home'))->assertOk()->assertSee('New SHOPPICK Orange');
        $this->get(route('products.index',['q'=>'Orange']))->assertOk()->assertSee('New SHOPPICK Orange');
        $this->get(route('shops.show',$store->slug))->assertOk()->assertSee('New SHOPPICK Orange');
        $this->get(route('products.show',$product->slug))->assertOk()->assertSee('New SHOPPICK Orange');

        $this->post(route('seller.products.publication',$product),['action'=>'deactivate'])
            ->assertSessionHas('success','Product deactivated successfully.');
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_archived_product_has_its_own_tab_is_hidden_from_buyers_and_restores_as_draft(): void
    {
        [$seller, $store] = $this->sellerAndStore('Archive Store', 'archive-store');
        $category = Category::create(['name'=>'Archive Test','slug'=>'archive-test','is_active'=>true]);
        $product = Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Archived Camera','slug'=>'archived-camera','price'=>500,'stock'=>3,'is_active'=>true,'moderation_status'=>'clean','publication_status'=>'published']);

        $this->actingAs($seller)->delete(route('seller.products.destroy',$product))->assertSessionHas('success','Product archived.');
        $this->assertSoftDeleted('products',['id'=>$product->id]);

        $this->get(route('seller.products.index',['status'=>'archived']))
            ->assertOk()->assertSee('Archived Camera')->assertSee('Archived date')->assertSee('Restore')->assertSee('Delete Permanently');
        $this->get(route('seller.products.archived.show',$product->id))->assertOk()->assertSee('Archived Camera')->assertSee('Restore as Draft');
        $this->get(route('seller.products.index'))->assertOk()->assertDontSee('Archived Camera');

        $superAdmin = User::factory()->create(['is_active'=>true]); $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin)->get(route('admin.products.index',['status'=>'archived']))
            ->assertOk()->assertSee('Archived Camera')->assertSee('Archived');

        $buyer = User::factory()->create(['is_active'=>true]); $buyer->assignRole('buyer');
        $this->actingAs($buyer)->get(route('home'))->assertOk()->assertDontSee('Archived Camera');
        $this->get(route('products.index',['q'=>'Archived Camera']))->assertOk()->assertSee('(0)')->assertDontSee(route('products.show',$product->slug),false);
        $this->followingRedirects()->get(route('products.category',$category))->assertOk()->assertDontSee('Archived Camera');
        $this->get(route('shops.show',$store->slug))->assertOk()->assertDontSee('Archived Camera');
        $this->get(route('products.show',$product->slug))->assertNotFound();
        $this->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1])->assertSessionHas('error','This product is currently unavailable.');
        $this->post(route('buy-now'),['product_id'=>$product->id,'quantity'=>1])->assertSessionHas('error','This product is currently unavailable.');

        $this->actingAs($seller)->post(route('seller.products.restore',$product->id))
            ->assertRedirect(route('seller.products.index',['status'=>'draft']))
            ->assertSessionHas('success');
        $restored = Product::findOrFail($product->id);
        $this->assertFalse($restored->is_active);
        $this->assertSame('draft',$restored->publication_status);
        $this->get(route('seller.products.edit',$restored))->assertOk();
    }

    public function test_archived_product_without_order_history_can_be_permanently_deleted(): void
    {
        [$seller, $store] = $this->sellerAndStore('Delete Store', 'delete-store');
        $category = Category::create(['name'=>'Delete Test','slug'=>'delete-test','is_active'=>true]);
        $product = Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Delete Me','slug'=>'delete-me','price'=>20,'stock'=>1,'is_active'=>false,'moderation_status'=>'clean','publication_status'=>'draft']);
        $product->delete();

        $this->actingAs($seller)->delete(route('seller.products.force-destroy',$product->id))
            ->assertRedirect(route('seller.products.index',['status'=>'archived']))
            ->assertSessionHas('success','Product permanently deleted.');
        $this->assertDatabaseMissing('products',['id'=>$product->id]);
    }

    public function test_product_with_order_history_cannot_be_permanently_deleted(): void
    {
        [$seller, $store] = $this->sellerAndStore('History Store', 'history-store');
        $buyer = User::factory()->create(['is_active'=>true]); $buyer->assignRole('buyer');
        $category = Category::create(['name'=>'History Test','slug'=>'history-test','is_active'=>true]);
        $product = Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Ordered Product','slug'=>'ordered-product','price'=>20,'stock'=>1,'is_active'=>false,'moderation_status'=>'clean','publication_status'=>'draft']);
        $order = Order::create(['user_id'=>$buyer->id,'order_number'=>'ORD-HISTORY-1','payment_method'=>'cod','subtotal'=>20,'total'=>20]);
        OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_name'=>$product->name,'price'=>20,'quantity'=>1,'total'=>20]);
        $product->delete();

        $this->actingAs($seller)->delete(route('seller.products.force-destroy',$product->id))
            ->assertSessionHas('error','This product has order history and cannot be permanently deleted.');
        $this->assertSoftDeleted('products',['id'=>$product->id]);
        $this->get(route('seller.products.index',['status'=>'archived']))->assertOk()->assertSee('Ordered Product')->assertDontSee('Delete Permanently');
    }

    private function sellerAndStore(string $name, string $slug): array
    {
        $seller = User::factory()->create(['is_active'=>true]); $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store = Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>$slug,'status'=>'active']);
        return [$seller,$store];
    }
}
