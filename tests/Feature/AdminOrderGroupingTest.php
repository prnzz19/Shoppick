<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_orders_group_seller_partitions_by_shop_with_search_and_filters(): void
    {
        [$admin,$buyer,$alpha,$beta] = $this->actors();

        $multi = $this->order($buyer,'SP-MULTI','pending');
        $alphaMulti = $this->sellerOrder($multi,$alpha,'SP-MULTI-S1','pending',100);
        $betaMulti = $this->sellerOrder($multi,$beta,'SP-MULTI-S2','pending',200);
        $alphaOnly = $this->sellerOrder($this->order($buyer,'SP-ALPHA','processing'),$alpha,'SP-ALPHA-S1','processing',300);
        $betaDelivered = $this->sellerOrder($this->order($buyer,'SP-BETA-DELIVERED','delivered'),$beta,'SP-BETA-D1','delivered',400);
        $betaCompleted = $this->sellerOrder($this->order($buyer,'SP-BETA-COMPLETED','completed'),$beta,'SP-BETA-C1','completed',500);

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));
        $response->assertOk()
            ->assertViewHas('orderGroups', fn ($groups) => $groups->pluck('store.name')->all() === ['Alpha Shop','Beta Shop'])
            ->assertSee('Seller: Alpha Seller')->assertSee('Seller: Beta Seller')
            ->assertSee('Orders: 2')->assertSee('Orders: 3')
            ->assertSee('Expand All')->assertSee('Collapse All')
            ->assertSee('aria-controls="shop-orders-'.$alpha->id.'"',false)
            ->assertSee('SP-MULTI',false)->assertSee('SP-MULTI-S1')->assertSee('SP-MULTI-S2');

        $this->get(route('admin.orders.index',['status'=>'pending']))->assertOk()
            ->assertSee('SP-MULTI-S1')->assertSee('SP-MULTI-S2')
            ->assertDontSee('SP-ALPHA-S1')->assertDontSee('SP-BETA-D1');

        $this->get(route('admin.orders.index',['q'=>'SP-BETA-DELIVERED']))->assertOk()
            ->assertSee('SP-BETA-D1')->assertDontSee('SP-ALPHA-S1')
            ->assertViewHas('orderGroups', fn ($groups) => $groups->count() === 1 && $groups->first()->store->is($beta));

        $this->get(route('admin.orders.index',['shop_id'=>$alpha->id]))->assertOk()
            ->assertSee('SP-MULTI-S1')->assertSee('SP-ALPHA-S1')->assertDontSee('SP-MULTI-S2')
            ->assertViewHas('orderGroups', fn ($groups) => $groups->count() === 1 && $groups->first()->store->is($alpha));

        $this->get(route('admin.orders.show',$multi))->assertOk()->assertSee('SP-MULTI');
        $this->assertSame($multi->id,$alphaMulti->order_id);
        $this->assertSame($multi->id,$betaMulti->order_id);
        $this->assertNotSame($alphaOnly->store_id,$betaDelivered->store_id);
        $this->assertSame($beta->id,$betaCompleted->store_id);
    }

    public function test_grouped_order_pagination_preserves_shop_status_and_search(): void
    {
        [$admin,$buyer,$alpha] = $this->actors();
        foreach(range(1,13) as $number) {
            $this->sellerOrder($this->order($buyer,'SP-PAGE-'.$number,'pending'),$alpha,'SP-PAGE-'.$number.'-S1','pending',100);
        }

        $this->actingAs($admin)->get(route('admin.orders.index',['shop_id'=>$alpha->id,'status'=>'pending','q'=>'SP-PAGE']))
            ->assertOk()
            ->assertViewHas('orders',fn($orders)=>$orders->total()===13&&$orders->hasPages())
            ->assertSee('shop_id='.$alpha->id,false)->assertSee('status=pending',false)->assertSee('q=SP-PAGE',false);
    }

    private function actors(): array
    {
        $permission=Permission::create(['name'=>'Manage Orders','slug'=>'manage_orders','group'=>'Orders','guard_name'=>'web']);
        $adminRole=Role::create(['name'=>'Admin','slug'=>'admin','guard_name'=>'web']);$adminRole->permissions()->attach($permission);
        Role::create(['name'=>'Seller','slug'=>'seller','guard_name'=>'web']);
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $buyer=User::factory()->create(['name'=>'Buyer One','email'=>'buyer-orders@test.local','is_active'=>true]);
        $sellerA=User::factory()->create(['name'=>'Alpha Seller','is_active'=>true]);$sellerA->assignRole('seller');
        $sellerB=User::factory()->create(['name'=>'Beta Seller','is_active'=>true]);$sellerB->assignRole('seller');
        $alpha=Store::create(['user_id'=>$sellerA->id,'name'=>'Alpha Shop','slug'=>'alpha-order-shop','status'=>'active']);
        $beta=Store::create(['user_id'=>$sellerB->id,'name'=>'Beta Shop','slug'=>'beta-order-shop','status'=>'active']);
        return [$admin,$buyer,$alpha,$beta];
    }

    private function order(User $buyer,string $number,string $status): Order
    {
        return Order::create(['user_id'=>$buyer->id,'order_number'=>$number,'status'=>$status,'payment_method'=>'cod','payment_status'=>'cod','subtotal'=>500,'shipping_fee'=>50,'total'=>550,'buyer_name'=>$buyer->name,'buyer_phone'=>'09170000000','shipping_address'=>['city'=>'Manila']]);
    }

    private function sellerOrder(Order $order,Store $store,string $number,string $status,float $subtotal): SellerOrder
    {
        return SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>$number,'status'=>$status,'subtotal'=>$subtotal,'shipping_fee'=>20,'seller_total'=>$subtotal]);
    }
}
