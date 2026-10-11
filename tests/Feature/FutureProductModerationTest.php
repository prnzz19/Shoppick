<?php
namespace Tests\Feature;

use App\Models\{User, SellerProfile, Store, Category, Product};
use App\Services\{ProductService, ProductModerationStateService, AdminSidebarCounts};
use App\Services\Moderation\ImageModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FutureProductModerationTest extends TestCase
{
    use RefreshDatabase;
    private Store $store;
    private Category $category;
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.image_moderation.queued'=>false]);
        $seller=User::factory()->create(['is_active'=>true,'registration_status'=>'approved']);
        $seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $this->store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Regression Shop','slug'=>'regression-shop','status'=>'active']);
        $this->category=Category::create(['name'=>'Home & Living','slug'=>'home-living','is_active'=>true]);
        $this->actingAs($seller);
    }
    private function provider(array|\Throwable $outcome): void
    {
        $this->app->instance(ImageModerationService::class,new class($outcome) implements ImageModerationService {
            public function __construct(private array|\Throwable $outcome){}
            public function scan(string $path): array { if($this->outcome instanceof \Throwable)throw $this->outcome;return $this->outcome; }
        });
    }
    private function create(string $name='Portable Desk Lamp'): Product
    {
        return app(ProductService::class)->create(['name'=>$name,'store_id'=>$this->store->id,'category_id'=>$this->category->id,'price'=>100,'stock'=>5,'publication_status'=>'published','is_active'=>true,'images'=>[$this->image()]])->fresh();
    }
    private function image(): UploadedFile { return UploadedFile::fake()->create('normal.jpg',10,'image/jpeg'); }
    private function normal(Product $product): void
    {
        $product->refresh();
        $this->assertContains($product->moderation_status,['clean','approved']);
        $this->assertNotSame('Review Required',$product->sellerStatus());
        $this->assertTrue($product->is_active);
    }
    public function test_new_normal_product_survives_provider_failure(): void
    {
        $this->provider(['status'=>'failed']);$this->normal($this->create());
    }
    public function test_new_normal_product_survives_timeout(): void
    {
        $this->provider(new \RuntimeException('timeout'));$this->normal($this->create());
    }
    public function test_new_normal_product_survives_processing_exception(): void
    {
        $this->provider(new \UnexpectedValueException('image processing failed'));$this->normal($this->create());
    }
    public function test_new_images_do_not_poison_normal_product(): void
    {
        $this->provider(['status'=>'safe']);$product=$this->create();
        $this->provider(new \RuntimeException('unavailable'));
        app(ProductService::class)->storeImages($product,[$this->image()]);
        $this->normal($product);
    }
    public function test_replacement_image_is_rechecked_and_optional_failure_is_not_blocking(): void
    {
        $this->provider(['status'=>'safe']);$product=$this->create();
        $this->provider(new \RuntimeException('unavailable'));
        $product->images()->first()->update(['path'=>'products/replacement.jpg']);
        $this->normal($product);
        $this->assertSame('scan_failed',$product->moderationScans()->first()->status);
    }
    public function test_repeated_edits_clear_stale_optional_failures(): void
    {
        $this->provider(new \RuntimeException('unavailable'));$product=$this->create();
        $product->update(['moderation_status'=>'scan_failed','is_active'=>false]);
        foreach(['Warm desk lighting','Adjustable home lighting','Portable lighting'] as $description){
            app(ProductService::class)->update($product,['description'=>$description]);$this->normal($product);
        }
        $this->post(route('seller.products.publication',$product),['action'=>'activate'])->assertSessionHasNoErrors();
        $this->normal($product);
    }
    public function test_successful_safe_scan_follows_normal_flow(): void
    {
        $this->provider(['status'=>'safe']);$this->normal($this->create());
    }
    public function test_successful_weapon_result_stays_blocked_on_activation(): void
    {
        $this->provider(['status'=>'flagged','category'=>'weapon','risk_level'=>'high']);$product=$this->create();
        $this->assertSame('Review Required',$product->sellerStatus());
        $this->assertFalse($product->is_active);
        $this->post(route('seller.products.publication',$product),['action'=>'activate'])->assertSessionHas('error');
        $this->assertFalse($product->fresh()->is_active);
    }
    public function test_text_or_category_safety_concern_requires_verification_during_outage(): void
    {
        $this->provider(new \RuntimeException('unavailable'));
        $product=$this->create('Hunting rifle');
        $this->assertSame('Review Required',$product->sellerStatus());
        $this->assertFalse($product->is_active);
        $category=Category::create(['name'=>'Firearms','slug'=>'firearms']);
        $normal=$this->create();
        app(ProductService::class)->update($normal,['category_id'=>$category->id]);
        $this->assertFalse($normal->fresh()->is_active);
    }
    public function test_edits_recalculate_mandatory_requirement_and_dont_reuse_old_success(): void
    {
        $this->provider(['status'=>'safe']);$product=$this->create();
        $this->provider(new \RuntimeException('unavailable'));
        app(ProductService::class)->update($product,['name'=>'Hunting rifle']);
        $this->assertFalse($product->fresh()->is_active);
        app(ProductService::class)->update($product,['name'=>'Desk lamp']);
        $this->normal($product);
    }
    public function test_consecutive_normal_products_during_outage_are_not_sent_to_review(): void
    {
        $this->provider(new \RuntimeException('provider offline'));
        foreach(['Lamp','Tumbler','Earphones','Mini fan'] as $name)$this->normal($this->create($name));
        $this->assertSame(0,app(AdminSidebarCounts::class)->get()['moderation']);
    }
    public function test_drafts_and_manual_rejections_are_preserved(): void
    {
        $this->provider(new \RuntimeException('offline'));$product=$this->create();
        $product->update(['publication_status'=>'draft']);
        app(ProductModerationStateService::class)->refresh($product);
        $this->assertFalse($product->fresh()->is_active);
        $scan=$product->moderationScans()->first();
        $scan->update(['status'=>'rejected','review_type'=>'manual']);
        $product->update(['publication_status'=>'published','suspension_reason'=>'Manual safety rejection']);
        app(ProductService::class)->update($product,['name'=>'Another lamp']);
        $this->assertSame('rejected',$product->fresh()->moderation_status);
    }
    public function test_local_file_validation_cannot_clear_weapon_concern(): void
    {
        $this->provider(['status'=>'safe','content_verified'=>false]);
        $this->assertFalse($this->create('Hunting rifle')->is_active);
    }

    public function test_reconciliation_is_dry_run_first_idempotent_and_preserves_safety_holds(): void
    {
        $this->provider(new \RuntimeException('offline'));
        $normal=$this->create();
        $normal->update(['moderation_status'=>'scan_failed','is_active'=>false]);
        $weapon=$this->create('Hunting rifle');
        $this->artisan('products:reconcile-optional-scans')->assertSuccessful();
        $this->assertSame('scan_failed',$normal->fresh()->moderation_status);
        $this->artisan('products:reconcile-optional-scans',['--apply'=>true])->assertSuccessful();
        $this->normal($normal);
        $this->assertFalse($weapon->fresh()->is_active);
        $this->artisan('products:reconcile-optional-scans',['--apply'=>true])->expectsOutput('Recovered: 0')->assertSuccessful();
    }

    public function test_removing_flagged_image_and_optional_failure_cannot_clear_a_safety_hold(): void
    {
        $this->provider(['status'=>'flagged','category'=>'weapon']);
        $product=$this->create();
        $product->images()->first()->delete();
        $this->provider(new \RuntimeException('offline'));
        app(ProductService::class)->storeImages($product,[$this->image()]);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('Review Required',$product->fresh()->sellerStatus());
    }

    public function test_required_failed_verification_cannot_be_activated(): void
    {
        $this->provider(new \RuntimeException('offline'));
        $product=$this->create('Hunting rifle');
        $this->post(route('seller.products.publication',$product),['action'=>'activate'])->assertSessionHas('error');
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_everyday_products_with_unknown_or_quality_only_results_do_not_require_review(): void
    {
        foreach (['USB desk lamp','Insulated tumbler','Bluetooth earbuds','Portable fan','Cotton shirt','Mixed nuts food pack','Fitness yoga mat'] as $name) {
            foreach ([[], ['status'=>null], ['status'=>'unknown'], ['status'=>'flagged','category'=>'blurry'], ['status'=>'uncertain','category'=>'low_resolution']] as $result) {
                $this->provider($result);
                $product = $this->create($name);
                $this->normal($product);
                $this->assertTrue(Product::publiclyVisible()->whereKey($product->id)->exists());
                $this->assertSame('scan_failed', $product->moderationScans()->first()->status);
            }
        }
        $this->assertSame(0, app(AdminSidebarCounts::class)->get()['moderation']);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_optional_queued_scans_do_not_hide_normal_products_or_count_as_admin_work(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['services.image_moderation.queued'=>true]);
        $product = $this->create();
        $this->normal($product);
        $this->assertSame('pending_scan', $product->moderationScans()->first()->status);
        $product->images()->first()->update(['path'=>'products/new-lamp.jpg']);
        $this->normal($product);
        $this->assertSame(0, app(AdminSidebarCounts::class)->get()['moderation']);
    }

    public function test_old_image_check_could_not_finish_state_is_reconciled_without_claiming_scan_success(): void
    {
        $this->provider(['status'=>'failed']);
        $product = $this->create('Portable fan');
        $scan = $product->moderationScans()->first();
        $scan->update(['failure_message'=>'Image check could not finish.']);
        $before = $scan->fresh()->getAttributes();
        $product->update(['moderation_status'=>'scan_failed','is_active'=>false]);
        $this->assertSame('Review Required', $product->fresh()->sellerStatus());
        $this->artisan('products:reconcile-optional-scans',['--apply'=>true])->expectsOutput('Recovered: 1')->assertSuccessful();
        $this->normal($product);
        $this->assertSame($before, $scan->fresh()->getAttributes());
    }

    public function test_optional_checks_preserve_inactive_draft_archived_and_suspended_shop_visibility(): void
    {
        $this->provider(['status'=>'unknown']);
        $product = $this->create();
        $product->update(['is_active'=>false]);
        app(ProductService::class)->update($product, ['description'=>'Everyday portable lighting']);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertFalse(Product::publiclyVisible()->whereKey($product->id)->exists());
        $product->update(['publication_status'=>'draft']);
        app(ProductModerationStateService::class)->refresh($product);
        $this->assertSame('Draft', $product->fresh()->sellerStatus());
        $product->delete();
        app(ProductModerationStateService::class)->refresh($product);
        $this->assertFalse($product->is_active);
        $this->assertSame('Archived', $product->sellerStatus());
        $this->store->update(['status'=>'suspended']);
        $other = $this->create('Another lamp');
        $this->assertFalse($other->is_active);
        $this->assertFalse(Product::publiclyVisible()->whereKey($other->id)->exists());
    }

    public function test_weapon_text_requires_human_review_even_with_automatic_safe_image_result(): void
    {
        $this->provider(['status'=>'safe']);
        $product = $this->create('Synthetic rifle listing');
        $this->assertSame('Review Required', $product->sellerStatus());
        $this->assertFalse($product->is_active);
        $this->assertSame(1, app(AdminSidebarCounts::class)->get()['moderation']);
        $this->post(route('seller.products.publication',$product),['action'=>'activate'])->assertSessionHas('error');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_active'=>true]);
        $admin->assignRole('admin');
        $scan = $product->moderationScans()->first();
        $this->actingAs($admin)->get(route('admin.moderation.index',['status'=>'pending_scan']))->assertOk()->assertSee($product->name);
        $this->get(route('admin.moderation.show',$scan))->assertOk()->assertSee('Admin Final Review')->assertSee('Listing safety review required');
        $this->post(route('admin.moderation.review',$scan),['decision'=>'false_positive','notes'=>'Synthetic fixture reviewed.'])->assertSessionHasNoErrors();
        $this->normal($product);
    }

    public function test_weapon_evidence_wins_over_unknown_status_and_quality_failure(): void
    {
        $this->provider(['status'=>'unknown','category'=>'weapon']);
        $product = $this->create('Test fixture');
        $this->assertSame('Review Required', $product->sellerStatus());
        $this->assertDatabaseHas('reports',['target_id'=>$product->id,'source'=>'automated_moderation']);
        $this->provider(['status'=>'flagged','category'=>'blurry']);
        $this->assertFalse($this->create('Synthetic pistol fixture')->is_active);
    }

    public function test_weapon_without_image_is_not_published_and_description_category_are_checked(): void
    {
        $product = app(ProductService::class)->create(['name'=>'Synthetic weapon fixture','store_id'=>$this->store->id,'category_id'=>$this->category->id,'price'=>100,'stock'=>2,'publication_status'=>'published','is_active'=>true]);
        $this->assertSame('Review Required',$product->sellerStatus());
        $this->assertFalse($product->is_active);
        $this->provider([]);
        $normal = $this->create();
        app(ProductService::class)->update($normal,['description'=>'Synthetic ammunition test fixture']);
        $this->assertFalse($normal->fresh()->is_active);
    }

    public function test_seller_create_and_image_edit_remain_normal_and_other_seller_cannot_edit(): void
    {
        $this->provider(['status'=>'unknown']);
        $data = ['name'=>'Ordinary USB lamp','category_id'=>$this->category->id,'price'=>100,'stock'=>4,'publication_action'=>'publish','images'=>[$this->image()]];
        $this->post(route('seller.products.store'),$data)->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));
        $product = Product::where('name','Ordinary USB lamp')->firstOrFail();
        $this->normal($product);
        $this->put(route('seller.products.update',$product),['name'=>$product->name,'category_id'=>$this->category->id,'price'=>120,'stock'=>4,'is_active'=>1,'images'=>[$this->image()]])->assertSessionHasNoErrors();
        $this->normal($product);
        $this->get(route('seller.products.index'))->assertOk()->assertSee('Ordinary USB lamp')->assertDontSee('Safety verification requires Admin');
        $outsider = User::factory()->create(['is_active'=>true]);
        $outsider->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$outsider->id,'status'=>'approved']);
        Store::create(['user_id'=>$outsider->id,'seller_profile_id'=>$profile->id,'name'=>'Other shop','slug'=>'other-shop','status'=>'active']);
        $this->actingAs($outsider)->put(route('seller.products.update',$product),['name'=>'Unauthorized'])->assertForbidden();
        $this->assertSame('Ordinary USB lamp',$product->fresh()->name);
    }
}
