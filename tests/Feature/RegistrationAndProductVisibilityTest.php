<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Address;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class RegistrationAndProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer'=>'Buyer','seller'=>'Seller','admin'=>'Admin','super_admin'=>'Super Admin'] as $slug=>$name) {
            Role::create(['name'=>$name,'slug'=>$slug,'guard_name'=>'web']);
        }
    }

    public function test_registration_requires_mobile_and_address_then_creates_default_address(): void
    {
        $base = ['name'=>'New Buyer','email'=>'new@buyer.test','password'=>'password','password_confirmation'=>'password','terms'=>'1'];
        $this->post(route('register.submit'), $base)
            ->assertSessionHasErrors(['phone','address_line','barangay','city','province','postal_code']);

        $this->post(route('register.submit'), $base + [
            'phone'=>'0917 123 4567','address_line'=>'12 Market Street','barangay'=>'Central',
            'city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH',
        ])->assertRedirect(route('home'));

        $user = User::where('email', 'new@buyer.test')->firstOrFail();
        $this->assertSame('+639171234567', $user->phone);
        $this->assertTrue($user->isBuyer());
        $this->assertDatabaseHas('addresses', ['user_id'=>$user->id,'is_default'=>true,'country'=>'PH']);
    }

    public function test_public_registration_choice_and_pending_seller_approval_flow(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Register as Buyer')->assertSee('Register as Seller')->assertDontSee('Register as Admin');
        $data=['name'=>'New Seller','email'=>'new@seller.test','phone'=>'09171234567','password'=>'password','password_confirmation'=>'password','address_line'=>'1 Seller Street','barangay'=>'Central','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH','store_name'=>'New Seller Shop','store_description'=>'Useful products from a new local store.','same_address'=>'1','seller_terms'=>'1'];
        $this->post(route('register.seller.submit'),$data)->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();
        $seller=User::where('email','new@seller.test')->firstOrFail();
        $this->assertTrue($seller->isBuyer());$this->assertFalse($seller->isSeller());
        $this->assertDatabaseHas('seller_profiles',['user_id'=>$seller->id,'status'=>'pending']);
        $this->assertDatabaseHas('seller_applications',['user_id'=>$seller->id,'store_name'=>'New Seller Shop','status'=>'pending']);
        $this->assertDatabaseHas('stores',['user_id'=>$seller->id,'name'=>'New Seller Shop','status'=>'pending']);
        $this->get(route('seller.apply'))->assertOk()->assertSee('Pending Admin review.');

        $this->post(route('logout'));
        $this->post(route('register.seller.submit'),$data)->assertSessionHasErrors('email');
        $this->assertSame(1,User::where('email','new@seller.test')->count());

        $approve=\App\Models\Permission::firstOrCreate(['slug'=>'approve_shops'],['name'=>'Approve Shops','group'=>'Shops','guard_name'=>'web']);
        $manageSellers=\App\Models\Permission::firstOrCreate(['slug'=>'manage_sellers'],['name'=>'Manage Sellers','group'=>'Sellers','guard_name'=>'web']);
        $adminRole=Role::where('slug','admin')->firstOrFail();$adminRole->permissions()->attach([$approve->id,$manageSellers->id]);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $application=SellerApplication::where('user_id',$seller->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.sellers.applications.review',$application),['status'=>'approved','review_notes'=>'Initial documents verified.'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$application->fresh()->status);
        $this->assertTrue($seller->fresh()->isSeller());
        $this->assertSame('approved',$seller->sellerProfile->fresh()->status);
        $this->assertSame('active',$seller->store->fresh()->status);
        $this->assertDatabaseHas('notifications_custom',['user_id'=>$seller->id,'title'=>'Your seller application has been approved.']);
    }

    public function test_new_public_seller_product_is_latest_searchable_and_visible_in_shop(): void
    {
        $seller = User::factory()->create(['is_active'=>true]); $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store = Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Mouse House','slug'=>'mouse-house','status'=>'active']);
        $category = Category::create(['name'=>'Electronics','slug'=>'electronics','is_active'=>true]);
        Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Older Product','slug'=>'older-product','price'=>100,'stock'=>2,'is_active'=>true,'moderation_status'=>'clean','publication_status'=>'published','created_at'=>now()->subDay()]);

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name'=>'Newest SHOPPICK Product','category_id'=>$category->id,'description'=>'New mouse',
            'price'=>499,'stock'=>20,'low_stock_threshold'=>5,'is_active'=>1,
        ])->assertRedirect(route('seller.products.index'));

        $product = Product::where('name', 'Newest SHOPPICK Product')->firstOrFail();
        $this->assertSame($store->id, $product->store_id);
        $this->assertSame('published', $product->publication_status);

        $this->travel(1)->minutes();
        $secondSeller = User::factory()->create(['is_active'=>true]); $secondSeller->assignRole('seller');
        $secondProfile = SellerProfile::create(['user_id'=>$secondSeller->id,'status'=>'approved']);
        $secondStore = Store::create(['user_id'=>$secondSeller->id,'seller_profile_id'=>$secondProfile->id,'name'=>'Fresh Finds','slug'=>'fresh-finds','status'=>'active']);
        $this->actingAs($secondSeller)->post(route('seller.products.store'), [
            'name'=>'Newest Across Sellers','category_id'=>$category->id,'description'=>'Newest valid product',
            'price'=>699,'stock'=>8,'publication_action'=>'publish',
        ])->assertRedirect(route('seller.products.index'));
        $newestAcrossSellers = Product::where('name','Newest Across Sellers')->firstOrFail();

        Product::create(['store_id'=>$secondStore->id,'category_id'=>$category->id,'name'=>'Hidden Draft','slug'=>'hidden-draft','price'=>100,'stock'=>5,'is_active'=>false,'moderation_status'=>'clean','publication_status'=>'draft']);
        Product::create(['store_id'=>$secondStore->id,'category_id'=>$category->id,'name'=>'Awaiting Moderation','slug'=>'awaiting-moderation','price'=>100,'stock'=>5,'is_active'=>false,'moderation_status'=>'pending_scan','publication_status'=>'published']);

        $this->actingAs($seller);
        $this->get(route('home'))->assertOk()
            ->assertSee('Latest Products')
            ->assertSee('Fresh picks from SHOPPICK sellers')
            ->assertSeeInOrder(['Newest Across Sellers','Newest SHOPPICK Product','Older Product'])
            ->assertDontSee('Hidden Draft')
            ->assertDontSee('Awaiting Moderation')
            ->assertViewHas('latestProducts', function ($products) use ($newestAcrossSellers, $product) {
                return $products->first()?->is($newestAcrossSellers)
                    && $products->get(1)?->is($product)
                    && $products->every(fn ($listed) => $listed->is_active && $listed->publication_status === 'published');
            });
        $this->get(route('products.index',['q'=>'Newest SHOPPICK Product']))->assertOk()->assertSee($product->name);
        $this->get(route('products.index',['q'=>'SHOPPICK Product']))->assertOk()->assertSee($product->name);
        $this->get(route('products.category',$category->id))->assertRedirect(route('products.index',['category'=>$category->id]));
        $this->get(route('shops.show',$store->slug))->assertOk()->assertSee($product->name);
        $this->get(route('products.show',$product->slug))->assertOk()->assertSee($product->name);
    }

    public function test_logged_in_buyer_can_cart_and_buy_a_product_created_through_seller_form(): void
    {
        $seller = User::factory()->create(['email'=>'seller-cart@test.local','password'=>Hash::make('password'),'is_active'=>true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store = Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Seller Cart Store','slug'=>'seller-cart-store','status'=>'active']);
        $category = Category::create(['name'=>'Seller Cart Category','slug'=>'seller-cart-category','is_active'=>true]);

        $this->post(route('login.submit'),['email'=>$seller->email,'password'=>'password'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);
        $this->post(route('seller.products.store'),[
            'name'=>'Seller Cart Test','category_id'=>$category->id,'description'=>'Created from Seller Products',
            'price'=>100,'stock'=>10,'low_stock_threshold'=>2,'publication_action'=>'publish',
        ])->assertRedirect(route('seller.products.index'))->assertSessionHasNoErrors();

        $product = Product::where('name','Seller Cart Test')->firstOrFail();
        $this->assertSame($store->id,$product->store_id);
        $this->assertSame(0,$product->variants()->count());
        $this->assertTrue($product->is_active);
        $this->assertSame('published',$product->publication_status);
        $this->assertSame('clean',$product->moderation_status);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $buyer = User::factory()->create(['email'=>'buyer-cart@test.local','password'=>Hash::make('password'),'phone'=>'+639171234567','is_active'=>true]);
        $buyer->assignRole('buyer');
        Address::create(['user_id'=>$buyer->id,'full_name'=>'Cart Buyer','phone'=>$buyer->phone,'province'=>'Metro Manila','city'=>'Manila','barangay'=>'Central','postal_code'=>'1000','address_line'=>'1 Cart Street','is_default'=>true]);
        $this->post(route('login.submit'),['email'=>$buyer->email,'password'=>'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($buyer);

        $this->get(route('home'))->assertOk()
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('onclick="toggleWishlist(event)"', false)
            ->assertSee('onclick="quickAdd(event)"', false);
        $this->get(route('products.show',$product->slug))->assertOk()->assertSee('Seller Cart Test');
        $this->postJson(route('wishlist.toggle'),['product_id'=>$product->id])
            ->assertOk()->assertJson(['success'=>true,'added'=>true,'count'=>1]);
        $this->get(route('wishlist.index'))->assertOk()->assertSee('Seller Cart Test')->assertSee('Seller Cart Store')->assertSee('Buy Now');
        $this->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1])
            ->assertRedirect(route('cart.index'))->assertSessionHas('cart_toast','Product added to cart.');
        $this->assertAuthenticatedAs($buyer);
        $this->assertDatabaseHas('cart_items',['product_id'=>$product->id,'product_variant_id'=>null,'quantity'=>1]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Seller Cart Test')->assertSee('100.00');

        $this->post(route('buy-now'),['product_id'=>$product->id,'quantity'=>1])
            ->assertRedirect(route('checkout',['mode'=>'buy_now']));
        $this->get(route('checkout',['mode'=>'buy_now']))->assertOk()->assertSee('Seller Cart Test');
        $this->get(route('home'))->assertOk();
        $this->assertAuthenticatedAs($buyer);

        $address = $buyer->addresses()->firstOrFail();
        $this->post(route('checkout.store'), [
            'address_id'=>$address->id,'payment_method'=>'cod','checkout_mode'=>'buy_now',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = \App\Models\Order::where('user_id',$buyer->id)->latest('id')->firstOrFail();
        $sellerOrder = $order->sellerOrders()->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame($store->id,$sellerOrder->store_id);
        $this->assertSame($sellerOrder->id,$item->seller_order_id);
        $this->assertSame($product->id,$item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame(9,$product->fresh()->stock);
        $this->assertDatabaseHas('payments',['order_id'=>$order->id,'method'=>'cod']);

        $this->get(route('orders.show',$order->order_number))->assertOk()->assertSee('Seller Cart Test');
        $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk()->assertSee($sellerOrder->seller_order_number)->assertSee('Cart Buyer');
        $this->get(route('seller.orders.show',$sellerOrder))->assertOk()->assertSee('Seller Cart Test');

        $admin = User::factory()->create(['is_active'=>true]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.orders.show',$order))->assertOk()->assertSee('Seller Cart Test');
    }

    public function test_google_first_time_user_is_buyer_and_must_complete_profile(): void
    {
        $google = (new GoogleUser)->setRaw(['verified_email'=>true])->map([
            'id'=>'google-123','name'=>'Google Buyer','email'=>'google@buyer.test','avatar'=>null,
        ]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('profile.complete'));
        $user = User::where('email','google@buyer.test')->firstOrFail();
        $this->assertTrue($user->isBuyer());
        $this->assertFalse($user->isAdmin());
        $this->assertDatabaseHas('social_accounts',['user_id'=>$user->id,'provider'=>'google','provider_id'=>'google-123']);

        $this->post(route('profile.complete.update'), [
            'phone'=>'09171234567','address_line'=>'1 Google Street','barangay'=>'Web',
            'city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH',
        ])->assertRedirect(route('home'));
        $this->assertTrue($user->fresh()->hasCompleteBuyerProfile());
    }

    public function test_google_uses_existing_buyer_email_without_creating_a_duplicate(): void
    {
        $buyer = User::factory()->create(['email'=>'existing@gmail.com','phone'=>'+639171234567','is_active'=>true]);
        $buyer->assignRole('buyer');
        $buyer->addresses()->create(['full_name'=>$buyer->name,'phone'=>$buyer->phone,'address_line'=>'1 Existing Street','barangay'=>'Central','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH','is_default'=>true]);
        $google = (new GoogleUser)->setRaw(['verified_email'=>true])->map(['id'=>'google-existing','name'=>'Existing Buyer','email'=>'existing@gmail.com','avatar'=>null]);
        $provider = Mockery::mock(Provider::class); $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));
        $this->assertSame(1, User::where('email','existing@gmail.com')->count());
        $this->assertDatabaseHas('social_accounts',['user_id'=>$buyer->id,'provider_id'=>'google-existing']);
    }

    public function test_google_seller_must_complete_store_information_and_remains_pending(): void
    {
        $google=(new GoogleUser)->setRaw(['verified_email'=>true])->map(['id'=>'google-seller','name'=>'Google Seller','email'=>'google@seller.test','avatar'=>null]);
        $provider=Mockery::mock(Provider::class);$provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->withSession(['registration_type'=>'seller'])->get(route('auth.google.callback'))->assertRedirect(route('profile.complete.seller'));
        $this->post(route('profile.complete.seller.update'),['phone'=>'09171234567'])->assertSessionHasErrors(['address_line','store_name','store_description','seller_terms']);
        $this->post(route('profile.complete.seller.update'),['phone'=>'09171234567','address_line'=>'2 Google Street','barangay'=>'Web','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH','store_name'=>'Google Seller Shop','store_description'=>'A complete Google seller application.','same_address'=>'1','seller_terms'=>'1'])->assertRedirect(route('seller.apply'));
        $user=User::where('email','google@seller.test')->firstOrFail();
        $this->assertTrue($user->isBuyer());$this->assertFalse($user->isSeller());
        $this->assertDatabaseHas('seller_applications',['user_id'=>$user->id,'status'=>'pending']);
        $this->assertDatabaseHas('stores',['user_id'=>$user->id,'status'=>'pending']);
    }

    public function test_normal_role_login_and_logout_still_use_laravel_session(): void
    {
        foreach (['super_admin'=>'superadmin.dashboard','admin'=>'admin.dashboard','seller'=>'seller.dashboard','buyer'=>'home'] as $role=>$route) {
            $user = User::factory()->create(['email'=>$role.'@login.test','password'=>Hash::make('password'),'is_active'=>true]);
            $user->assignRole($role);
            $response = $this->post(route('login.submit'), ['email'=>$user->email,'password'=>'password']);
            $response->assertRedirect(route($route));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_cancelled_google_sign_in_returns_a_friendly_error(): void
    {
        $this->get(route('auth.google.callback',['error'=>'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);
    }
}
