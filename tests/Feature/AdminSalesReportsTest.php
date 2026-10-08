<?php

namespace Tests\Feature;

use App\Models\{Category, Order, OrderItem, Permission, Product, Role, SellerOrder, SellerProfile, Store, User};
use App\Services\ShopSalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSalesReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));
        $role = Role::firstOrCreate(['slug'=>'admin'],['name'=>'Admin','guard_name'=>'web']);
        $permission = Permission::firstOrCreate(['slug'=>'view_shops'],['name'=>'View Shops','guard_name'=>'web']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('admin'));
    }

    private function shop(string $name, string $status = 'active'): Store
    {
        $user = User::factory()->create(['name'=>'Seller '.$name,'is_active'=>true])->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']);
        return Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>'shop-'.$user->id,'status'=>$status]);
    }

    private function sale(Store $shop, float $amount, int $units, string $date, string $status = 'completed', ?Order $parent = null): SellerOrder
    {
        $parent ??= Order::create(['user_id'=>$shop->user_id,'order_number'=>'BUYER-'.uniqid(),'payment_method'=>'cod','payment_status'=>'paid','total'=>99999]);
        $vendor = SellerOrder::create(['order_id'=>$parent->id,'store_id'=>$shop->id,'seller_order_number'=>'VENDOR-'.uniqid(),'status'=>$status,'subtotal'=>$amount,'seller_total'=>$amount,'completed_at'=>$status === 'completed' ? $date : null]);
        $vendor->forceFill(['created_at'=>'2026-09-01'])->save();
        $category = Category::firstOrCreate(['slug'=>'report-fixture'],['name'=>'Report Fixture']);
        $product = Product::create(['store_id'=>$shop->id,'slug'=>'item-'.uniqid(),'category_id'=>$category->id,'name'=>'Report Item','price'=>10,'stock'=>25]);
        OrderItem::create(['order_id'=>$parent->id,'seller_order_id'=>$vendor->id,'product_id'=>$product->id,'product_name'=>'Report Item','price'=>10,'quantity'=>$units,'total'=>$units*10]);
        return $vendor;
    }

    private function fixture(): array
    {
        $panda = $this->shop('Panda Picks');
        $tech = $this->shop('Tech Corner','suspended');
        $empty = $this->shop('Home Finds');
        $parent = $this->sale($panda,150,3,'2026-10-06')->order;
        $this->sale($tech,50,2,'2026-10-06','completed',$parent);
        $this->sale($panda,100,2,'2026-10-01');
        $this->sale($panda,800,2,'2026-10-07','cancelled');
        $this->sale($panda,900,1,'2026-10-07','pending');
        return [$panda,$tech,$empty];
    }

    private function page(array $params = [])
    {
        $web = $this->get(route('admin.sales-reports.index',$params))->assertOk();
        $json = $this->getJson(route('admin.sales-reports.filter',$params))->assertOk();
        foreach (['summary','table','pagination','period','exports'] as $part) {
            $this->assertSame(view('admin.sales-reports.'.$part,$web->original->getData())->render(),$json->json($part.'_html'));
        }
        return $web;
    }

    public function test_sidebar_is_present_and_only_sales_reports_is_active_on_overview_and_detail(): void
    {
        [$panda] = $this->fixture();
        foreach (['admin.sales-reports.index'=>[],'admin.shops.sales-report'=>[$panda]] as $route=>$params) {
            $html = $this->get(route($route,$params))->assertOk()->getContent();
            preg_match_all('/<a href="([^"]+)" class="([^"]+)"[^>]*>.*?<\/a>/s',$html,$links,PREG_SET_ORDER);
            $active = [];
            foreach ($links as $link) if (str_contains($link[2],'bg-brand-500/20')) $active[] = $link[1];
            $this->assertSame([route('admin.sales-reports.index'),route('admin.sales-reports.index')],$active);
            $this->assertStringContainsString('Sales Reports',$html);
        }
    }

    public function test_marketplace_totals_do_not_double_count_multi_vendor_orders_or_items(): void
    {
        [$panda] = $this->fixture();
        // An additional item changes units, without repeating the net order total.
        $vendor = $panda->sellerOrders()->where('seller_total',150)->first();
        $item = $vendor->items()->first();
        OrderItem::create(['order_id'=>$vendor->order_id,'seller_order_id'=>$vendor->id,'product_id'=>$item->product_id,'product_name'=>'Another item','price'=>10,'quantity'=>4,'total'=>40]);
        $summary = $this->page(['range'=>'week'])->viewData('summary');
        $this->assertSame(['sales'=>200.0,'orders'=>2,'units'=>9,'average'=>100.0,'shops'=>3],$summary);
        $this->assertSame(300.0,$this->page()->viewData('summary')['sales']);
    }

    public function test_shop_date_search_status_sort_and_detail_links_preserve_the_correct_scope(): void
    {
        [$panda,$tech] = $this->fixture();
        $page = $this->page(['range'=>'custom','from'=>'2026-10-05','to'=>'2026-10-06','shop'=>$panda->id,'q'=>'Panda','sort'=>'name']);
        $this->assertSame(150.0,$page->viewData('summary')['sales']);
        $this->assertSame([$panda->id],$page->viewData('shops')->pluck('id')->all());
        $page->assertSee(route('admin.shops.sales-report',['shop'=>$panda->id,'range'=>'custom','from'=>'2026-10-05','to'=>'2026-10-06']));
        $this->assertSame(50.0,$this->page(['range'=>'week','shop_status'=>'suspended'])->viewData('summary')['sales']);
        $this->assertSame([$tech->id],$this->page(['q'=>'Seller Tech'])->viewData('shops')->pluck('id')->all());
        $this->assertSame(100.0,$this->page(['range'=>'last_week','shop'=>$panda->id])->viewData('summary')['sales']);
        $this->assertSame(0.0,$this->page(['range'=>'today'])->viewData('summary')['sales']);
        $this->assertSame(200.0,$this->page(['range'=>'7days'])->viewData('summary')['sales']);
        $this->assertSame(300.0,$this->page(['range'=>'30days'])->viewData('summary')['sales']);
        $this->assertSame(300.0,$this->page(['range'=>'month'])->viewData('summary')['sales']);
        $this->assertSame(0.0,$this->page(['range'=>'last_month'])->viewData('summary')['sales']);
        $this->assertSame(0,$this->page(['q'=>'missing'])->viewData('summary')['shops']);
        $this->page(['q'=>'missing'])->assertSee('No shop sales match your current filters.')->assertSee('Clear Filters');
    }

    public function test_all_performance_states_and_filters_match_existing_shop_reports(): void
    {
        $scenarios = [
            'Improving'=>[[150,3], [100,2]], 'Declining'=>[[50,1],[100,2]],
            'Stable'=>[[100,2],[100,2]], 'Mixed Performance'=>[[150,1],[100,2]],
            'New Activity'=>[[100,2],null], 'No Sales'=>[null,null],
        ];
        $service = app(ShopSalesReportService::class);
        foreach ($scenarios as $label=>[$current,$previous]) {
            $shop = $this->shop($label);
            if ($current) $this->sale($shop,$current[0],$current[1],'2026-10-06');
            if ($previous) $this->sale($shop,$previous[0],$previous[1],'2026-10-01');
            $page = $this->page(['range'=>'week','performance'=>$label]);
            $this->assertSame([$shop->id],$page->viewData('shops')->pluck('id')->all());
            $this->assertSame($label,$page->viewData('shops')->first()->performance);
            $detail = $service->build($shop,$service->filters(\Illuminate\Http\Request::create('/','GET',['range'=>'week'])));
            $this->assertSame($label,$detail['performance']);
        }
        $this->assertSame(6,$this->page(['performance'=>'No comparable period'])->viewData('summary')['shops']);
    }

    public function test_weekly_comparison_is_previous_week_and_sales_trend_is_correct(): void
    {
        $this->fixture();
        $web = $this->page(['range'=>'week']);
        $panda = $web->viewData('shops')->firstWhere('name','Panda Picks');
        $this->assertSame(100.0,(float)$panda->previous_sales);
        $this->assertSame('2026-09-28',$web->viewData('filters')['previousFrom']->toDateString());
        $this->assertSame('2026-10-04',$web->viewData('filters')['previousTo']->toDateString());
        $web->assertSee('+50.0%')->assertSee('Improving')->assertSee('New Activity')->assertSee('No Sales');
    }

    public function test_pagination_preserves_filters_and_exports_include_every_matching_page(): void
    {
        for ($i=1;$i<=17;$i++) $this->sale($this->shop(sprintf('Shop %02d',$i)),10,1,'2026-10-06');
        $params = ['range'=>'week','sort'=>'name','q'=>'Shop','performance'=>'New Activity'];
        $web = $this->page($params);
        $this->assertSame(15,$web->viewData('shops')->count());
        $this->assertSame(17,$web->viewData('shops')->total());
        $this->assertSame(170.0,$web->viewData('summary')['sales']);
        $this->assertStringContainsString('performance=New%20Activity',$web->viewData('shops')->url(2));
        $this->assertSame(2,$this->page($params+['page'=>2])->viewData('shops')->count());
        $print = $this->get(route('admin.sales-reports.print',$params+['page'=>2]))->assertOk()->assertSee('Shop 01')->assertSee('Shop 17')->assertSee('₱170.00')->assertDontSee('<aside',false)->assertDontSee('<form',false);
        $csv = $this->get(route('admin.sales-reports.csv',$params))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv',array_filter(explode("\n",trim(substr($csv,3)))));
        $this->assertCount(19,$rows);
        $this->assertSame('Totals',$rows[18][0]);
        $this->assertSame('170.00',$rows[18][6]);
        $pdf = $this->get(route('admin.sales-reports.pdf',$params))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$pdf->getContent());
        $this->assertStringContainsString('2026-10-05_to_2026-10-11.pdf',$pdf->headers->get('Content-Disposition'));
    }

    public function test_all_exports_use_shop_date_and_performance_filters_and_escape_csv_formulas(): void
    {
        [$panda] = $this->fixture();
        $panda->update(['name'=>'=Panda Picks']);
        $params = ['shop'=>$panda->id,'range'=>'week','performance'=>'Improving','q'=>'Panda'];
        $this->get(route('admin.sales-reports.print',$params))->assertOk()->assertSee('=Panda Picks')->assertSee('₱150.00')->assertDontSee('Tech Corner')->assertSee('window.print()',false);
        $csv = $this->get(route('admin.sales-reports.csv',$params))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
        $this->assertStringContainsString("'=Panda Picks",$csv);
        $this->assertStringNotContainsString('Tech Corner',$csv);
        $this->assertStringContainsString('150.00',$csv);
        $pdf = $this->get(route('admin.sales-reports.pdf',$params))->assertOk();
        $this->assertStringStartsWith('%PDF-',$pdf->getContent());
    }

    public function test_every_endpoint_requires_admin_role_and_existing_shop_permission(): void
    {
        foreach (['buyer','seller','logistics','rider'] as $role) {
            Role::firstOrCreate(['slug'=>$role],['name'=>ucfirst($role),'guard_name'=>'web']);
            $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole($role));
            foreach (['index','filter','pdf','print','csv'] as $action) $this->getJson(route('admin.sales-reports.'.$action))->assertForbidden();
        }
        $role = Role::where('slug','admin')->first();
        $role->permissions()->detach();
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('admin'));
        foreach (['index','filter','pdf','print','csv'] as $action) $this->getJson(route('admin.sales-reports.'.$action))->assertForbidden();
        auth()->logout();
        $this->getJson(route('admin.sales-reports.filter'))->assertUnauthorized();
    }

    public function test_reports_are_read_only_and_aggregation_query_count_does_not_grow_per_shop(): void
    {
        $this->fixture();
        $service = app(\App\Services\MarketplaceSalesReportService::class);
        $filters = $service->filters(\Illuminate\Http\Request::create('/','GET',['range'=>'week']));
        DB::enableQueryLog(); DB::flushQueryLog();
        $service->data($filters);
        $count = count(DB::getQueryLog());
        foreach (DB::getQueryLog() as $query) $this->assertMatchesRegularExpression('/^select\b/i',$query['query']);
        DB::disableQueryLog();
        for ($i=0;$i<20;$i++) $this->shop('Extra '.$i);
        DB::enableQueryLog(); DB::flushQueryLog();
        $service->data($filters);
        $this->assertSame($count,count(DB::getQueryLog()));
        $this->assertSame(3,$count); // Totals, pagination count, paginated grouped rows.
        DB::disableQueryLog();
    }

    public function test_invalid_filters_are_rejected_by_every_endpoint(): void
    {
        foreach (['index','filter','pdf','print','csv'] as $action) {
            foreach ([['range'=>'bad'],['shop'=>9999],['performance'=>'bad'],['sort'=>'bad'],['q'=>str_repeat('x',201)],['range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-01']] as $query) {
                $this->getJson(route('admin.sales-reports.'.$action,$query))->assertUnprocessable();
            }
        }
    }

    public function test_browser_fixtures_are_generated_only_on_request_in_the_isolated_database(): void
    {
        if (getenv('SHOPPICK_SALES_OVERVIEW_FIXTURES') !== '1') $this->markTestSkipped('Opt-in isolated browser fixture generation.');
        [$panda] = $this->fixture();
        for ($i=0;$i<16;$i++) $this->shop(sprintf('Extra %02d',$i));
        $fixtures = [];
        $queries = [[],['range'=>'week'],['range'=>'week','shop'=>(string)$panda->id],['range'=>'week','performance'=>'Improving'],['q'=>'Panda'],['q'=>'missing'],['range'=>'custom','from'=>'2026-10-05','to'=>'2026-10-06'],['sort'=>'name'],['page'=>'2'],['shop_status'=>'suspended'],['range'=>'today']];
        foreach ($queries as $query) {
            $key = http_build_query($query,'','&',PHP_QUERY_RFC3986);
            $fixtures[$key] = $this->getJson(route('admin.sales-reports.filter',$query))->assertOk()->json();
        }
        $dir = storage_path('app/sales-overview-fixtures');
        if (!is_dir($dir)) mkdir($dir,0777,true);
        file_put_contents($dir.'/web.html',$this->get(route('admin.sales-reports.index'))->assertOk()->getContent());
        file_put_contents($dir.'/responses.json',json_encode($fixtures,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/print.html',$this->get(route('admin.sales-reports.print',['range'=>'week','shop'=>$panda->id]))->assertOk()->getContent());
        file_put_contents($dir.'/report.pdf',$this->get(route('admin.sales-reports.pdf',['range'=>'week','shop'=>$panda->id]))->assertOk()->getContent());
        file_put_contents($dir.'/report.csv',$this->get(route('admin.sales-reports.csv',['range'=>'week','shop'=>$panda->id]))->assertOk()->streamedContent());
    }
}
