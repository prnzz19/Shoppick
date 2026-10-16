<?php

namespace Tests\Feature;

use App\Models\{Address, Category, Order, Product, Role, SellerProfile, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private Store $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name'=>'Buyer','slug'=>'buyer','guard_name'=>'web']);
        $this->buyer = User::factory()->create(['is_active'=>true]);
        $this->buyer->assignRole('buyer');
        $seller = User::factory()->create(['is_active'=>true]);
        $profile = SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $this->store = Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Test Shop','slug'=>'test-shop','status'=>'active']);
        $category = Category::create(['name'=>'Electronics','slug'=>'electronics','is_active'=>true]);
        $this->product = Product::create(['store_id'=>$this->store->id,'category_id'=>$category->id,'name'=>'Headphones','slug'=>'headphones','price'=>100,'stock'=>10,'is_active'=>true,'moderation_status'=>'clean']);
        DB::table('mobile_api_tokens')->insert(['user_id'=>$this->buyer->id,'name'=>'test','token_hash'=>hash('sha256','mobile-test'),'created_at'=>now(),'updated_at'=>now()]);
        $this->withToken('mobile-test');
    }

    public function test_catalog_exposes_real_shop_and_hides_inactive_shops(): void
    {
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.data.0.store.name','Test Shop');
        $this->getJson('/api/v1/shops/test-shop')->assertOk()->assertJsonPath('products.total',1);
        $this->getJson('/api/v1/products?q=Test%20Shop')->assertOk()->assertJsonPath('data.total',1);
        $this->store->update(['status'=>'suspended']);
        $this->getJson('/api/v1/shops/test-shop')->assertNotFound();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.total',0);
    }

    public function test_parent_category_includes_active_children(): void
    {
        $parentId = $this->product->category_id;
        $child = Category::create(['name'=>'Audio','slug'=>'audio','parent_id'=>$parentId,'is_active'=>true]);
        $this->product->update(['category_id'=>$child->id]);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('0.children.0.name','Audio');
        $this->getJson('/api/v1/products?category='.$parentId)->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/v1/products?category='.$child->id)->assertOk()->assertJsonPath('data.total',1);
    }

    public function test_checkout_preview_is_read_only_and_matches_placed_order(): void
    {
        $cart = $this->postJson('/api/v1/cart',['product_id'=>$this->product->id,'quantity'=>2])->assertOk();
        $itemId = $cart->json('items.0.id');
        $this->patchJson('/api/v1/cart/'.$itemId,['selected'=>false])->assertOk()->assertJsonPath('subtotal',0);
        $this->patchJson('/api/v1/cart/'.$itemId,['selected'=>true])->assertOk()->assertJsonPath('subtotal',200);
        $preview = $this->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('totals.total',250);
        $this->assertDatabaseCount('orders',0);
        $this->assertSame(10, $this->product->fresh()->stock);
        $address = Address::create(['user_id'=>$this->buyer->id,'full_name'=>'Test Buyer','phone'=>'09171234567','address_line'=>'123 Test Street','barangay'=>'Test','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH','is_default'=>true]);
        $response = $this->postJson('/api/v1/checkout',['address_id'=>$address->id,'payment_method'=>'cod'])->assertCreated();
        $number = $response->json('order.order_number');
        $this->assertEquals($preview->json('totals.total'), $response->json('order.total'));
        $this->getJson('/api/v1/orders/'.$number)->assertOk()->assertJsonPath('shops.0.shop.name','Test Shop')->assertJsonPath('items.0.product_name','Headphones')->assertJsonPath('progress.0.status','pending');
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.order_number',$number);
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('cart_count',0);
    }

    public function test_order_and_cart_access_are_scoped_to_token_owner(): void
    {
        $other = User::factory()->create(['is_active'=>true]);
        $order = Order::create(['user_id'=>$other->id,'order_number'=>'PRIVATE-ORDER','status'=>'pending','payment_method'=>'cod','payment_status'=>'unpaid','subtotal'=>100,'shipping_fee'=>50,'total'=>150]);
        $this->getJson('/api/v1/orders/'.$order->order_number)->assertNotFound();
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('total',0);
        $this->withHeaders(['Authorization'=>''])->getJson('/api/v1/checkout')->assertUnauthorized();
    }

    public function test_seller_access_uses_the_existing_website_eligibility_rule(): void
    {
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $this->buyer->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$this->buyer->id,'status'=>'pending']);
        $store = Store::create(['user_id'=>$this->buyer->id,'seller_profile_id'=>$profile->id,'name'=>'Buyer Shop','slug'=>'buyer-shop','status'=>'active']);
        foreach (['pending','needs_resubmission','rejected','approved'] as $status) {
            $profile->update(['status'=>$status]);
            $this->getJson('/api/v1/seller/application')->assertOk()->assertJsonPath('seller_access', $status === 'approved');
        }
        foreach (['pending','suspended','restricted','inactive'] as $status) {
            $store->update(['status'=>$status]);
            $this->getJson('/api/v1/seller/application')->assertOk()->assertJsonPath('seller_access',false);
        }
        $store->update(['status'=>'active']);
        $this->getJson('/api/v1/seller/application')->assertOk()->assertJsonPath('seller_access',true);
        $this->assertTrue($this->buyer->fresh()->isBuyer());
        $this->buyer->update(['is_active'=>false]);
        $this->getJson('/api/v1/seller/application')->assertUnauthorized();
    }

    public function test_mobile_application_documents_optional_logo_and_resubmission(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $payload = fn()=>[
            'store_name'=>'Buyer Shop','phone'=>'09171234567','address'=>'Manila',
            'category_id'=>$this->product->category_id,
            'valid_id'=>\Illuminate\Http\UploadedFile::fake()->create('id.pdf',20,'application/pdf'),
            'business_permit'=>\Illuminate\Http\UploadedFile::fake()->create('permit.pdf',20,'application/pdf'),
        ];
        $this->getJson('/api/v1/seller/application')->assertOk()->assertJsonPath('application',null);
        $this->postJson('/api/v1/seller/application',[])->assertUnprocessable()->assertJsonValidationErrors(['valid_id','business_permit']);
        $this->postJson('/api/v1/seller/application',$payload())->assertCreated()->assertJsonPath('application.logo',null)->assertJsonPath('application.status','pending');
        $application = $this->buyer->sellerApplications()->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($application->valid_id_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($application->business_permit_path);
        $this->postJson('/api/v1/seller/application',$payload())->assertUnprocessable();
        foreach (['needs_resubmission','rejected'] as $status) {
            $application->update(['status'=>$status]);
            $this->postJson('/api/v1/seller/application',$payload())->assertCreated()->assertJsonPath('application.status','pending');
            $this->assertDatabaseCount('seller_applications',1);
        }
        $application->update(['status'=>'needs_resubmission']);
        $withLogo = $payload();
        $withLogo['logo'] = \Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
        $this->postJson('/api/v1/seller/application',$withLogo)->assertCreated();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($application->fresh()->logo);
    }

    public function test_cart_quantities_selection_and_foreign_address_are_scoped(): void
    {
        $id = $this->postJson('/api/v1/cart',['product_id'=>$this->product->id,'quantity'=>1])->json('items.0.id');
        $this->patchJson('/api/v1/cart/'.$id,['quantity'=>3])->assertOk()->assertJsonPath('subtotal',300);
        $this->patchJson('/api/v1/cart/'.$id,['quantity'=>2])->assertOk()->assertJsonPath('subtotal',200);
        $this->patchJson('/api/v1/cart/'.$id,['selected'=>false])->assertOk()->assertJsonPath('subtotal',0);
        $this->getJson('/api/v1/checkout')->assertUnprocessable();
        $this->patchJson('/api/v1/cart/'.$id,['selected'=>true])->assertOk();
        $this->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('items.0.id',$id)->assertJsonPath('items.0.quantity',2);
        $other = User::factory()->create(['is_active'=>true]);
        $foreign = app(\App\Services\CartService::class)->getOrCreateCart($other->id)->items()->create(['product_id'=>$this->product->id,'quantity'=>1,'selected'=>true]);
        $this->patchJson('/api/v1/cart/'.$foreign->id,['selected'=>false])->assertNotFound();
        $this->deleteJson('/api/v1/cart/'.$foreign->id)->assertOk();
        $this->assertNotNull($foreign->fresh());
        $address = Address::create(['user_id'=>$other->id,'full_name'=>'Other','phone'=>'09171234567','address_line'=>'Private','barangay'=>'Test','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH']);
        $this->postJson('/api/v1/checkout',['address_id'=>$address->id,'payment_method'=>'cod'])->assertNotFound();
        $this->assertDatabaseCount('orders',0);
        $this->assertSame(10,$this->product->fresh()->stock);
        $this->deleteJson('/api/v1/cart/'.$id)->assertOk()->assertJsonPath('cart_count',0);
    }

    public function test_login_profile_and_logout_revoke_only_the_current_token(): void
    {
        $response = $this->postJson('/api/v1/login',['email'=>$this->buyer->email,'password'=>'password'])->assertCreated();
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->assertJsonPath('user.id',$this->buyer->id);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->withToken('mobile-test')->getJson('/api/v1/profile')->assertOk();
    }

    public function test_product_pagination_has_stable_unique_results_and_search(): void
    {
        for ($i=0; $i<24; $i++) {
            $copy = $this->product->replicate();
            $copy->name = 'Headphones '.$i;
            $copy->slug = 'headphones-'.$i;
            $copy->save();
        }
        $first = $this->getJson('/api/v1/products?q=Headphones')->assertOk()->assertJsonPath('data.total',25)->json('data.data');
        $second = $this->getJson('/api/v1/products?q=Headphones&page=2')->assertOk()->json('data.data');
        $this->assertCount(25,array_unique(array_column(array_merge($first,$second),'id')));
        $this->getJson('/api/v1/products?q=missing-product')->assertOk()->assertJsonPath('data.total',0);
        $this->getJson('/api/v1/products/'.$this->product->id)->assertOk()->assertJsonPath('id',$this->product->id);
    }

    public function test_preview_and_order_use_selected_items_across_shops_only(): void
    {
        $seller = User::factory()->create(['is_active'=>true]);
        $profile = SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $shop = Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Second Shop','slug'=>'second','status'=>'active']);
        $second = $this->product->replicate();
        $second->fill(['store_id'=>$shop->id,'slug'=>'second-product']);
        $second->save();
        $unselected = $this->product->replicate();
        $unselected->slug = 'unselected';
        $unselected->save();
        foreach ([$this->product,$second,$unselected] as $product) {
            $this->postJson('/api/v1/cart',['product_id'=>$product->id,'quantity'=>1])->assertOk();
        }
        $id = $this->getJson('/api/v1/cart')->json('items.2.id');
        $this->patchJson('/api/v1/cart/'.$id,['selected'=>false])->assertOk();
        $this->getJson('/api/v1/checkout')->assertOk()->assertJsonCount(2,'items')->assertJsonCount(2,'totals.seller_breakdown')->assertJsonPath('totals.total',250);
        $this->assertDatabaseCount('orders',0);
        $address = Address::create(['user_id'=>$this->buyer->id,'full_name'=>'Buyer','phone'=>'09171234567','address_line'=>'Test','barangay'=>'Test','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH']);
        $this->postJson('/api/v1/checkout',['address_id'=>$address->id,'payment_method'=>'cod'])->assertCreated()->assertJsonCount(2,'order.items');
        $this->assertDatabaseCount('seller_orders',2);
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(1,'items')->assertJsonPath('items.0.product.id',$unselected->id);
        $this->assertSame(10,$unselected->fresh()->stock);
        $this->assertSame(9,$second->fresh()->stock);
    }
}
