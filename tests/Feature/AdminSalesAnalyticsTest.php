<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\ShopSalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['view_reports', 'view_shops'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'guard_name' => 'web']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->admin = User::factory()->create(['is_active' => true])->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function shop(string $name): Store
    {
        $user = User::factory()->create(['name' => 'Seller '.$name])->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $user->id, 'status' => 'approved']);

        return Store::create(['user_id' => $user->id, 'seller_profile_id' => $profile->id, 'name' => $name, 'slug' => 'shop-'.$user->id, 'status' => 'active']);
    }

    private function product(Store $shop, string $name, Category $category, int $stock = 20): Product
    {
        return Product::create(['store_id' => $shop->id, 'category_id' => $category->id, 'name' => $name, 'slug' => 'product-'.uniqid(), 'price' => 10, 'stock' => $stock]);
    }

    private function sale(Store $shop, float $net, string $date, array $items, string $status = 'completed', ?Order $parent = null): SellerOrder
    {
        $parent ??= Order::create(['user_id' => $shop->user_id, 'order_number' => 'PARENT-'.uniqid(), 'payment_method' => 'cod', 'payment_status' => 'failed', 'total' => 99999]);
        $order = SellerOrder::create(['order_id' => $parent->id, 'store_id' => $shop->id, 'seller_order_number' => 'VENDOR-'.uniqid(), 'status' => $status, 'subtotal' => 1000, 'seller_total' => $net, 'completed_at' => $status === 'completed' ? $date : null]);
        $order->forceFill(['created_at' => $date])->save();
        foreach ($items as [$product,$quantity,$gross]) {
            OrderItem::create(['order_id' => $parent->id, 'seller_order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $quantity, 'price' => 10, 'total' => $gross]);
        }

        return $order;
    }

    private function fixture(): array
    {
        $panda = $this->shop('Panda Picks');
        $tech = $this->shop('Tech Corner');
        $empty = $this->shop('Home Finds');
        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $beauty = Category::create(['name' => 'Beauty', 'slug' => 'beauty']);
        $phone = $this->product($panda, 'Phone', $electronics);
        $cream = $this->product($panda, 'Cream', $beauty);
        $cable = $this->product($tech, 'Cable', $electronics);
        $unused = $this->product($empty, 'Unsold Lamp', $beauty, 0);
        $parent = $this->sale($panda, 120, '2026-10-06 10:00:00', [[$phone, 2, 75], [$cream, 1, 25]])->order;
        $this->sale($tech, 80, '2026-10-06 12:00:00', [[$cable, 2, 100]], 'completed', $parent);
        $this->sale($panda, 200, '2026-10-01 10:00:00', [[$phone, 4, 100]]);
        $this->sale($panda, 900, '2026-10-07 10:00:00', [[$phone, 3, 900]], 'cancelled');
        $this->sale($panda, 800, '2026-10-07 10:00:00', [[$phone, 1, 800]], 'pending');
        $this->sale($panda, 800, '2026-10-02 10:00:00', [[$phone, 1, 800]], 'cancelled');

        return compact('panda', 'tech', 'empty', 'electronics', 'beauty', 'phone', 'cream', 'cable', 'unused');
    }

    private function page(array $filters = [])
    {
        return $this->get(route('admin.analytics.index', array_merge(['range' => 'week'], $filters)))->assertOk();
    }

    public function test_shared_totals_comparison_attribution_and_multi_vendor_orders(): void
    {
        $f = $this->fixture();
        $page = $this->page();
        $summary = $page->viewData('summary');
        $this->assertSame(['sales' => 200.0, 'orders' => 2, 'units' => 5, 'average' => 100.0], $summary);
        $this->assertSame(200.0, $page->viewData('previous')['sales']);
        $this->assertSame('Mixed Performance', $page->viewData('performance'));
        $this->assertEquals(200, $page->viewData('categories')->sum('sales'));
        $this->assertEquals(170, $page->viewData('categories')->firstWhere('name', 'Electronics')->sales);
        $this->assertEquals(90, $page->viewData('products')->firstWhere('name', 'Phone')->sales);
        $this->assertSame(['Panda Picks', 'Tech Corner'], $page->viewData('topShops')->pluck('name')->all());
        $this->assertEquals(100, $page->viewData('topFiveShare'));
        $dash = $this->get(route('admin.dashboard'))->assertOk()->viewData('stats');
        $this->assertEquals($this->page(['range'=>'all'])->viewData('summary')['sales'], $dash['revenue']);
        $report = $this->get(route('admin.sales-reports.index', ['range' => 'week']))->assertOk()->viewData('summary');
        unset($report['shops']);
        $this->assertSame($summary, $report);
        $oneShop = $this->page(['shop' => $f['panda']->id])->viewData('summary');
        $detail = $this->get(route('admin.shops.sales-report', ['shop' => $f['panda']->id, 'range' => 'week']))->assertOk()->viewData('summary');
        $this->assertSame($oneShop, array_intersect_key($detail, $oneShop));
    }

    public function test_filters_apply_to_every_section_and_custom_dates_are_inclusive(): void
    {
        $f = $this->fixture();
        $page = $this->page(['category' => $f['electronics']->id]);
        $this->assertSame(170.0, $page->viewData('summary')['sales']);
        $this->assertSame(4, $page->viewData('summary')['units']);
        $this->assertCount(1, $page->viewData('categories'));
        $this->assertSame(1, $page->viewData('cancellations')['current']);
        $page = $this->page(['shop' => $f['tech']->id]);
        $this->assertSame(80.0, $page->viewData('summary')['sales']);
        $this->assertCount(1, $page->viewData('shops'));
        $this->assertSame(200.0, $this->page(['range' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-06'])->viewData('summary')['sales']);
        $page = $this->page(['status' => 'cancelled']);
        $this->assertSame(0.0, $page->viewData('summary')['sales']);
        $this->assertSame(100.0, $page->viewData('cancellations')['rate']);
        $this->assertCount(0, $page->viewData('products'));
        $page = $this->page(['category' => $f['beauty']->id]);
        $this->assertSame(30.0, $page->viewData('summary')['sales']);
        $this->assertSame(1, $page->viewData('summary')['orders']);
        $this->assertSame(1, $page->viewData('noSaleProducts')['out_of_stock']);
        $this->getJson(route('admin.analytics.index', ['range' => 'custom', 'from' => '2026-10-08', 'to' => '2026-10-01']))->assertUnprocessable();
        $this->getJson(route('admin.analytics.index', ['category' => 999999]))->assertUnprocessable();
    }

    public function test_statuses_no_sales_diagnostics_and_zero_safe_baselines(): void
    {
        $this->fixture();
        $page = $this->page();
        $this->assertSame(4, $page->viewData('statusTotal'));
        $this->assertSame(25.0, $page->viewData('cancellations')['rate']);
        $this->assertSame(1, $page->viewData('counts')['no_sales']);
        $this->assertSame(1, $page->viewData('noSaleProducts')['count']);
        $this->assertSame(7, count($page->viewData('chart')['points']));
        $this->assertEquals(200, array_sum(array_column($page->viewData('chart')['points'], 'sales')));
        $page = $this->page(['range' => 'today']);
        $this->assertSame(0.0, $page->viewData('summary')['average']);
        $page->assertSee('No completed sales for this period.');
        $page = $this->page(['range' => 'all']);
        $this->assertNull($page->viewData('previous'));
        $this->assertSame('No comparable period', $page->viewData('performance'));
        $service = app(ShopSalesReportService::class);
        $stat = fn ($v) => ['sales' => $v, 'orders' => $v, 'units' => $v, 'average' => $v];
        foreach ([[200, 100, 'Improving'], [50, 100, 'Declining'], [100, 100, 'Stable'], [100, 0, 'New Activity'], [0, 0, 'No Sales']] as [$now,$before,$status]) {
            $this->assertSame($status, $service->comparison($stat($now), $stat($before))['performance']);
        }
    }

    public function test_json_fragments_exports_permissions_and_read_only_behavior(): void
    {
        $f = $this->fixture();
        $params = ['range' => 'week', 'shop' => $f['panda']->id, 'category' => $f['electronics']->id];
        $counts = [];
        foreach (['orders', 'seller_orders', 'order_items', 'products', 'payments'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $page = $this->get(route('admin.analytics.index', $params))->assertOk();
        $json = $this->getJson(route('admin.analytics.index', $params))->assertOk();
        $this->assertSame(view('admin.analytics.content', $page->original->getData())->render(), $json->json('content_html'));
        $this->get(route('admin.analytics.print', $params))->assertOk()->assertSee('₱90.00')->assertSee('Phone')->assertDontSee('Cable');
        $csv = $this->get(route('admin.analytics.csv', $params))->assertOk()->streamedContent();
        $this->assertStringContainsString('Phone', $csv);
        $this->assertStringNotContainsString('Cable', $csv);
        $pdf = $this->get(route('admin.analytics.pdf', $params))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->getJson(route('admin.analytics.index', ['range' => 'week']))->assertOk()->assertJsonStructure(['content_html', 'url']);
        $this->actingAs(User::factory()->create(['is_active' => true])->assignRole('buyer'));
        foreach (['index', 'pdf', 'print', 'csv'] as $action) {
            $this->get(route('admin.analytics.'.$action))->assertForbidden();
        }
        $this->actingAs($this->admin);
        $this->admin->roles()->first()->permissions()->detach();
        $this->admin->unsetRelation('roles');
        $this->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_decline_priorities_missing_items_and_zero_value_allocation(): void
    {
        $f = $this->fixture();
        $this->sale($f['panda'], 100, '2026-10-01', [[$f['phone'], 1, 100]]);
        $page = $this->page();
        $this->assertSame('Needs Attention', $page->viewData('insights')[0]['priority']);
        $page->assertSee('Sales decreased 33.3%');
        $page->assertSee('largest shop decrease');
        $this->sale($f['panda'], 60, '2026-10-06', []);
        $this->sale($f['panda'], 40, '2026-10-06', [[$f['phone'], 3, 0], [$f['cream'], 1, 0]]);
        $page = $this->page();
        $this->assertEquals(300, $page->viewData('categories')->sum('sales'));
        $this->assertEquals(60, $page->viewData('categories')->firstWhere('name', 'Uncategorized / unallocated')->sales);
        $this->assertEquals(200, $page->viewData('categories')->firstWhere('name', 'Electronics')->sales);
        // Historical items remain attributed when a product is soft deleted.
        $f['phone']->delete();
        $page = $this->page(['category' => $f['electronics']->id]);
        $this->assertSame(200.0, $page->viewData('summary')['sales']);
        $this->sale($f['panda'], 50, '2026-10-01', [[$f['cream'], 1, 50]]);
        $this->assertSame('Watch', $this->page(['range' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-11'])->viewData('insights')[0]['priority']);
    }

    public function test_shop_pagination_sort_and_queries_do_not_write_business_data(): void
    {
        $this->fixture();
        for ($i = 0; $i < 12; $i++) {
            $this->shop('Quiet Shop '.$i);
        }
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop)/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $page = $this->page(['sort' => 'orders_desc']);
        $this->assertSame(15, $page->viewData('shops')->total());
        $this->assertCount(10, $page->viewData('shops'));
        $this->assertSame('Panda Picks', $this->page(['sort' => 'sales_desc'])->viewData('shops')->first()->name);
        $this->assertEquals(0, $this->page(['sort' => 'sales_asc'])->viewData('shops')->first()->sales);
        $this->page(['sort' => 'growth']);
        $this->getJson(route('admin.analytics.index', ['range' => 'week', 'page' => 2]))->assertOk();
        $this->assertSame([], $writes);
    }

    public function test_completely_empty_marketplace_and_missing_completion_dates(): void
    {
        $page = $this->page(['range' => 'all']);
        $this->assertSame(0.0, $page->viewData('summary')['sales']);
        $this->assertSame([], $page->viewData('chart')['points']);
        $shop = $this->shop('Legacy Shop');
        $this->sale($shop, 50, '2026-10-06', [])->forceFill(['completed_at' => null])->save();
        $page = $this->page(['range' => 'all']);
        $this->assertSame(50.0, $page->viewData('summary')['sales']);
        $page->assertSee('No dated completed sales to plot.')->assertSee('no recorded completion date');
        $this->assertSame(0.0, $this->page()->viewData('summary')['sales']);
    }

    public function test_generate_opt_in_browser_fixtures(): void
    {
        if (! getenv('ANALYTICS_BROWSER_FIXTURES')) {
            $this->markTestSkipped('Opt-in SQLite browser fixtures.');
        }
        $f = $this->fixture();
        for ($i = 0; $i < 12; $i++) {
            $this->shop('Quiet Shop '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $dir = storage_path('app/analytics-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $queries = [['range' => 'week'], ['range' => '30days'], ['range' => 'month'], ['range' => 'today'], ['range' => 'all'], ['range' => 'week', 'shop' => (string) $f['tech']->id], ['range' => 'week', 'category' => (string) $f['beauty']->id], ['range' => 'week', 'status' => 'cancelled'], ['range' => 'week', 'sort' => 'sales_asc'], ['range' => 'week', 'page' => '2'], ['range' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-06']];
        $responses = [];
        foreach ($queries as $i => $query) {
            $html = $this->get(route('admin.analytics.index', $query))->assertOk()->getContent();
            file_put_contents($dir.'/analytics-'.$i.'.html', $html);
            $responses['analytics?'.http_build_query($query)] = $this->getJson(route('admin.analytics.index', $query))->assertOk()->json();
        }
        foreach (['week', '7days', '30days', 'month', 'today', 'all'] as $range) {
            file_put_contents($dir.'/overview-'.$range.'.html', $this->get(route('admin.analytics.index', ['range' => $range]))->assertOk()->getContent());
            $responses['analytics?range='.$range] = $this->getJson(route('admin.analytics.index', ['range' => $range]))->assertOk()->json();
        }
        file_put_contents($dir.'/print.html', $this->get(route('admin.analytics.print', ['range' => 'week']))->assertOk()->getContent());
        file_put_contents($dir.'/analytics.pdf', $this->get(route('admin.analytics.pdf', ['range' => 'week']))->assertOk()->getContent());
        file_put_contents($dir.'/responses.json', json_encode($responses));
        file_put_contents($dir.'/ids.json', json_encode(['tech' => $f['tech']->id, 'beauty' => $f['beauty']->id]));
    }

    public function test_analytics_visual_overview_chart_aggregates_match_reports_and_preserve_period_scopes(): void
    {
        $this->fixture();
        foreach (['today'=>'hour','7days'=>'day','30days'=>'week','month'=>'week','all'=>'month'] as $range=>$grouping) {
            $page=$this->get(route('admin.analytics.index',['range'=>$range]))->assertOk();
            $sales=$page->original->getData(); $chart=$sales['overview'];
            $this->assertSame($grouping,$chart['grouping']);
            $report=$this->get(route('admin.sales-reports.index',['range'=>$range]))->assertOk()->viewData('summary');
            $this->assertEquals($report['sales'],$sales['summary']['sales']);
            $this->assertEquals($report['sales'],array_sum(array_column($chart['trend'],'current')));
            $this->assertEquals($report['orders'],array_sum(array_column($chart['trend'],'currentOrders')));
            $this->assertEquals($sales['previous']['sales'] ?? 0,array_sum(array_column($chart['trend'],'previous')));
            $json=$this->getJson(route('admin.analytics.index',['range'=>$range]))->assertOk();
            $this->assertSame(view('admin.analytics.content',$sales)->render(),$json->json('content_html'));
        }
        $sales=$this->get(route('admin.analytics.index',['range'=>'week']))->assertOk()->original->getData();
        $this->assertEquals(['Electronics'=>170,'Beauty'=>30],array_column($sales['overview']['categories'],'value','label'));
        $this->assertEquals(['Phone'=>90,'Cable'=>80,'Cream'=>30],array_column($sales['overview']['products']['sales'],'value','label'));
        $this->assertSame(['Panda Picks','Tech Corner'],$sales['topShops']->pluck('name')->all());
        $this->assertEquals(['Cancelled'=>1,'Completed'=>2,'Pending'=>1],array_column($sales['overview']['statuses'],'value','label'));
    }

    public function test_analytics_visual_zero_states_and_admin_only_chart_response(): void
    {
        $page=$this->get(route('admin.analytics.index',['range'=>'all']))->assertOk()
            ->assertSee('No completed sales for this period.')
            ->assertSee('No shop sales available for this period.')
            ->assertSee('No products sold during this period.')
            ->assertSee('Sales Insights');
        foreach (['categories', 'period', 'comparison'] as $chart) {
            $page->assertSee('data-report-chart="'.$chart.'"', false);
        }
        $overview = $page->original->getData()['overview'];
        $this->assertCount(1, $overview['trend']);
        $this->assertEquals(0, $overview['trend'][0]['current']);
        $this->assertEmpty($overview['categories']);
        $page->assertSee('No completed sales yet.');
        $this->assertFalse($page->original->getData()['overview']['hasSales']);
        $this->assertNull($page->original->getData()['overview']['previous']);
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('buyer'));
        $this->getJson(route('admin.analytics.index'))->assertForbidden();
        auth()->logout();
        $this->getJson(route('admin.analytics.index'))->assertUnauthorized();
    }


    public function test_analytics_visual_refresh_urls_are_relative_behind_https_proxy_and_zero_periods_keep_axes(): void
    {
        Category::create(['name'=>'Actual category', 'slug'=>'actual-category']);
        foreach (['7days'=>7, '30days'=>5, 'month'=>5, 'all'=>1] as $range=>$count) {
            $url='/admin/analytics?range='.$range;
            $page=$this->withHeader('X-Forwarded-Proto','https')->get($url)->assertOk();
            $page->assertSee('action="/admin/analytics"', false);
            $overview=$page->original->getData()['overview'];
            $this->assertCount($count,$overview['trend']);
            $this->assertEquals(0,array_sum(array_column($overview['trend'],'current')));
            $this->assertSame(['Actual category'],$overview['categoryLabels']);
            $response=$this->getJson($url)->assertOk()->assertJsonPath('url',$url);
            $this->assertStringContainsString('data-report-chart="categories"',$response->json('content_html'));
            $this->assertStringContainsString('data-report-chart="period"',$response->json('content_html'));
            $this->assertStringContainsString('data-report-chart="comparison"',$response->json('content_html'));
        }
    }

    public function test_analytics_visual_chart_query_count_is_bounded_and_does_not_write_records(): void
    {
        $this->fixture();
        $service=app(\App\Services\AdminSalesAnalyticsService::class);
        $filters=$service->filters(\Illuminate\Http\Request::create('/','GET',['range'=>'week']));
        DB::enableQueryLog(); DB::flushQueryLog();
        $service->data($filters,true); $first=DB::getQueryLog();
        DB::disableQueryLog();
        for($i=0;$i<8;$i++)$this->shop('Additional Shop '.$i);
        DB::enableQueryLog(); DB::flushQueryLog();
        $service->data($filters,true); $second=DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(count($first),$second);
        foreach($second as $query)$this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|drop)/i',$query['query']);
    }

    public function test_dashboard_is_operational_and_analytics_has_one_visual_summary(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Recent Orders')->assertSee('Low Stock Alerts')
            ->assertSee('Recent Seller Applications')->assertSee('View Analytics')->assertDontSee('data-sales-chart-dashboard',false);
        $page=$this->get(route('admin.analytics.index'))->assertOk();
        $html=$page->getContent();
        $this->assertSame(1,substr_count($html,'name="range"'));
        $this->assertSame(1,substr_count($html,'data-sales-chart-dashboard'));
        foreach(['Marketplace Diagnostics','Sales by Shop','Order Status Analysis','Cancellation Trend','Period Comparison','Products with No Sales'] as $label)$page->assertSee($label);
        $page->assertDontSee('Diagnostic Insights');
        $this->assertSame(1,substr_count($html,'class="report-metrics"'));
        $f=$this->fixture();
        foreach ([['shop'=>$f['panda']->id],['category'=>$f['beauty']->id],['status'=>'cancelled'],['shop'=>$f['panda']->id,'category'=>$f['electronics']->id]] as $scope) {
            $filtered=$this->page($scope);
            $summary=$filtered->viewData('summary'); $overview=$filtered->viewData('overview');
            $this->assertEquals($summary['sales'],array_sum(array_column($overview['trend'],'current')));
            $this->assertEquals($summary['sales'],array_sum(array_column($overview['categories'],'value')));
            $this->assertEquals($filtered->viewData('previous')['sales'],array_sum(array_column($overview['trend'],'previous')));
            $this->assertEquals($filtered->viewData('statusTotal'),array_sum(array_column($overview['statuses'],'value')));
            $this->getJson(route('admin.analytics.index',['range'=>'week']+$scope))->assertOk();
        }
    }
}
