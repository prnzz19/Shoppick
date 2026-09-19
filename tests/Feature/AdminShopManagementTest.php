<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected User $seller;
    protected Store $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole=Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $this->seller=User::factory()->create(['is_active'=>true]);$this->seller->assignRole('seller');
        $this->shop=Store::create(['user_id'=>$this->seller->id,'name'=>'Panda Picks','slug'=>'panda-picks','status'=>'active']);
    }

    protected function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(['slug'=>$slug],['name'=>ucwords(str_replace('_',' ',$slug)),'group'=>'Shops','guard_name'=>'web']);
    }

    public function test_admin_shop_pages_require_view_permission(): void
    {
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.shops.index'))->assertForbidden();
        $this->post(route('admin.shops.status',$this->shop),['action'=>'approve'])->assertForbidden();
        $this->post(route('admin.shops.status',$this->shop),['action'=>'reject','reason'=>'Not authorized'])->assertForbidden();
        $this->adminRole->permissions()->attach($this->permission('view_shops'));
        $this->get(route('admin.shops.index'))->assertOk()->assertSee('Panda Picks')->assertSee('Shops');
        $this->get(route('admin.shops.show',$this->shop))->assertOk()->assertSee('Panda Picks')->assertDontSee('Suspend Shop');
    }

    public function test_authorized_admin_can_suspend_shop_and_seller_is_notified(): void
    {
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->adminRole->permissions()->attach([$this->permission('view_shops')->id,$this->permission('suspend_shops')->id]);
        $category=Category::create(['name'=>'Gifts','slug'=>'gifts','is_active'=>true]);
        $product=Product::create(['store_id'=>$this->shop->id,'category_id'=>$category->id,'name'=>'Panda Gift','slug'=>'panda-gift','price'=>100,'stock'=>5,'is_active'=>true,'publication_status'=>'published','moderation_status'=>'clean']);

        $this->actingAs($admin)->post(route('admin.shops.status',$this->shop),['action'=>'suspend','reason'=>'Policy violation'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('suspended',$this->shop->fresh()->status);
        $this->assertSame($admin->id,$this->shop->fresh()->status_changed_by);
        $this->assertFalse(Product::publiclyVisible()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('notifications_custom',['user_id'=>$this->seller->id,'title'=>'Your SHOPPICK store has been suspended.']);
        $this->assertTrue(AdminActivityLog::where('action','shop.suspend')->where('target_id',$this->shop->id)->exists());
    }

    public function test_admin_cannot_bypass_missing_permission(): void
    {
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->adminRole->permissions()->attach([$this->permission('view_shops')->id,$this->permission('reactivate_shops')->id]);
        $this->actingAs($admin)->post(route('admin.shops.status',$this->shop),['action'=>'suspend','reason'=>'No permission'])->assertForbidden();

        $this->adminRole->permissions()->attach($this->permission('suspend_shops'));
        $this->actingAs($admin)->post(route('admin.shops.status',$this->shop),['action'=>'suspend','reason'=>'Protected platform action'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.shops.status',$this->shop->fresh()),['action'=>'reactivate'])->assertSessionHasNoErrors();
        $this->assertSame('active',$this->shop->fresh()->status);
    }

    public function test_admin_approval_immediately_activates_normal_shop(): void
    {
        Role::create(['name'=>'Buyer','slug'=>'buyer','guard_name'=>'web']);
        $applicant=User::factory()->create(['is_active'=>true]);$applicant->assignRole('buyer');
        $profile=SellerProfile::create(['user_id'=>$applicant->id,'phone'=>'09171234567','address'=>'Manila','status'=>'pending']);
        $shop=Store::create(['user_id'=>$applicant->id,'seller_profile_id'=>$profile->id,'name'=>'HATDOGGGG','slug'=>'hatdogggg','status'=>'pending']);
        $application=SellerApplication::create(['user_id'=>$applicant->id,'store_name'=>'HATDOGGGG','store_description'=>'Pending store','phone'=>'09171234567','address'=>'Manila','status'=>'pending']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->adminRole->permissions()->attach([$this->permission('view_shops')->id,$this->permission('approve_shops')->id,$this->permission('reject_shops')->id]);

        $this->actingAs($admin)->get(route('admin.shops.index',['status'=>'pending']))->assertOk()->assertSee('Approve')->assertSee('Reject')->assertDontSee('Final Approve');
        $this->get(route('admin.shops.show',$shop))->assertOk()
            ->assertSee('Approve')->assertSee('Reject')
            ->assertSee('id="shoppick-confirm"',false)->assertSee('data-confirm-title="Approve this seller shop?"',false)
            ->assertDontSee('return confirm(',false);
        $this->post(route('admin.shops.status',$shop),['action'=>'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$application->fresh()->status);$this->assertSame('approved',$profile->fresh()->status);$this->assertSame('active',$shop->fresh()->status);$this->assertTrue($applicant->fresh()->isSeller());
        $this->assertDatabaseHas('notifications_custom',['user_id'=>$applicant->id,'title'=>'Your seller application has been approved.']);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'seller_shop.admin_approved','target_id'=>$shop->id]);

        $this->actingAs($admin)->get('/superadmin/shops/'.$shop->id)->assertRedirect('/admin/shops/'.$shop->id);
    }

    public function test_admin_rejection_is_final_for_normal_application(): void
    {
        Role::create(['name'=>'Buyer','slug'=>'buyer','guard_name'=>'web']);
        $applicant=User::factory()->create(['is_active'=>true]);$applicant->assignRole('buyer');
        $profile=SellerProfile::create(['user_id'=>$applicant->id,'status'=>'pending']);
        $shop=Store::create(['user_id'=>$applicant->id,'seller_profile_id'=>$profile->id,'name'=>'Pending Shop','slug'=>'pending-shop','status'=>'pending']);
        $application=SellerApplication::create(['user_id'=>$applicant->id,'store_name'=>'Pending Shop','store_description'=>'Needs review','phone'=>'09171234567','address'=>'Manila','status'=>'pending']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->adminRole->permissions()->attach([$this->permission('view_shops')->id,$this->permission('reject_shops')->id]);
        $this->actingAs($admin)->post(route('admin.shops.status',$shop),['action'=>'reject','reason'=>'Admin found incomplete information.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected',$application->fresh()->status);$this->assertSame('rejected',$shop->fresh()->status);$this->assertFalse($applicant->fresh()->isSeller());
        $this->assertDatabaseHas('notifications_custom',['user_id'=>$applicant->id,'title'=>'Your seller application was rejected.']);
        $this->actingAs($applicant)->get(route('seller.apply'))->assertOk()->assertSee('Admin found incomplete information.');
    }

    public function test_legacy_escalated_shop_is_decided_by_admin_without_another_escalation(): void
    {
        Role::firstOrCreate(['slug'=>'buyer'],['name'=>'Buyer','guard_name'=>'web']);
        $applicant=User::factory()->create(['is_active'=>true]);$applicant->assignRole('buyer');
        $profile=SellerProfile::create(['user_id'=>$applicant->id,'status'=>'pending']);
        $shop=Store::create(['user_id'=>$applicant->id,'seller_profile_id'=>$profile->id,'name'=>'Review Shop','slug'=>'review-shop','status'=>'pending']);
        $application=SellerApplication::create(['user_id'=>$applicant->id,'store_name'=>'Review Shop','phone'=>'09171234567','address'=>'Manila','status'=>'escalated','escalation_reason'=>'Legacy review queue']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->adminRole->permissions()->attach([
            $this->permission('view_shops')->id,
            $this->permission('approve_shops')->id,
            $this->permission('add_shop_notes')->id,
        ]);

        $this->actingAs($admin)->get(route('admin.shops.show',$shop))
            ->assertOk()->assertSee('Private Administrative Note')->assertSee('Admin Review')->assertDontSee('Escalate');
        $this->post(route('admin.shops.status',$shop),['action'=>'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$application->fresh()->status);$this->assertSame('active',$shop->fresh()->status);$this->assertTrue($applicant->fresh()->isSeller());
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'seller_shop.admin_approved','target_id'=>$shop->id]);
    }
}
