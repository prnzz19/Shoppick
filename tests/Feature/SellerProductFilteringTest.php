<?php
namespace Tests\Feature;

use App\Models\{User, SellerProfile, Store, Product, Category};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerProductFilteringTest extends TestCase
{
    use RefreshDatabase;
    private User $seller;
    private Category $category;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seller=User::factory()->create(['is_active'=>true,'registration_status'=>'approved']);
        $this->seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$this->seller->id,'status'=>'approved']);
        Store::create(['user_id'=>$this->seller->id,'seller_profile_id'=>$profile->id,'name'=>'Filter Store','slug'=>'filter-store','status'=>'active']);
        $this->category=Category::create(['name'=>'Audio','slug'=>'audio','is_active'=>true]);
        $this->actingAs($this->seller);
    }
    private function product(array $values=[]): Product
    {
        $number=Product::withTrashed()->count()+1;
        return Product::create($values+['store_id'=>$this->seller->store->id,'category_id'=>$this->category->id,'name'=>'Earbuds '.$number,'slug'=>'earbuds-'.$number,'sku'=>'SKU-'.$number,'price'=>100,'stock'=>5,'is_active'=>true,'publication_status'=>'published','moderation_status'=>'approved']);
    }
    public function test_search_category_and_moderation_filters_are_composable_and_paginated(): void
    {
        for($i=0;$i<12;$i++) $this->product(['moderation_status'=>'scan_failed','is_active'=>false]);
        $clear=$this->product();
        $other=Category::create(['name'=>'Decor','slug'=>'decor','is_active'=>true]);
        $excluded=$this->product(['category_id'=>$other->id,'name'=>'Lamp']);
        $filters=['q'=>'Earbuds','category'=>$this->category->id,'status'=>'inactive','moderation'=>'review_required'];
        $response=$this->get(route('seller.products.index',$filters))->assertOk()->assertViewHas('products',fn($p)=>$p->count()===10 && $p->total()===12);
        $next=$response->viewData('products')->nextPageUrl();
        parse_str(parse_url($next,PHP_URL_QUERY),$query);
        foreach($filters as $key=>$value)$this->assertEquals($value,$query[$key]);
        $this->get($next)->assertOk()->assertViewHas('products',fn($p)=>$p->count()===2);
        $this->get(route('seller.products.index',['q'=>$excluded->sku]))->assertViewHas('products',fn($p)=>$p->total()===1 && $p->first()->id===$excluded->id);
        $this->get(route('seller.products.index',['category'=>$other->id]))->assertViewHas('products',fn($p)=>$p->total()===1);
        $this->get(route('seller.products.index',['moderation'=>'clear']))->assertViewHas('products',fn($p)=>$p->total()===2);
        $this->get(route('seller.products.index',['q'=>'no-match']))->assertSee('No products match your filters.')->assertSee('Clear Filters');
    }
    public function test_tabs_archive_isolation_and_clear_filter_link(): void
    {
        $active=$this->product();
        $draft=$this->product(['is_active'=>false,'publication_status'=>'draft']);
        $inactive=$this->product(['is_active'=>false]);
        $archived=$this->product();$archived->delete();
        $foreign=$this->product(['store_id'=>null,'name'=>'Foreign product']);
        foreach(['active'=>$active,'draft'=>$draft,'inactive'=>$inactive,'archived'=>$archived] as $status=>$expected){
            $this->get(route('seller.products.index',['status'=>$status]))->assertOk()
                ->assertViewHas('products',fn($p)=>$p->total()===1 && $p->first()->id===$expected->id)->assertDontSee('Foreign product');
        }
        $this->get(route('seller.products.index'))->assertViewHas('products',fn($p)=>$p->total()===3)->assertDontSee('Clear Filters');
        $this->get(route('seller.products.index',['moderation'=>'review_required']))->assertSee('No Review Required products match your filters.');
        $this->get(route('seller.products.index',['moderation'=>'invalid']))->assertSessionHasErrors('moderation');
    }
    public function test_edit_cancel_and_save_preserve_validated_list_context(): void
    {
        $product=$this->product();
        $context=['q'=>'Earbuds','category'=>$this->category->id,'status'=>'active','moderation'=>'clear','page'=>2];
        $this->get(route('seller.products.edit',['product'=>$product,'list_context'=>$context]))->assertOk()->assertSee('list_context[page]',false);
        $this->put(route('seller.products.update',$product),[
            'name'=>$product->name,'category_id'=>$product->category_id,'sku'=>$product->sku,
            'price'=>100,'stock'=>5,'is_active'=>1,'list_context'=>$context,
        ])->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index',$context));
    }
    public function test_product_relationship_queries_do_not_grow_with_row_count(): void
    {
        $this->product();
        DB::enableQueryLog();
        $this->get(route('seller.products.index'))->assertOk();
        $small=count(DB::getQueryLog());
        DB::disableQueryLog();
        for($i=0;$i<9;$i++)$this->product();
        DB::flushQueryLog(); DB::enableQueryLog();
        $this->get(route('seller.products.index'))->assertOk();
        $large=count(DB::getQueryLog());DB::disableQueryLog();
        $this->assertLessThanOrEqual($small+1,$large);
    }
}