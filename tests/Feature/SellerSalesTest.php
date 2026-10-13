<?php
namespace Tests\Feature;
use App\Models\{Category,Order,OrderItem,Product,SellerOrder,SellerProfile,Store,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SellerSalesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->travelTo(now()->setDate(2026,10,10)->setTime(12,0)); }
    private function seller(string $name): array
    {
        $user=User::factory()->create(['is_active'=>true]);$user->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>str($name)->slug().$user->id,'status'=>'active']);
        return [$user,$store];
    }
    private function order(Store $store,string $status,float $gross,float $net,string $date,string $number): SellerOrder
    {
        $parent=Order::create(['user_id'=>$store->user_id,'order_number'=>'parent-'.$number,'buyer_name'=>'Buyer '.$number,'payment_method'=>'cod','payment_status'=>'paid','total'=>$gross]);
        $order=SellerOrder::create(['order_id'=>$parent->id,'store_id'=>$store->id,'seller_order_number'=>$number,'status'=>$status,'subtotal'=>$gross,'seller_total'=>$net,'commission_amount'=>$gross-$net,'completed_at'=>$status==='completed'?$date:null]);
        $order->forceFill(['created_at'=>$date])->save();return $order;
    }
    private function product(Store $store,string $name): Product
    {
        $category=Category::firstOrCreate(['slug'=>'dashboard'],['name'=>'Dashboard']);
        return Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>$name,'slug'=>str($name)->slug().$store->id,'price'=>100,'stock'=>12,'sold_count'=>3,'is_active'=>true,'moderation_status'=>'clean','publication_status'=>'published']);
    }

    public function test_overview_preserves_kpis_and_scopes_every_chart_and_transaction(): void
    {
        [$seller,$store]=$this->seller('Own');[, $other]=$this->seller('Other');
        $done=$this->order($store,'completed',100,90,'2026-10-10','OWN-DONE');
        $this->order($store,'completed',50,45,'2026-10-01','OWN-OLD');
        $this->order($store,'pending',80,72,'2026-10-09','OWN-PENDING');
        $this->order($store,'cancelled',999,899,'2026-10-09','OWN-CANCELLED');
        $secret=$this->order($other,'completed',9000,8000,'2026-10-10','SECRET');
        $product=$this->product($store,'Own Product');
        OrderItem::create(['order_id'=>$done->order_id,'seller_order_id'=>$done->id,'product_id'=>$product->id,'product_name'=>$product->name,'quantity'=>2,'price'=>50,'total'=>100]);
        $page=$this->actingAs($seller)->get(route('seller.sales.index',['range'=>'7days','shop'=>$other->id]))->assertOk()->assertDontSee('SECRET');
        $this->assertSame(['today'=>90.0,'month'=>135.0,'completed'=>135.0,'pending'=>72.0,'commission'=>15.0],$page->viewData('stats'));
        $this->assertEquals(90,array_sum(array_column($page->viewData('chart')['trend'],'current')));
        $this->assertEquals(90,$page->viewData('categories')->sum('sales'));
        $this->assertEquals(3,array_sum(array_column($page->viewData('chart')['statuses'],'value')));
        $this->assertSame([100.0,10.0,90.0],[(float)$page->viewData('earnings')->gross,(float)$page->viewData('earnings')->commission,(float)$page->viewData('earnings')->net]);
        $this->assertTrue($page->viewData('orders')->every(fn($o)=>$o->store_id===$store->id));
    }
    public function test_transactions_and_pdf_report_respect_seller_and_selected_period(): void
    {
        [$seller,$store]=$this->seller('Own');[, $other]=$this->seller('Other');
        $this->order($store,'completed',100,90,'2026-10-10','MY-ORDER');
        $this->order($store,'completed',200,180,'2026-09-01','OLD-ORDER');
        $this->order($other,'completed',900,800,'2026-10-10','SECRET');
        $this->actingAs($seller);
        $this->get(route('seller.sales.index',['tab'=>'transactions','range'=>'7days','q'=>'MY-ORDER']))->assertOk()->assertSee('MY-ORDER')->assertDontSee('OLD-ORDER')->assertDontSee('SECRET');
        $pdf=$this->get(route('seller.sales.index',['export'=>'pdf','range'=>'7days','shop'=>$other->id]))
            ->assertOk()
            ->assertHeader('Content-Type','application/pdf')
            ->assertHeader('Content-Disposition','attachment; filename="SHOPPICK_own'.$store->id.'_Sales_Report_2026-10-04_to_2026-10-10.pdf"');
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        $this->get(route('seller.sales.index',['export'=>'csv','range'=>'7days']))->assertSessionHasErrors('export');
    }
    public function test_empty_shop_has_honest_zero_state_and_paginated_transactions(): void
    {
        [$seller,$store]=$this->seller('Empty');
        $overview=$this->actingAs($seller)->get(route('seller.sales.index'))->assertOk()
            ->assertSee('No completed sales in this period.')
            ->assertSee('Sales trend will update when completed orders are recorded.')
            ->assertSee('No completed category sales yet.')
            ->assertSee('Export PDF Report')
            ->assertDontSee('Export CSV')
            ->assertDontSee('Reports')
            ->assertSee('data-sales-chart="overview"',false)
            ->assertSee('data-sales-chart="trend"',false)
            ->assertSee('data-sales-chart="categories"',false);
        $this->assertSame(4,substr_count($overview->getContent(),'class="sales-card sales-kpi"'));
        $this->assertSame(0,$overview->viewData('earnings')->net);
        $this->assertSame(0.0,$overview->viewData('stats')['completed']);
        for($i=0;$i<17;$i++)$this->order($store,'pending',100,90,'2026-10-10','MY-'.$i);
        $page=$this->get(route('seller.sales.index',['tab'=>'transactions','range'=>'7days','q'=>'MY-']))->assertOk();
        $this->assertCount(15,$page->viewData('orders'));$this->assertSame(17,$page->viewData('orders')->total());
        $this->assertStringContainsString('tab=transactions',$page->viewData('orders')->nextPageUrl());
        $this->assertStringContainsString('q=MY-',$page->viewData('orders')->nextPageUrl());
    }
}
