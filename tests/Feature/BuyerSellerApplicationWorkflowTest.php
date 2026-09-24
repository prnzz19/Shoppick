<?php
namespace Tests\Feature;
use App\Models\{User, Category, SellerApplication, SellerProfile, Store, Product};
use App\Services\{AdminSidebarCounts, SellerRegistrationService};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage, Mail, DB};
use Tests\TestCase;
class BuyerSellerApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private User $buyer;
    private User $admin;
    private Category $category;
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); Storage::fake('public'); Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->buyer=User::factory()->create(['phone'=>'+639171234567','is_active'=>true,'registration_type'=>'buyer','registration_status'=>'approved']);
        $this->buyer->assignRole('buyer');
        $this->admin=User::factory()->create(['is_active'=>true]);$this->admin->assignRole('admin');
        $this->category=Category::create(['name'=>'Test category','slug'=>'test-category','is_active'=>true]);
    }
    private function payload(): array
    {
        return ['store_name'=>'Buyer Shop','store_description'=>'Useful products','category_id'=>$this->category->id,'phone'=>'09171234567','address'=>'Manila',
            'valid_id'=>UploadedFile::fake()->create('id.pdf',20,'application/pdf'),'business_permit'=>UploadedFile::fake()->create('permit.pdf',20,'application/pdf')];
    }
    private function apply(): SellerApplication
    {
        $this->actingAs($this->buyer)->post(route('seller.apply.store'),$this->payload())->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();
        return $this->buyer->sellerApplications()->firstOrFail();
    }

    public function test_buyer_profile_photo_persists_and_replaces_previous_avatar(): void
    {
        $first = UploadedFile::fake()->create('first-avatar.jpg', 100, 'image/jpeg');
        $this->actingAs($this->buyer)->post(route('account.update'), [
            'name' => $this->buyer->name,
            'email' => $this->buyer->email,
            'phone' => $this->buyer->phone,
            'avatar' => $first,
        ])->assertSessionHasNoErrors();

        $firstPath = $this->buyer->fresh()->avatar;
        $this->assertStringStartsWith('avatars/', $firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $this->actingAs($this->buyer)->get(route('account.profile'))->assertSee(asset('storage/'.$firstPath), false);

        $second = UploadedFile::fake()->create('second-avatar.png', 100, 'image/png');
        $this->actingAs($this->buyer)->post(route('account.update'), [
            'name' => $this->buyer->name,
            'email' => $this->buyer->email,
            'phone' => $this->buyer->phone,
            'avatar' => $second,
        ])->assertSessionHasNoErrors();

        $secondPath = $this->buyer->fresh()->avatar;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertStringContainsString(asset('storage/'.$secondPath), $this->actingAs($this->buyer)->get(route('account.profile'))->getContent());
    }

    public function test_buyer_profile_photo_rejects_non_images(): void
    {
        $this->actingAs($this->buyer)->post(route('account.update'), [
            'name' => $this->buyer->name,
            'email' => $this->buyer->email,
            'phone' => $this->buyer->phone,
            'avatar' => UploadedFile::fake()->create('avatar.exe', 100, 'application/octet-stream'),
        ])->assertSessionHasErrors('avatar');
    }
    public function test_pending_applicant_keeps_buyer_access_but_cannot_use_any_seller_module(): void
    {
        $this->actingAs($this->buyer)->get(route('account.profile'))->assertOk()->assertSee('Become a Seller')->assertDontSee('{{')->assertDontSee('@csrf')->assertDontSee('@php')->assertDontSee('sellerAction()');
        $application=$this->apply();
        $this->assertSame('pending',$application->status);
        $this->assertTrue($this->buyer->fresh()->isBuyer());$this->assertFalse($this->buyer->fresh()->isSeller());
        foreach(['seller.dashboard','seller.products.index','seller.products.create','seller.orders.index','seller.inventory.index','seller.marketing.index','seller.store.edit'] as $route)$this->get(route($route))->assertForbidden();
        $this->get(route('cart.index'))->assertOk();$this->get(route('orders.index'))->assertOk();
        $this->get(route('account.profile'))->assertOk()->assertSee('View Application Status');
        $this->post(route('seller.apply.store'),$this->payload())->assertRedirect(route('seller.apply'));
        $this->assertDatabaseCount('seller_applications',1);$this->assertDatabaseCount('stores',1);
        $this->actingAs($this->admin)->get(route('admin.sellers.applications.index'))->assertOk()->assertSee('Buyer Shop');
        $this->get(route('admin.sellers.index'))->assertOk()->assertViewHas('sellers',fn($s)=>$s->total()===0);
        $this->assertSame(1,app(AdminSidebarCounts::class)->get()['applications']);
    }
    public function test_approval_is_idempotent_and_unlocks_same_account_without_losing_buyer_access(): void
    {
        $app=$this->apply();$userCount=User::count();
        $this->actingAs($this->admin)->get(route('admin.sellers.applications.show',$app))->assertOk()->assertSee('Admin Review');
        for($i=0;$i<2;$i++)$this->post(route('admin.sellers.applications.review',$app),['status'=>'approved'])->assertSessionHasNoErrors();
        $buyer=$this->buyer->fresh();$this->assertTrue($buyer->isBuyer());$this->assertTrue($buyer->isSeller());
        $this->assertSame($userCount,User::count());$this->assertDatabaseCount('stores',1);$this->assertDatabaseCount('seller_profiles',1);$this->assertDatabaseCount('seller_applications',1);
        $this->assertSame(2,$buyer->roles()->count());
        $this->assertSame(1,$buyer->notificationsData()->where('title','Your seller application has been approved.')->count());
        $this->assertNotNull($app->fresh()->reviewed_at);$this->assertSame($this->admin->id,$app->fresh()->reviewed_by);
        $this->get(route('admin.sellers.index'))->assertOk()->assertViewHas('sellers',fn($s)=>$s->total()===1);
        $this->get(route('admin.sellers.show',$buyer))->assertOk()->assertSee('Buyer + Seller');
        $this->get(route('admin.users.index',['tab'=>'buyers']))->assertOk()->assertSee($buyer->email);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Recent Seller Applications')->assertViewHas('stats',fn($s)=>$s['active_sellers']===1 && $s['seller_applications']===0);
        $this->actingAs($buyer)->get(route('seller.apply'))->assertRedirect(route('seller.dashboard'));
        foreach(['seller.dashboard','seller.products.index','seller.orders.index','seller.inventory.index','seller.marketing.index','seller.store.edit','account.profile','cart.index','orders.index'] as $route)$this->get(route($route))->assertOk();
        $this->get(route('landing'))->assertOk()->assertSee(route('seller.dashboard'),false);
        $this->post(route('seller.apply.store'),$this->payload())->assertStatus(422);
        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$other->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$other->id,'seller_profile_id'=>$profile->id,'name'=>'Other Shop','slug'=>'other-shop','status'=>'active']);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$this->category->id,'name'=>'Test Product','slug'=>'test-product','price'=>20,'stock'=>5,'is_active'=>true,'publication_status'=>'published','moderation_status'=>'clean']);
        $this->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1])->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('cart_items',['product_id'=>$product->id,'quantity'=>1]);
        $address=$buyer->addresses()->create(['full_name'=>$buyer->name,'phone'=>'09171234567','address_line'=>'1 Main Street','barangay'=>'Central','city'=>'Manila','province'=>'Metro Manila','postal_code'=>'1000','country'=>'PH','is_default'=>true]);
        $this->get(route('checkout'))->assertOk();
        $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'cod'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('orders',['user_id'=>$buyer->id]);
    }
    public function test_resubmission_requires_reason_reuses_application_and_preserves_review_history(): void
    {
        $app=$this->apply();
        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review',$app),['status'=>'needs_resubmission'])->assertSessionHasErrors('review_notes');
        $this->post(route('admin.sellers.applications.review',$app),['status'=>'needs_resubmission','review_notes'=>'Upload a clearer permit.'])->assertSessionHasNoErrors();
        $this->assertSame(0,app(AdminSidebarCounts::class)->get()['applications']);
        $this->actingAs($this->buyer)->get(route('seller.apply'))->assertOk()->assertSee('Upload a clearer permit.')->assertSee('Resubmit Application');
        $this->get(route('seller.dashboard'))->assertForbidden();$this->get(route('cart.index'))->assertOk();
        $this->post(route('seller.apply.store'),$this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('seller_applications',1);$this->assertSame('pending',$app->fresh()->status);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'seller_application.resubmitted','target_id'=>$app->id]);
        $this->actingAs($this->admin)->get(route('admin.sellers.applications.show',$app))->assertOk()->assertSee('Upload a clearer permit.');
    }
    public function test_rejection_keeps_buyer_access_and_existing_reapplication_policy(): void
    {
        $app=$this->apply();
        $this->actingAs($this->admin)->post(route('admin.sellers.applications.review',$app),['status'=>'rejected'])->assertSessionHasErrors('review_notes');
        $this->post(route('admin.sellers.applications.review',$app),['status'=>'rejected','review_notes'=>'Permit does not match.'])->assertSessionHasNoErrors();
        $this->assertFalse($this->buyer->fresh()->isSeller());$this->assertTrue($this->buyer->fresh()->isBuyer());
        $this->actingAs($this->buyer)->get(route('seller.apply'))->assertOk()->assertSee('Permit does not match.');
        $this->get(route('seller.dashboard'))->assertForbidden();$this->get(route('orders.index'))->assertOk();
        $this->post(route('seller.apply.store'),$this->payload())->assertSessionHasNoErrors();$this->assertDatabaseCount('seller_applications',1);
    }
    public function test_role_alone_or_profile_alone_never_unlocks_seller_routes(): void
    {
        $this->buyer->assignRole('seller');
        $this->actingAs($this->buyer)->get(route('seller.dashboard'))->assertForbidden();
        $profile=SellerProfile::create(['user_id'=>$this->buyer->id,'status'=>'pending']);
        Store::create(['user_id'=>$this->buyer->id,'seller_profile_id'=>$profile->id,'name'=>'Pending','slug'=>'pending','status'=>'active']);
        $this->get(route('seller.products.index'))->assertForbidden();
        $profile->update(['status'=>'approved']);$this->buyer->removeRole('seller');
        $this->get(route('seller.dashboard'))->assertForbidden();
    }
    public function test_buyers_cannot_review_other_applications_or_download_private_documents(): void
    {
        $app=$this->apply();$other=User::factory()->create(['is_active'=>true]);$other->assignRole('buyer');
        $this->actingAs($other)->get(route('seller.apply'))->assertOk()->assertDontSee('Buyer Shop');
        $this->get(route('admin.sellers.applications.show',$app))->assertForbidden();
        $this->post(route('admin.sellers.applications.review',$app),['status'=>'approved'])->assertForbidden();
        $this->get(route('admin.registrations.sellers.document',[$app,'valid-id']))->assertForbidden();
        $this->assertSame('pending',$app->fresh()->status);
        $this->actingAs($this->admin)->get(route('admin.registrations.sellers.document',[$app,'valid-id']))->assertOk();
    }
    public function test_legacy_buyer_role_migration_is_additive_and_repeatable(): void
    {
        $legacy=User::factory()->create(['is_active'=>true]);$legacy->assignRole('seller');
        SellerProfile::create(['user_id'=>$legacy->id,'status'=>'approved']);
        $migration=require database_path('migrations/2026_09_24_000001_preserve_buyer_role_for_approved_sellers.php');
        $migration->up();$migration->up();
        $this->assertTrue($legacy->fresh()->isBuyer());$this->assertTrue($legacy->fresh()->isSeller());
        $this->assertSame(2,$legacy->roles()->count());
    }
    public function test_guests_and_incomplete_accounts_cannot_apply(): void
    {
        $this->get(route('seller.apply'))->assertRedirect(route('login'));
        $this->post(route('seller.apply.store'),$this->payload())->assertRedirect(route('login'));
        $this->buyer->update(['registration_status'=>'incomplete','is_active'=>false]);
        $this->actingAs($this->buyer)->get(route('seller.apply'))->assertRedirect(route('profile.complete'));
        $this->assertDatabaseCount('seller_applications',0);
    }
}
