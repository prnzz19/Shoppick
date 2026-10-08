<?php

namespace Tests\Feature;

use App\Models\{Category, Order, OrderItem, Permission, Product, Role, SellerOrder, SellerProfile, Store, User};
use App\Services\ShopSalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopSalesReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));
        $role=Role::firstOrCreate(['slug'=>'admin'],['name'=>'Admin','guard_name'=>'web']);
        foreach (['view_shops','manage_inventory'] as $slug) {
            $permission=Permission::firstOrCreate(['slug'=>$slug],['name'=>$slug,'guard_name'=>'web']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('admin'));
    }

    private function shop(string $name='Panda Picks'): Store
    {
        $user=User::factory()->create(['name'=>'Demo Seller','is_active'=>true])->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$user->id,'status'=>'approved']);
        return Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>$name,'slug'=>'shop-'.$user->id,'status'=>'active']);
    }

    private function order(Store $shop, string $completed, float $sales, int $units=1, string $status='completed', ?string $placed=null): SellerOrder
    {
        $parent=Order::create(['user_id'=>$shop->user_id,'order_number'=>'BUYER-'.uniqid(),'payment_method'=>'cod','payment_status'=>'paid','total'=>99999,'buyer_name'=>'María Dela Cruz']);
        $order=SellerOrder::create(['order_id'=>$parent->id,'store_id'=>$shop->id,'seller_order_number'=>'VENDOR-'.uniqid(),'status'=>$status,'subtotal'=>$sales,'seller_total'=>$sales,'completed_at'=>$status==='completed' ? $completed : null]);
        $order->forceFill(['created_at'=>$placed ?? $completed,'updated_at'=>$placed ?? $completed])->save();
        $category=Category::firstOrCreate(['slug'=>'sale-fixture'],['name'=>'Sale Fixture']);
        $product=Product::firstOrCreate(['store_id'=>$shop->id,'slug'=>'sale-fixture-'.$shop->id],['category_id'=>$category->id,'name'=>'Panda Product','price'=>10,'stock'=>20,'low_stock_threshold'=>5]);
        OrderItem::create(['order_id'=>$parent->id,'seller_order_id'=>$order->id,'product_id'=>$product->id,'product_name'=>'Panda Product','price'=>10,'quantity'=>$units,'total'=>$units*10]);
        return $order;
    }

    private function fixture(): Store
    {
        $shop=$this->shop();
        $this->order($shop,'2026-10-06 12:00:00',150,3,'completed','2026-09-01');
        $this->order($shop,'2026-10-01',100,2);
        $this->order($shop,'2026-10-07',800,1,'cancelled');
        $failed=$this->order($shop,'2026-10-08',900,1,'pending');
        $failed->order->update(['payment_status'=>'failed']);
        $this->order($this->shop('Other Shop Secret'),'2026-10-06',9999,5);
        return $shop;
    }

    public function test_web_print_and_pdf_use_identical_filtered_data_and_a_real_pdf(): void
    {
        $shop=$this->fixture();
        $spy=new class extends ShopSalesReportService {
            public array $observed=[];
            public function build(Store $shop,array $filters): array { $data=parent::build($shop,$filters); $this->observed[]=$data; return $data; }
        };
        $this->app->instance(ShopSalesReportService::class,$spy);
        $params=[$shop,'range'=>'week'];
        $web=$this->get(route('admin.shops.sales-report',$params))->assertOk()->assertSee('₱150.00')->assertDontSee('Other Shop Secret');
        $pdf=$this->get(route('admin.shops.sales-report.pdf',$params))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$pdf->getContent());
        $this->assertStringContainsString('2026-10-05_to_2026-10-11.pdf',$pdf->headers->get('Content-Disposition'));
        $this->get(route('admin.shops.sales-report.print',$params))->assertOk()->assertSee('₱150.00')->assertSee('Improving');
        foreach ($spy->observed as $data) {
            $this->assertSame($web->viewData('summary'),$data['summary']);
            $this->assertSame($web->viewData('changes'),$data['changes']);
            $this->assertSame('Improving',$data['performance']);
            $this->assertSame($shop->id,$data['shop']->id);
        }
        $this->assertSame(3,count($spy->observed));
        $this->assertSame(1,$web->viewData('summary')['orders']);
        $this->assertSame(3,$web->viewData('summary')['units']);
        $this->assertSame(100.0,$web->viewData('previous')['sales']);
        $this->assertSame(50.0,$web->viewData('changes')['sales']['percent']);
        $this->assertSame(3,$web->viewData('recentOrders')->count());
        if (getenv('SHOPPICK_REPORT_BROWSER_FIXTURES')==='1') {
            $dir=storage_path('app/shop-report-fixtures');
            if (!is_dir($dir)) mkdir($dir,0777,true);
            file_put_contents($dir.'/web.html',$web->getContent());
            file_put_contents($dir.'/report.pdf',$pdf->getContent());
            file_put_contents($dir.'/print.html',$this->get(route('admin.shops.sales-report.print',$params))->getContent());
            file_put_contents($dir.'/report.csv',$this->get(route('admin.shops.sales-report.csv',$params))->streamedContent());
        }
    }

    public function test_print_document_has_all_sections_without_admin_navigation_or_buttons(): void
    {
        $shop=$this->fixture();
        $this->get(route('admin.shops.sales-report.print',[$shop,'range'=>'week']))->assertOk()
            ->assertSee('SHOPPICK')->assertSee('Report Period')->assertSee('Generated:')->assertSee('Performance: Improving')
            ->assertSee('Top Products')->assertSee('Order Status Breakdown')->assertSee('Recent Orders')->assertSee('Current Inventory Snapshot')
            ->assertSee('A4 portrait')->assertSee('window.print()',false)->assertDontSee('<aside',false)->assertDontSee('<button',false)
            ->assertDontSee('Admin Panel')->assertDontSee('Export Report')->assertDontSee('pagination');
    }

    public function test_csv_is_utf8_and_one_row_per_vendor_order_with_completed_totals_matching_web(): void
    {
        $shop=$this->fixture();
        $web=$this->get(route('admin.shops.sales-report',[$shop,'range'=>'week']))->assertOk();
        $response=$this->get(route('admin.shops.sales-report.csv',[$shop,'range'=>'week']))->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8');
        $csv=$response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
        $this->assertStringContainsString('María Dela Cruz',$csv);
        $this->assertStringNotContainsString('Other Shop Secret',$csv);
        $stream=fopen('php://memory','r+'); fwrite($stream,substr($csv,3)); rewind($stream);
        $header=fgetcsv($stream,0,',','"',''); $rows=[];
        while (($row=fgetcsv($stream,0,',','"',''))!==false) $rows[]=array_combine($header,$row);
        fclose($stream);
        $this->assertCount(3,$rows);
        $this->assertSame($web->viewData('summary')['sales'],array_sum(array_map(fn($r)=>(float)$r['Recognized Completed Sales (PHP)'],$rows)));
        $this->assertSame($web->viewData('summary')['orders'],count(array_filter($rows,fn($r)=>$r['Order Status']==='completed')));
        $this->assertSame(3,count(array_unique(array_column($rows,'Order Number'))));
        foreach ($rows as $row) $this->assertSame('2026-10-05',$row['Report Start']);
    }

    public function test_exports_preserve_custom_status_and_search_filters_and_sanitize_filenames_and_csv_cells(): void
    {
        $shop=$this->shop('../../Ñandú / Shop');
        $sale=$this->order($shop,'2026-10-08',50,2);
        $sale->order->update(['buyer_name'=>'=HYPERLINK("bad")']);
        $sale->items()->first()->update(['product_name'=>'+SUM(1,2)']);
        $params=[$shop,'range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-08','status'=>'completed','q'=>$sale->seller_order_number];
        $web=$this->get(route('admin.shops.sales-report',$params))->assertOk();
        foreach (['pdf','print','csv'] as $format) $web->assertSee(route('admin.shops.sales-report.'.$format,$params));
        $pdf=$this->get(route('admin.shops.sales-report.pdf',$params))->assertOk();
        $this->assertStringNotContainsString('../',$pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('2026-10-08_to_2026-10-08.pdf',$pdf->headers->get('Content-Disposition'));
        $csv=$this->get(route('admin.shops.sales-report.csv',$params))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->assertStringContainsString("'+SUM",$csv);
        $this->get(route('admin.shops.sales-report.pdf',$shop))->assertOk()->assertHeader('Content-Disposition','attachment; filename="SHOPPICK_nandu-shop_Sales_Report_All-Time.pdf"');
    }

    public function test_every_report_and_export_route_requires_admin_and_shop_view_permission(): void
    {
        $shop=$this->shop();
        auth()->logout();
        foreach (['','pdf','print','csv'] as $format) $this->getJson(route('admin.shops.sales-report'.($format ? '.'.$format : ''),$shop))->assertUnauthorized();
        foreach (['buyer','seller','rider','logistics','admin'] as $role) {
            $actor=User::factory()->create(['is_active'=>true])->assignRole($role);
            if ($role==='admin') $actor->roles()->first()->permissions()->detach();
            foreach (['','pdf','print','csv'] as $format) $this->actingAs($actor)->getJson(route('admin.shops.sales-report'.($format ? '.'.$format : ''),$shop))->assertForbidden();
        }
    }

    public function test_reporting_reads_do_not_write_business_records_and_inventory_link_is_available(): void
    {
        $shop=$this->fixture();
        $this->get(route('admin.inventory.index',['shop'=>$shop->id]))->assertOk()->assertSee(route('admin.shops.sales-report',$shop));
        $category=Category::create(['name'=>'Report Category','slug'=>'report-category']);
        foreach ([0,2,20] as $stock) Product::create(['store_id'=>$shop->id,'category_id'=>$category->id,'name'=>'Inventory '.$stock,'slug'=>'inventory-'.$stock,'price'=>10,'stock'=>$stock,'low_stock_threshold'=>5]);
        DB::enableQueryLog();
        foreach (['','pdf','print','csv'] as $format) {
            $response=$this->get(route('admin.shops.sales-report'.($format ? '.'.$format : ''),[$shop,'range'=>'week']))->assertOk();
            if ($format==='csv') $response->streamedContent();
            if ($format==='') $this->assertSame([4,1,1],[(int)$response->viewData('inventory')->products,(int)$response->viewData('inventory')->low_stock,(int)$response->viewData('inventory')->out_of_stock]);
        }
        $writes=array_filter(DB::getQueryLog(),fn($entry)=>preg_match('/^\s*(insert|update|delete|alter|create|drop|replace)\b/i',$entry['query']));
        DB::disableQueryLog();
        $this->assertSame([],$writes);
    }

    public function test_performance_states_handle_zero_baselines_and_mixed_changes(): void
    {
        $service=new ShopSalesReportService();
        $filters=$service->filters(\Illuminate\Http\Request::create('/','GET',['range'=>'week']));
        foreach (['No Sales'=>[[],[]],'New Activity'=>[[10],[]],'Stable'=>[[10],[10]],'Improving'=>[[20],[10]],'Declining'=>[[],[10]],'Mixed Performance'=>[[10,10],[15]]] as $expected=>[$current,$previous]) {
            $shop=$this->shop($expected);
            foreach ($current as $total) $this->order($shop,'2026-10-06',$total);
            foreach ($previous as $total) $this->order($shop,'2026-10-01',$total);
            $data=$service->build($shop,$filters);
            $this->assertSame($expected,$data['performance']);
            if ($expected==='New Activity') $this->assertNull($data['changes']['sales']['percent']);
        }
    }

    public function test_all_exports_reject_invalid_periods_and_statuses(): void
    {
        $shop=$this->shop();
        foreach (['pdf','print','csv'] as $format) {
            foreach ([['range'=>'custom'],['range'=>'invented'],['range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-01'],['status'=>'invented']] as $invalid) {
                $this->getJson(route('admin.shops.sales-report.'.$format,[$shop]+$invalid))->assertUnprocessable();
            }
        }
    }
}
