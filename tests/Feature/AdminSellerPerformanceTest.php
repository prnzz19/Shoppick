<?php
namespace Tests\Feature;

use App\Models\{Order, Permission, Role, SellerOrder, SellerProfile, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSellerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-08 12:00:00'));
        $role = Role::firstOrCreate(['slug'=>'admin'], ['name'=>'Admin','guard_name'=>'web']);
        $permission = Permission::firstOrCreate(['slug'=>'manage_sellers'], ['name'=>'Manage sellers','guard_name'=>'web']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('admin'));
    }

    private function seller(string $name, string $shopName): array
    {
        $user = User::factory()->create(['name'=>$name,'is_active'=>true])->assignRole('buyer','seller');
        $profile = SellerProfile::create(['user_id'=>$user->id,'status'=>'approved','approved_at'=>now()]);
        $store = Store::create(['user_id'=>$user->id,'seller_profile_id'=>$profile->id,'name'=>$shopName,'slug'=>'shop-'.$user->id,'status'=>'active']);
        return [$user,$profile,$store];
    }

    private function sale(Store $store, string $date, int $total, string $status = 'completed', ?Order $parent = null): SellerOrder
    {
        $parent ??= Order::create(['user_id'=>$store->user_id,'order_number'=>'P-'.uniqid(),'payment_method'=>'cod','payment_status'=>'paid','total'=>9999]);
        return SellerOrder::create(['order_id'=>$parent->id,'store_id'=>$store->id,'seller_order_number'=>'S-'.uniqid(),'status'=>$status,'subtotal'=>$total,'seller_total'=>$total,'completed_at'=>$date]);
    }

    private function page(array $filters = [])
    {
        $page = $this->get(route('admin.sellers.index',$filters))->assertOk();
        $async = $this->getJson(route('admin.sellers.filter',$filters))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('total', $page->viewData('sellers')->total())
            ->assertJsonPath('page', $page->viewData('sellers')->currentPage());
        foreach (['summary','table','pagination','tabs','period'] as $partial) {
            $this->assertSame(view('admin.sellers.partials.'.$partial, $page->viewData())->render(), $async->json($partial.'_html'));
        }
        return $page;
    }

    public function test_search_shop_and_actual_status_filters(): void
    {
        [$a,$profile,$store] = $this->seller('Unique Seller','Panda Picks');
        [$b,$otherProfile,$otherStore] = $this->seller('Other Seller','Home Finds');
        foreach ([$a->name,$a->email,$store->name] as $search) {
            $this->page(['q'=>$search])->assertViewHas('sellers',fn($s)=>$s->total()===1 && $s->first()->id===$a->id);
        }
        $this->page(['shop'=>$store->id])->assertSee($a->email)->assertDontSee($b->email);
        $otherProfile->update(['status'=>'suspended']);
        $otherStore->update(['status'=>'suspended']);
        $this->page(['status'=>'suspended'])->assertSee($b->email)->assertDontSee($a->email);
        $this->page(['shop_status'=>'active'])->assertSee($a->email)->assertDontSee($b->email);
        $this->get(route('admin.sellers.show',$b))->assertOk();
    }

    public function test_date_presets_and_custom_range_use_completion_date_with_inclusive_boundaries(): void
    {
        [$user,,$store] = $this->seller('Dates','Dates Shop');
        foreach (['2026-10-08 00:00:00','2026-10-08 23:59:59','2026-10-02 00:00:00','2026-09-09 00:00:00','2026-09-08 23:59:59'] as $date) $this->sale($store,$date,100);
        foreach (['today'=>2,'7days'=>3,'30days'=>4,'month'=>3,'last_month'=>2,'all'=>5] as $range=>$count) {
            $this->page(['range'=>$range])->assertViewHas('sellers',fn($s)=>(int)$s->first()->orders_count===$count && (float)$s->first()->sales_total===$count*100.0)
                ->assertViewHas('summary',fn($s)=>(int)$s->orders===$count && (float)$s->sales===$count*100.0);
        }
        $this->page(['range'=>'custom','from'=>'2026-10-02','to'=>'2026-10-02'])->assertViewHas('summary',fn($s)=>(int)$s->orders===1);
        $this->get(route('admin.sellers.index',['range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('admin.sellers.index',['range'=>'custom']))->assertSessionHasErrors(['from','to']);
    }

    public function test_vendor_totals_sorting_zero_sales_and_lifecycle_exclusions(): void
    {
        [$a,,$storeA] = $this->seller('Alpha','Alpha Shop');
        [$b,,$storeB] = $this->seller('Beta','Beta Shop');
        [$zero] = $this->seller('Zero','Zero Shop');
        $first = $this->sale($storeA,'2026-10-08',300);
        $this->sale($storeB,'2026-10-08',100,'completed',$first->order);
        $this->sale($storeB,'2026-10-08',100);
        foreach (['pending','cancelled','delivered'] as $status) $this->sale($storeA,'2026-10-08',5000,$status);
        // Failed/refunded payments do not create an additional sales source; existing analytics uses vendor completion.
        $this->sale($storeA,'2026-10-08',5000,'pending')->order->update(['payment_status'=>'failed']);
        $this->sale($storeA,'2026-10-08',5000,'cancelled')->order->update(['payment_status'=>'refunded']);
        $this->page(['sort'=>'sales_desc'])->assertViewHas('sellers',fn($s)=>$s->first()->id===$a->id && (float)$s->first()->sales_total===300.0)
            ->assertViewHas('summary',fn($s)=>(float)$s->sales===500.0 && (int)$s->orders===3)->assertSee('₱0.00');
        $this->page(['sort'=>'orders_desc'])->assertViewHas('sellers',fn($s)=>$s->first()->id===$b->id);
        $this->page(['sales'=>'none'])->assertViewHas('sellers',fn($s)=>$s->total()===1 && $s->first()->id===$zero->id);
        $this->page(['sales'=>'with'])->assertViewHas('sellers',fn($s)=>$s->total()===2);
    }

    public function test_pagination_keeps_filters_and_archived_history_survives_restore(): void
    {
        for ($i=0;$i<16;$i++) $this->seller('Page Seller '.$i,'Page Shop '.$i);
        $this->page(['q'=>'Page Seller','range'=>'30days','status'=>'approved','sort'=>'sales_desc','sales'=>'none'])
            ->assertViewHas('sellers',fn($s)=>$s->perPage()===15 && $s->total()===16 && str_contains($s->url(2),'range=30days') && str_contains($s->url(2),'sales=none'));
        [$user,$profile,$store] = $this->seller('Archived Name','Archived Shop');
        $sale = $this->sale($store,'2026-10-08',123);
        $this->patch(route('admin.sellers.archive',$profile))->assertSessionHas('success');
        $this->page()->assertDontSee($user->email);
        $this->page(['tab'=>'archived'])->assertSee($user->email)->assertSee('Restore')->assertViewHas('summary',fn($s)=>(float)$s->sales===123.0);
        $this->patch(route('admin.sellers.restore',$profile))->assertSessionHas('success');
        $this->page(['q'=>$user->email])->assertSee($user->email)->assertSee('₱123.00');
        $this->assertDatabaseHas('seller_orders',['id'=>$sale->id,'seller_total'=>123,'status'=>'completed']);
    }

    public function test_non_admin_and_admin_without_permission_cannot_access(): void
    {
        foreach (['buyer','seller','admin'] as $role) {
            $user=User::factory()->create(['is_active'=>true])->assignRole($role);
            if ($role==='admin') $user->roles()->first()->permissions()->detach();
            $this->actingAs($user)->get(route('admin.sellers.index'))->assertForbidden();
        }
    }

    public function test_eager_loading_keeps_query_count_constant_and_clear_filters_preserves_archive_tab(): void
    {
        $this->seller('Single','Single Shop');
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->page();
        $single = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        for ($i=0;$i<14;$i++) $this->seller('Many '.$i,'Many Shop '.$i);
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->page();
        $this->assertLessThanOrEqual($single,count(\Illuminate\Support\Facades\DB::getQueryLog()));
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->page()->assertSee('hidden >Clear Filters', false);
        $this->page(['q'=>'Single'])->assertSee('Clear Filters');
        $this->page(['tab'=>'archived','q'=>'missing'])->assertSee('No sellers match your filters.')
            ->assertSee(route('admin.sellers.index',['tab'=>'archived']),false);
    }

    public function test_async_combined_filters_and_matching_summary_cards(): void
    {
        [$a,,$store] = $this->seller('Tech Seller','Tech Corner');
        [$b,$profileB,$storeB] = $this->seller('Other Tech Seller','Other Shop');
        $this->sale($store,'2026-10-08',100);
        $this->sale($store,'2026-09-01',900);
        $this->sale($storeB,'2026-10-08',400);
        $storeB->update(['status'=>'suspended']);
        $filters=['q'=>'Tech','shop'=>$store->id,'range'=>'30days','status'=>'approved','shop_status'=>'active','sales'=>'with','sort'=>'sales_desc'];
        $this->page($filters)->assertViewHas('summary',fn($s)=>(int)$s->sellers===1 && (int)$s->active_shops===1 && (float)$s->sales===100.0 && (int)$s->orders===1);
        $this->page(['shop_status'=>'suspended'])->assertViewHas('summary',fn($s)=>(int)$s->sellers===1 && (int)$s->active_shops===0);
        $this->page(['q'=>'nothing matches'])->assertViewHas('summary',fn($s)=>(int)$s->sellers===0 && (int)$s->active_shops===0 && (float)$s->sales===0.0);
    }

    public function test_async_custom_aliases_and_invalid_values(): void
    {
        [$user,,$store] = $this->seller('Custom','Custom Shop');
        $this->sale($store,'2026-10-08',99);
        $aliasResponse = $this->getJson(route('admin.sellers.filter',['range'=>'custom','date_from'=>'2026-10-08','date_to'=>'2026-10-08']))
            ->assertOk()->assertJsonPath('query.from','2026-10-08')->assertJsonPath('query.to','2026-10-08');
        $this->assertStringContainsString('₱99.00',$aliasResponse->json('table_html'));
        foreach (['sort'=>'users.password','range'=>'bad','status'=>'invented','shop_status'=>'inactive','sales'=>'bad','page'=>0,'tab'=>'bad','shop'=>999999,'q'=>['array']] as $key=>$value) {
            $this->getJson(route('admin.sellers.filter',[$key=>$value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->getJson(route('admin.sellers.filter',['range'=>'custom']))->assertUnprocessable()->assertJsonValidationErrors(['from','to']);
        $this->getJson(route('admin.sellers.filter',['range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('admin.sellers.filter',['range'=>'custom','from'=>'not-a-date','to'=>'2026-10-08']))->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_async_urls_are_normal_page_urls_with_validated_filter_state(): void
    {
        for ($i=0;$i<16;$i++) $this->seller('Paged '.$i,'Paged Shop '.$i);
        $response=$this->getJson(route('admin.sellers.filter',['q'=>'Paged','range'=>'30days','sort'=>'name','page'=>2,'unknown'=>'discard']))->assertOk()
            ->assertJsonPath('page',2)->assertJsonPath('total',16)->assertJsonPath('query.q','Paged');
        $this->assertStringNotContainsString('/sellers/filter',$response->json('pagination_html'));
        $this->assertStringContainsString('range=30days',$response->json('pagination_html'));
        $this->assertStringContainsString('sort=name',$response->json('url'));
        $this->assertArrayNotHasKey('unknown',$response->json('query'));
        $this->getJson(route('admin.sellers.filter',['range'=>'all','sort'=>'newest','page'=>1]))->assertOk()->assertJsonPath('url',route('admin.sellers.index'));
    }

    public function test_async_endpoint_is_protected_by_auth_role_and_permission(): void
    {
        auth()->logout();
        $this->getJson(route('admin.sellers.filter'))->assertUnauthorized();
        foreach (['buyer','seller','rider','logistics','admin'] as $role) {
            $user=User::factory()->create(['is_active'=>true])->assignRole($role);
            if ($role==='admin') $user->roles()->first()->permissions()->detach();
            $this->actingAs($user)->getJson(route('admin.sellers.filter'))->assertForbidden();
        }
    }

    public function test_export_isolated_browser_fixtures_when_requested(): void
    {
        if (getenv('SHOPPICK_SELLER_BROWSER_FIXTURES') !== '1') {
            $this->markTestSkipped('Opt-in fixture export for the isolated browser test.');
        }
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        [$panda,,$pandaStore] = $this->seller('Panda Seller','Panda Picks');
        [$tech,,$techStore] = $this->seller('Tech Seller','Tech Corner');
        $this->seller('Quiet Seller','Quiet Shop');
        [$dormant,$profile,$store] = $this->seller('Dormant Seller','Dormant Shop');
        $profile->update(['status'=>'suspended']);
        $store->update(['status'=>'suspended']);
        $this->sale($pandaStore,'2026-10-08',150);
        $this->sale($pandaStore,'2026-09-01',9000);
        $this->sale($techStore,'2026-10-08',50);
        $this->sale($techStore,'2026-09-01',5000);
        $this->sale($store,'2026-10-08',75);
        for ($i=0;$i<14;$i++) $this->seller('Fixture Seller '.$i,'Fixture Shop '.$i);
        [$archived,$archivedProfile,$archivedStore]=$this->seller('Archived Seller','Archived Shop');
        $archivedProfile->forceFill(['archived_at'=>now()])->save();
        $this->sale($archivedStore,'2026-10-08',200);

        $filters = [[], ['page'=>2], ['shop'=>$pandaStore->id], ['range'=>'30days'], ['status'=>'suspended'],
            ['shop_status'=>'suspended'], ['sales'=>'none'], ['sort'=>'orders_desc'], ['sort'=>'sales_desc'],
            ['q'=>'Tech'], ['q'=>'Panda'], ['q'=>'no matching seller'], ['tab'=>'archived'],
            ['tab'=>'archived','q'=>'Archived'], ['tab'=>'archived','range'=>'30days'],
            ['range'=>'custom','from'=>'2026-10-08','to'=>'2026-10-08']];
        $combined=['shop'=>$pandaStore->id];
        foreach (['range'=>'30days','status'=>'approved','sort'=>'sales_desc','shop_status'=>'active','sales'=>'with'] as $key=>$value) {
            $combined[$key]=$value;
            $filters[]=$combined;
        }
        $responses=[];
        foreach ($filters as $params) {
            $responses[]=['params'=>$params,'data'=>$this->getJson(route('admin.sellers.filter',$params))->assertOk()->json()];
        }
        $html=$this->get(route('admin.sellers.index'))->assertOk()->getContent();
        file_put_contents(storage_path('app/admin-seller-browser-fixtures.json'),json_encode([
            'html'=>$html,'responses'=>$responses,'panda_shop_id'=>$pandaStore->id,
        ],JSON_THROW_ON_ERROR));
    }
}
