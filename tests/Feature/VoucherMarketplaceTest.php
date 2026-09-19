<?php
namespace Tests\Feature;
use App\Models\{Address,Category,Permission,Product,Role,SellerProfile,Store,User,Voucher};
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class VoucherMarketplaceTest extends TestCase {
 use RefreshDatabase;
 public function test_admin_platform_free_shipping_stacks_with_shop_voucher_and_supports_future_shops():void {
  foreach(['buyer','seller','admin'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);$permission=Permission::create(['name'=>'Manage Promotions','slug'=>'manage_promotions','guard_name'=>'web','group'=>'Admin']);Role::where('slug','admin')->first()->permissions()->attach($permission);
  $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');$buyer=User::factory()->create(['is_active'=>true,'phone'=>'+639171111111']);$buyer->assignRole('buyer');[$seller,$shop]=$this->seller('Platform Test Shop','platform-test-shop');
  $category=Category::create(['name'=>'Platform Products','slug'=>'platform-products']);$product=$this->product($shop,$category,'Platform Item',150);$address=Address::create(['user_id'=>$buyer->id,'full_name'=>'Buyer','phone'=>'09171111111','province'=>'Laguna','city'=>'Cavinti','barangay'=>'Mahipon','postal_code'=>'4013','address_line'=>'1 Main St','is_default'=>true]);
  $shopVoucher=$shop->vouchers()->create(['source_type'=>'shop','code'=>'SHOP10','title'=>'Shop 10%','type'=>'percent','value'=>10,'min_purchase'=>100,'max_discount'=>100,'per_user_limit'=>1,'status'=>'active']);
  $this->actingAs($admin)->post(route('admin.promotions.store'),['code'=>'PLATFORMFREE','title'=>'SHOPPICK Free Shipping','type'=>'free_shipping','min_purchase'=>100,'max_discount'=>50,'usage_limit'=>500,'per_user_limit'=>3,'status'=>'active'])->assertRedirect(route('admin.promotions.index'))->assertSessionHasNoErrors();
  $platform=Voucher::where('code','PLATFORMFREE')->firstOrFail();$this->assertSame('platform',$platform->source_type);$this->assertNull($platform->store_id);$this->assertSame('all_shops',$platform->platform_scope);$this->assertSame($admin->id,$platform->created_by);
  $this->actingAs($buyer)->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1]);$this->get(route('checkout'))->assertOk()->assertSee('Shop Vouchers: 1')->assertSee('Platform Vouchers: 1')->assertSee('PLATFORMFREE')->assertSee('Platform Voucher');
  $items=$buyer->cart->items()->with(['product','variant'])->get();$quote=app(OrderService::class)->computeTotals($buyer->id,[$shopVoucher->code,$platform->code],$items);$this->assertEquals(15,$quote['shop_discount']);$this->assertEquals(50,$quote['platform_shipping_discount']);$this->assertEquals(135,$quote['total']);
  $secondPlatform=Voucher::create(['source_type'=>'platform','platform_scope'=>'all_shops','code'=>'PLATFORM10','title'=>'Platform 10%','type'=>'percent','value'=>10,'min_purchase'=>0,'per_user_limit'=>1,'status'=>'active']);try{app(OrderService::class)->computeTotals($buyer->id,[$platform->code,$secondPlatform->code],$items);$this->fail('Two Platform vouchers were stacked.');}catch(\Exception $e){$this->assertStringContainsString('one Platform Voucher',$e->getMessage());}
  $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'cod','voucher_codes'=>[$shopVoucher->code,$platform->code]])->assertSessionHasNoErrors();$order=$buyer->orders()->latest('id')->firstOrFail();$sellerOrder=$order->sellerOrders()->firstOrFail();
  $this->assertEquals(135,$order->total);$this->assertSame('cod',$order->payment_status);$this->assertEquals(50,$order->platform_shipping_discount);$this->assertEquals(0,$sellerOrder->shipping_discount);$this->assertEquals(50,$sellerOrder->platform_shipping_discount);$this->assertEquals(135,$sellerOrder->seller_total);$this->assertDatabaseHas('voucher_usages',['voucher_id'=>$platform->id,'funding_source'=>'platform','shipping_discount_amount'=>50]);
  [$futureSeller,$futureShop]=$this->seller('Future Shop','future-platform-shop');$futureProduct=$this->product($futureShop,$category,'Future Product',150);$this->post(route('cart.add'),['product_id'=>$futureProduct->id,'quantity'=>1]);$futureItems=$buyer->cart->items()->with(['product','variant'])->get();$futureQuote=app(OrderService::class)->computeTotals($buyer->id,[$platform->code],$futureItems);$this->assertEquals(150,$futureQuote['total']);
  $this->actingAs($seller)->post(route('admin.promotions.toggle',$platform))->assertForbidden();$this->actingAs($admin)->post(route('seller.marketing.vouchers.toggle',$shopVoucher))->assertForbidden();
 }
 public function test_checkout_count_tracks_seller_created_fixed_and_free_shipping_vouchers():void {
  foreach(['buyer','seller'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
  $buyer=User::factory()->create(['is_active'=>true,'phone'=>'+639171111111']);$buyer->assignRole('buyer');[$seller,$shop]=$this->seller('Dynamic Voucher Shop','dynamic-voucher-shop');
  $category=Category::create(['name'=>'Dynamic Products','slug'=>'dynamic-products']);$product=$this->product($shop,$category,'Dynamic Item',150);
  Address::create(['user_id'=>$buyer->id,'full_name'=>'Buyer','phone'=>'09171111111','province'=>'Laguna','city'=>'Cavinti','barangay'=>'Mahipon','postal_code'=>'4013','address_line'=>'1 Main St','is_default'=>true]);
  $this->actingAs($buyer)->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1]);
  $this->get(route('checkout'))->assertOk()->assertSee('Shop Vouchers: 0')->assertSee('Platform Vouchers: 0');
  $fixed=$shop->vouchers()->create(['source_type'=>'shop','code'=>'DYNAMIC20','title'=>'Dynamic Fixed','type'=>'fixed','value'=>20,'min_purchase'=>100,'per_user_limit'=>1,'status'=>'active']);
  $this->get(route('checkout'))->assertOk()->assertSee('Shop Vouchers: 1')->assertSee($fixed->code);
  $this->actingAs($seller)->post(route('seller.marketing.vouchers.store'),['title'=>'Dynamic Shipping','code'=>'DYNAMICSHIP','type'=>'free_shipping','min_purchase'=>500,'max_discount'=>50,'usage_limit'=>50,'per_user_limit'=>1,'application_scope'=>'entire_shop','status'=>'active'])->assertSessionHasNoErrors();
  $this->actingAs($buyer)->get(route('checkout'))->assertOk()->assertSee('Shop Vouchers: 2')->assertSee('FREE SHIPPING')->assertSee('Spend ₱350.00 more to use this voucher.');
  $shipping=Voucher::where('code','DYNAMICSHIP')->firstOrFail();$shipping->update(['min_purchase'=>100]);
  $this->post(route('checkout.voucher'),['voucher_code'=>$shipping->code,'checkout_mode'=>'cart'])->assertRedirect()->assertSessionHas('checkout_voucher_codes.cart',[$shipping->code]);
 }
 public function test_checkout_apply_remove_replace_and_place_order_use_persistent_server_pricing():void {
  foreach(['buyer','seller'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
  $buyer=User::factory()->create(['is_active'=>true,'phone'=>'+639171111111']);$buyer->assignRole('buyer');[, $shop]=$this->seller('Panda Picks','panda-apply');
  $category=Category::create(['name'=>'Apply Products','slug'=>'apply-products']);$product=$this->product($shop,$category,'Ninety Five Item',95);
  $address=Address::create(['user_id'=>$buyer->id,'full_name'=>'Buyer','phone'=>'09171111111','province'=>'Laguna','city'=>'Cavinti','barangay'=>'Mahipon','postal_code'=>'4013','address_line'=>'1 Main St','is_default'=>true]);
  $flat=Voucher::create(['source_type'=>'platform','platform_scope'=>'all_shops','code'=>'SHOPPICK50','title'=>'Flat 50 Off','type'=>'fixed','value'=>50,'min_purchase'=>0,'per_user_limit'=>1,'status'=>'active']);
  $ten=Voucher::create(['source_type'=>'platform','platform_scope'=>'all_shops','code'=>'WELCOME10','title'=>'Ten Percent','type'=>'percent','value'=>10,'min_purchase'=>0,'per_user_limit'=>1,'status'=>'active']);
  $this->actingAs($buyer)->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1]);
  $this->post(route('checkout.voucher'),['voucher_id'=>$flat->id,'checkout_mode'=>'cart'])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('checkout_voucher_codes.cart',['SHOPPICK50']);
  $this->assertDatabaseCount('voucher_usages',0);
  $this->get(route('checkout'))->assertOk()->assertSee('Applied Platform Voucher')->assertSee('−₱50.00')->assertSee('₱95.00')->assertSee('name="voucher_codes[]" value="SHOPPICK50"',false);
  $this->post(route('checkout.voucher'),['voucher_id'=>$ten->id,'checkout_mode'=>'cart'])->assertSessionHas('checkout_voucher_codes.cart',['WELCOME10']);
  $this->post(route('checkout.voucher'),['voucher_code'=>'SHOPPICK50','checkout_mode'=>'cart'])->assertSessionHas('checkout_voucher_codes.cart',['SHOPPICK50']);
  $this->post(route('checkout.voucher'),['voucher_id'=>$flat->id,'checkout_mode'=>'cart','action'=>'remove'])->assertSessionHas('checkout_voucher_codes.cart',[]);
  $this->get(route('checkout'))->assertOk()->assertSee('₱145.00')->assertDontSee('Applied Platform Voucher');
  $this->post(route('checkout.voucher'),['voucher_id'=>$flat->id,'checkout_mode'=>'cart']);
  $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'cod','voucher_codes'=>['SHOPPICK50']])->assertSessionHasNoErrors();
  $order=$buyer->orders()->latest('id')->firstOrFail();$this->assertEquals(95,$order->total);$this->assertEquals(50,$order->platform_discount);$this->assertSame('cod',$order->payment_status);$this->assertDatabaseHas('voucher_usages',['voucher_id'=>$flat->id,'order_id'=>$order->id,'discount_amount'=>50,'funding_source'=>'platform']);$this->assertNull(session('checkout_voucher_codes.cart'));
 }
 public function test_admin_can_filter_edit_view_deactivate_and_archive_only_platform_vouchers():void {
  foreach(['buyer','seller','admin'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);$permission=Permission::create(['name'=>'Manage Promotions','slug'=>'manage_promotions','guard_name'=>'web','group'=>'Admin']);Role::where('slug','admin')->first()->permissions()->attach($permission);
  $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');[$seller,$shop]=$this->seller('Seller Voucher Shop','seller-voucher-shop');$buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
  $fixed=Voucher::create(['source_type'=>'platform','platform_scope'=>'all_shops','created_by'=>$admin->id,'code'=>'ADMIN50','title'=>'Admin Fixed','type'=>'fixed','value'=>50,'min_purchase'=>0,'status'=>'active']);
  $shipping=Voucher::create(['source_type'=>'platform','platform_scope'=>'all_shops','created_by'=>$admin->id,'code'=>'SHIPADMIN','title'=>'Admin Shipping','type'=>'free_shipping','value'=>0,'min_purchase'=>100,'max_discount'=>50,'status'=>'inactive']);
  $shopVoucher=$shop->vouchers()->create(['source_type'=>'shop','code'=>'SELLER10','title'=>'Seller Voucher','type'=>'fixed','value'=>10,'min_purchase'=>0,'status'=>'active']);
  $this->actingAs($admin)->get(route('admin.promotions.create'))->assertRedirect(route('admin.promotions.index',['create'=>1]));$this->get(route('admin.promotions.edit',$fixed))->assertRedirect(route('admin.promotions.index',['q'=>'ADMIN50','edit'=>$fixed->id]));
  $this->actingAs($admin)->get(route('admin.promotions.index',['q'=>'Shipping','type'=>'free_shipping','status'=>'inactive']))->assertOk()->assertSee('SHIPADMIN')->assertDontSee('ADMIN50')->assertDontSee('SELLER10');
  $this->get(route('admin.promotions.show',$shipping))->assertOk()->assertSee('Usage History')->assertSee('All Shops')->assertSee('Maximum Shipping Discount');
  $this->put(route('admin.promotions.update',$shipping),['code'=>'SHIPADMIN','title'=>'Updated Platform Shipping','type'=>'free_shipping','min_purchase'=>100,'max_discount'=>'','usage_limit'=>'','per_user_limit'=>1,'starts_at'=>now()->format('Y-m-d'),'ends_at'=>now()->addDays(10)->format('Y-m-d'),'status'=>'active'])->assertRedirect(route('admin.promotions.index'))->assertSessionHasNoErrors();
  $this->assertDatabaseHas('vouchers',['id'=>$shipping->id,'source_type'=>'platform','platform_scope'=>'all_shops','store_id'=>null,'title'=>'Updated Platform Shipping','max_discount'=>null]);
  $this->post(route('admin.promotions.toggle',$fixed))->assertSessionHasNoErrors();$this->assertSame('inactive',$fixed->fresh()->status);
  $this->delete(route('admin.promotions.destroy',$shipping))->assertRedirect(route('admin.promotions.index'));$this->assertNotNull($shipping->fresh()->archived_at);$this->assertSame('inactive',$shipping->fresh()->status);
  $this->post(route('admin.promotions.toggle',$shipping))->assertStatus(422);
  $this->actingAs($seller)->get(route('admin.promotions.index'))->assertForbidden();$this->put(route('admin.promotions.update',$fixed),[])->assertForbidden();$this->delete(route('admin.promotions.destroy',$fixed))->assertForbidden();
  $this->actingAs($admin)->get(route('admin.promotions.show',$shopVoucher))->assertForbidden();
 }
 public function test_seller_vouchers_are_discoverable_scoped_and_securely_priced_for_cod_and_online_payments():void {
  foreach(['buyer','seller'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
  $buyer=User::factory()->create(['is_active'=>true,'phone'=>'+639171111111']);$buyer->assignRole('buyer');
  [$sellerA,$shopA]=$this->seller('Panda Picks','panda-vouchers');[$sellerB,$shopB]=$this->seller('Tech Corner','tech-vouchers');
  $category=Category::create(['name'=>'Voucher Products','slug'=>'voucher-products']);
  $panda=$this->product($shopA,$category,'Panda Item',150);$tech=$this->product($shopB,$category,'Tech Item',150);
  $address=Address::create(['user_id'=>$buyer->id,'full_name'=>'Voucher Buyer','phone'=>'09171111111','province'=>'Laguna','city'=>'Cavinti','barangay'=>'Mahipon','postal_code'=>'4013','address_line'=>'1 Voucher St','is_default'=>true]);
  $this->actingAs($sellerA)->post(route('seller.marketing.vouchers.store'),['title'=>'Panda ₱20 OFF','code'=>'PANDA20','type'=>'fixed','value'=>20,'min_purchase'=>100,'usage_limit'=>100,'per_user_limit'=>1,'application_scope'=>'entire_shop','status'=>'active'])->assertSessionHasNoErrors();
  $this->post(route('seller.marketing.vouchers.store'),['title'=>'Free Shipping Weekend','code'=>'FREESHIP','type'=>'free_shipping','min_purchase'=>100,'max_discount'=>20,'usage_limit'=>50,'per_user_limit'=>1,'application_scope'=>'entire_shop','status'=>'active'])->assertSessionHasNoErrors();
  $fixed=Voucher::where('code','PANDA20')->firstOrFail();$free=Voucher::where('code','FREESHIP')->firstOrFail();
  $this->assertSame($shopA->id,$fixed->store_id);$this->assertSame('free_shipping',$free->type);
  $this->actingAs($buyer)->post(route('cart.add'),['product_id'=>$panda->id,'quantity'=>1]);$this->post(route('cart.add'),['product_id'=>$tech->id,'quantity'=>1]);
  $this->get(route('checkout'))->assertOk()->assertSee('Available Vouchers')->assertSee('PANDA20')->assertSee('FREESHIP')->assertSee('Panda Picks')->assertDontSee('unrelated-voucher');
  $items=$buyer->cart->items()->with(['product','variant'])->get();$quote=app(OrderService::class)->computeTotals($buyer->id,['FREESHIP'],$items);
  $this->assertEquals(0,$quote['voucher_discount']);$this->assertEquals(20,$quote['shipping_discount']);$this->assertEquals(330,$quote['total']);
  $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'cod','voucher_codes'=>['FREESHIP']])->assertSessionHasNoErrors()->assertRedirect();
  $order=$buyer->orders()->latest('id')->firstOrFail();$pandaOrder=$order->sellerOrders()->where('store_id',$shopA->id)->firstOrFail();$techOrder=$order->sellerOrders()->where('store_id',$shopB->id)->firstOrFail();
  $this->assertEquals(0,$pandaOrder->voucher_discount);$this->assertEquals(20,$pandaOrder->shipping_discount);$this->assertEquals(0,$techOrder->voucher_discount);$this->assertEquals(0,$techOrder->shipping_discount);
  $this->assertEquals(330,$order->total);$this->assertSame('cod',$order->payment_status);$this->assertEquals(330,$order->payments()->first()->amount);$this->assertCount(1,$order->voucherUsages);
  try{app(OrderService::class)->computeTotals($buyer->id,['FREESHIP'],$items);$this->fail('Per-buyer usage limit was bypassed.');}catch(\Exception $e){$this->assertStringContainsString('not eligible',$e->getMessage());}
  $expired=$shopB->vouchers()->create(['source_type'=>'shop','code'=>'OLD10','title'=>'Expired','type'=>'percent','value'=>10,'min_purchase'=>0,'per_user_limit'=>1,'starts_at'=>now()->subDays(2),'ends_at'=>now()->subDay(),'status'=>'active']);
  try{app(OrderService::class)->computeTotals($buyer->id,[$expired->code],$items);$this->fail('Expired voucher was accepted.');}catch(\Exception $e){$this->assertStringContainsString('not eligible',$e->getMessage());}
  $online=$shopB->vouchers()->create(['source_type'=>'shop','code'=>'TECH10','title'=>'Tech 10%','type'=>'percent','value'=>10,'min_purchase'=>100,'max_discount'=>100,'per_user_limit'=>1,'status'=>'active']);
  $this->post(route('cart.add'),['product_id'=>$tech->id,'quantity'=>1]);$this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'card','voucher_codes'=>[$online->code]])->assertSessionHasNoErrors();
  $card=$buyer->orders()->latest('id')->firstOrFail();$this->assertEquals(185,$card->total);$this->assertSame('paid',$card->payment_status);$this->assertEquals(185,$card->payments()->latest('id')->value('amount'));
 }
 private function seller(string $name,string $slug):array{$user=User::factory()->create(['is_active'=>true]);$user->assignRole('seller');$profile=SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']);$shop=Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>$slug,'status'=>'active']);return[$user,$shop];}
 private function product(Store $shop,Category $category,string $name,float $price):Product{return Product::create(['store_id'=>$shop->id,'category_id'=>$category->id,'name'=>$name,'slug'=>str($name)->slug().'-'.str()->random(5),'price'=>$price,'stock'=>10,'is_active'=>true,'moderation_status'=>'clean']);}
}
