<?php
namespace Tests\Feature;

use App\Models\{Category,Product,SellerProfile,Store,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerInventoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    private Store $shop;
    private Category $category;
    private Product $in;
    private Product $low;
    private Product $out;
    private Product $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category=Category::create(['name'=>'Audio','slug'=>'inventory-audio','is_active'=>true]);
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $this->shop=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'My Inventory','slug'=>'my-inventory','status'=>'active']);
        $this->in=$this->product('Wireless Earbuds',25,'AUDIO-001');
        $this->low=$this->product('Desk Lamp',3,'LAMP-002');
        $this->out=$this->product('Mini Fan',0,'FAN-003');
        $this->in->variants()->create(['type'=>'color','value'=>'Black','stock'=>7]);
        $other=User::factory()->create(['is_active'=>true]);
        $otherProfile=SellerProfile::create(['user_id'=>$other->id,'status'=>'approved']);
        $otherShop=Store::create(['user_id'=>$other->id,'seller_profile_id'=>$otherProfile->id,'name'=>'Other','slug'=>'other-inventory','status'=>'active']);
        $otherCategory=Category::create(['name'=>'Other category','slug'=>'other-inventory-category']);
        $this->foreign=Product::create(['store_id'=>$otherShop->id,'category_id'=>$otherCategory->id,'name'=>'Private Product','slug'=>'private-product','price'=>100,'stock'=>100]);
        $this->foreign->variants()->create(['type'=>'color','value'=>'Red','stock'=>50]);
        $this->actingAs($seller);
    }
    private function product(string $name,int $stock,string $sku): Product
    {
        return Product::create(['store_id'=>$this->shop->id,'category_id'=>$this->category->id,'name'=>$name,'slug'=>strtolower(str_replace(' ','-',$sku)),'sku'=>$sku,'price'=>100,'stock'=>$stock,'low_stock_threshold'=>5,'is_active'=>true]);
    }
    private function listing(array $filters=[])
    {
        return $this->get(route('seller.inventory.index',$filters))->assertOk();
    }
    public function test_inventory_is_compact_and_summary_categories_are_seller_scoped(): void
    {
        $r=$this->listing();$r->assertSee('Manage')->assertSee('Stock Management')->assertDontSee('Private Product')->assertDontSee('Other category');
        $this->assertEquals([3,1,1,28,1],array_map(fn($k)=>$r->viewData('summary')->$k,['total','low','out','units','with_variants']));
        $this->assertSame([$this->category->id],$r->viewData('categories')->pluck('id')->all());
    }
    public function test_search_name_and_sku_are_trimmed_and_case_insensitive(): void
    {
        foreach(['  wireless  ',' audio-001 '] as $q){$r=$this->listing(['q'=>$q]);$this->assertSame([$this->in->id],$r->viewData('products')->pluck('id')->all());}
    }
    public function test_category_stock_and_product_type_filters_compose(): void
    {
        foreach(['in'=>$this->in,'low'=>$this->low,'out'=>$this->out] as $status=>$product){$r=$this->listing(['status'=>$status,'category'=>$this->category->id]);$this->assertSame([$product->id],$r->viewData('products')->pluck('id')->all());}
        $this->assertSame([$this->in->id],$this->listing(['type'=>'variants'])->viewData('products')->pluck('id')->all());
        $this->assertCount(2,$this->listing(['type'=>'simple'])->viewData('products'));
        $this->assertCount(0,$this->listing(['category'=>$this->foreign->category_id])->viewData('products'));
        $this->in->update(['low_stock_threshold'=>25]);
        $this->assertCount(2,$this->listing(['status'=>'low'])->viewData('products'));
    }
    public function test_all_sort_orders_and_pagination_keep_filters(): void
    {
        $this->low->forceFill(['updated_at'=>now()->addMinute()])->save();
        foreach(['name_asc'=>$this->low->id,'name_desc'=>$this->in->id,'stock_asc'=>$this->out->id,'stock_desc'=>$this->in->id,'updated'=>$this->low->id] as $sort=>$first){$this->assertSame($first,$this->listing(['sort'=>$sort])->viewData('products')->first()->id);}
        for($i=0;$i<13;$i++)$this->product('Wireless extra '.$i,10,'EXTRA-'.$i);
        $r=$this->listing(['q'=>'wireless','category'=>$this->category->id,'sort'=>'stock_desc']);
        $this->assertCount(10,$r->viewData('products'));$this->assertSame(14,$r->viewData('products')->total());
        parse_str(parse_url($r->viewData('products')->nextPageUrl(),PHP_URL_QUERY),$params);
        $this->assertSame('wireless',$params['q']);$this->assertSame('stock_desc',$params['sort']);$this->assertEquals($this->category->id,$params['category']);
        $this->get(route('seller.inventory.index',['q'=>'wireless','page'=>99]))->assertRedirect(route('seller.inventory.index',['q'=>'wireless','sort'=>'updated','page'=>2],false));
    }
    public function test_empty_results_and_empty_inventory_have_distinct_actions(): void
    {
        $this->listing(['q'=>'nothing matches'])->assertSee('No products match your filters.')->assertSee('Clear Filters');
        $this->shop->products()->delete();
        $this->listing()->assertSee("You don&#039;t have any products yet.",false)->assertSee('Add Product');
    }
    public function test_save_keeps_independent_parent_stock_and_variant_quantity(): void
    {
        $variant=$this->in->variants()->first();
        $url=route('seller.inventory.index',['q'=>'wireless','type'=>'variants','page'=>1]);
        $this->from($url)->post(route('seller.inventory.update',$this->in),['stock'=>15,'low_stock_threshold'=>2,'variants'=>[$variant->id=>7]])->assertRedirect($url)->assertSessionHas('success','Stock updated successfully.');
        $this->assertEquals(15,$this->in->fresh()->stock);$this->assertEquals(7,$variant->fresh()->stock);
        $this->postJson(route('seller.inventory.update',$this->in),['stock'=>12,'low_stock_threshold'=>0])->assertOk()->assertJson(['message'=>'Stock updated successfully.']);
        $this->assertEquals(7,$variant->fresh()->stock);
    }
    public function test_foreign_variant_rejects_entire_transaction_and_foreign_product_is_forbidden(): void
    {
        $foreignVariant=$this->foreign->variants()->first();
        $this->postJson(route('seller.inventory.update',$this->in),['stock'=>99,'low_stock_threshold'=>1,'variants'=>[$foreignVariant->id=>99]])->assertUnprocessable()->assertJsonValidationErrors('variants');
        $this->assertEquals(25,$this->in->fresh()->stock);$this->assertEquals(50,$foreignVariant->fresh()->stock);
        $this->postJson(route('seller.inventory.update',$this->foreign),['stock'=>1,'low_stock_threshold'=>1])->assertForbidden();
        $this->assertEquals(100,$this->foreign->fresh()->stock);
    }
    public function test_negative_fractional_and_oversized_values_are_rejected(): void
    {
        $variant=$this->in->variants()->first();
        foreach([['stock'=>-1],['stock'=>2.5],['stock'=>2147483648],['low_stock_threshold'=>-1],['low_stock_threshold'=>1.5],['variants'=>[$variant->id=>-1]],['variants'=>[$variant->id=>7.5]]] as $bad){
            $payload=array_replace(['stock'=>25,'low_stock_threshold'=>5],$bad);
            $this->postJson(route('seller.inventory.update',$this->in),$payload)->assertUnprocessable();
        }
        $this->assertEquals(25,$this->in->fresh()->stock);$this->assertEquals(7,$variant->fresh()->stock);
    }
    public function test_legacy_fraction_is_displayed_without_silently_rounding_or_writing_it(): void
    {
        $variant=$this->in->variants()->first();$variant->update(['stock'=>7.5]);
        $this->listing(['type'=>'variants'])->assertSee('Legacy fractional quantity.')->assertSee('value="7.5"',false);
        $this->assertEquals(7.5,$variant->fresh()->stock);
    }

    public function test_ajax_inventory_links_stay_relative_behind_https_terminating_proxy(): void
    {
        for ($i=0; $i<12; $i++) $this->product('Wireless proxy fixture '.$i,10,'PROXY-'.$i);
        // Cloudflare terminates HTTPS; Laravel can see HTTP on the origin connection.
        $response=$this->withHeaders(['X-Requested-With'=>'XMLHttpRequest','X-Forwarded-Proto'=>'https'])
            ->get('http://shoppickmarket.shop/seller/inventory?sort=stock_asc')->assertOk();
        $html=new \DOMDocument();
        @$html->loadHTML($response->getContent());
        $xpath=new \DOMXPath($html);
        $links=$xpath->query('//*[@id="inventory-workspace"]//a[contains(@href,"/seller/inventory")] | //*[@id="inventory-workspace"]//form[contains(@action,"/seller/inventory")]');
        $this->assertGreaterThan(5,$links->length);
        foreach ($links as $node) {
            $url=$node->getAttribute($node->tagName==='form'?'action':'href');
            $this->assertStringStartsWith('/seller/inventory',$url);
            $this->assertNull(parse_url($url,PHP_URL_HOST));
        }
        $this->assertStringStartsWith('/seller/inventory?', $response->viewData('products')->nextPageUrl());
        $this->get('http://shoppickmarket.shop/seller/inventory?q=wireless&page=99')->assertRedirect('/seller/inventory?q=wireless&sort=updated&page=2');
    }
}
