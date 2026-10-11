<?php
namespace Tests\Feature;

use App\Models\{Category,Order,OrderItem,Product,Role,SellerOrder,SellerProfile,Store,User};
use App\Services\{SellerDashboardService,ShopSalesReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
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
    public function test_seller_metrics_match_sales_and_cannot_be_broadened_by_request(): void
    {
        [$seller,$store]=$this->seller('Own Shop');[, $other]=$this->seller('Other Shop');
        $this->order($store,'completed',100,90,'2026-10-08','OWN-DONE');
        $this->order($store,'completed',50,45,'2026-10-01','OWN-PRIOR');
        $this->order($store,'pending',80,72,'2026-10-09','OWN-PENDING');
        $this->order($other,'completed',9000,8000,'2026-10-08','SECRET-ORDER');
        $this->product($store,'Own Product');$this->product($other,'Secret Product');
        $page=$this->actingAs($seller)->get(route('seller.dashboard',['range'=>'7days','store_id'=>$other->id,'shop'=>$other->id]))->assertOk()->assertDontSee('Secret Product')->assertDontSee('SECRET-ORDER')->assertDontSee('Other Shop');
        $this->assertSame(['gross'=>100.0,'net'=>90.0,'commission'=>10.0,'orders'=>2,'pending'=>72.0],$page->viewData('metrics'));
        $this->assertEquals(90,array_sum(array_column($page->viewData('chart')['trend'],'current')));
        $this->assertEquals(45,array_sum(array_column($page->viewData('chart')['trend'],'previous')));
        $this->assertEquals(['completed'=>1,'pending'=>1],array_column($page->viewData('chart')['statuses'],'value','status'));
        $this->assertSame([$store->id],$page->viewData('recentOrders')->pluck('store_id')->unique()->values()->all());
        $this->assertSame([$store->id],$page->viewData('inventory')->pluck('store_id')->unique()->values()->all());
        $all=$this->get(route('seller.dashboard',['range'=>'all']))->assertOk()->viewData('metrics');
        $sales=$this->get(route('seller.sales.index'))->assertOk()->viewData('stats');
        $this->assertEquals($sales['completed'],$all['net']);$this->assertEquals($sales['commission'],$all['commission']);$this->assertEquals($sales['pending'],$all['pending']);
        $this->assertEquals($all['gross']-$all['commission'],$all['net']);
        $this->assertSame(100.0,$page->viewData('changes')['net']['percent']);
    }
    public function test_new_seller_zero_charts_and_buyer_authorization(): void
    {
        [$seller]=$this->seller('Future Seller');
        $page=$this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Future Seller')->assertSee('No sales recorded yet.')->assertSee('No orders yet.')->assertSee('No products yet.');
        $this->assertSame(0.0,$page->viewData('metrics')['net']);$this->assertEmpty($page->viewData('chart')['statuses']);
        $page->assertSee('data-seller-chart="sales"',false)->assertSee('data-seller-chart="status"',false);
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();auth()->logout();
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
    }
    public function test_queries_are_bounded_and_records_are_unchanged(): void
    {
        [, $store]=$this->seller('Query Shop');
        for($i=0;$i<8;$i++){$this->order($store,'completed',10,9,'2026-10-08','ORDER-'.$i);$this->product($store,'Product '.$i);}
        $service=app(SellerDashboardService::class);$filters=app(ShopSalesReportService::class)->filters(Request::create('/','GET',['range'=>'7days']));
        DB::enableQueryLog();DB::flushQueryLog();$data=$service->data($store,$filters);$queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertCount(6,$data['recentOrders']);$this->assertCount(5,$data['inventory']);
        foreach($queries as $q)$this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|drop|alter)/i',$q['query']);
        for($i=8;$i<16;$i++)$this->order($store,'completed',10,9,'2026-10-08','ORDER-'.$i);
        DB::enableQueryLog();DB::flushQueryLog();$service->data($store,$filters);$this->assertCount(count($queries),DB::getQueryLog());DB::disableQueryLog();
    }
    public function test_generate_opt_in_seller_browser_fixtures(): void
    {
        if(!getenv('SELLER_BROWSER_FIXTURES'))$this->markTestSkipped('Opt-in isolated seller browser fixtures.');
        [$seller,$store]=$this->seller('Studio Home');$this->actingAs($seller);
        $dir=storage_path('app/seller-dashboard-fixtures');if(!is_dir($dir))mkdir($dir,0777,true);
        file_put_contents($dir.'/empty.html',$this->get(route('seller.dashboard'))->assertOk()->getContent());
        foreach([4,5,6,7,8,9] as $day){$this->order($store,'completed',$day*500,$day*450,'2026-10-0'.$day,'SHOP-2026100'.$day);$this->product($store,'Home Collection '.$day);}
        $this->order($store,'ready_to_ship',250,225,'2026-10-09','SHOP-READY');
        $this->order($store,'completed',2000,1800,'2026-09-28','SHOP-PRIOR');
        file_put_contents($dir.'/filled.html',$this->get(route('seller.dashboard'))->assertOk()->getContent());
    }
}
