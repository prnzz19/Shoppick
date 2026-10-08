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
        $dash = $this->get(route('admin.dashboard', ['range' => 'week']))->assertOk()->viewData('sales');
        $this->assertSame($summary, $dash['summary']);
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
        $page->assertSee('No completed sales were recorded for this period.');
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
        $this->getJson(route('admin.dashboard', ['range' => 'week']))->assertOk()->assertJsonStructure(['content_html', 'url']);
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
        $page->assertSee('No dated completed sales are available to plot.')->assertSee('no recorded completion date');
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
        foreach (['week', '30days', 'month', 'today'] as $range) {
            file_put_contents($dir.'/dashboard-'.$range.'.html', $this->get(route('admin.dashboard', ['range' => $range]))->assertOk()->getContent());
            $responses['dashboard?range='.$range] = $this->getJson(route('admin.dashboard', ['range' => $range]))->assertOk()->json();
        }
        file_put_contents($dir.'/print.html', $this->get(route('admin.analytics.print', ['range' => 'week']))->assertOk()->getContent());
        file_put_contents($dir.'/analytics.pdf', $this->get(route('admin.analytics.pdf', ['range' => 'week']))->assertOk()->getContent());
        file_put_contents($dir.'/responses.json', json_encode($responses));
        file_put_contents($dir.'/ids.json', json_encode(['tech' => $f['tech']->id, 'beauty' => $f['beauty']->id]));
    }
}
