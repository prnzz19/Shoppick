<?php

namespace Tests\Feature;

use App\Models\{Address, Category, Product, Role, SellerProfile, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerAddressManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_crud_is_csrf_ready_owned_and_available_to_checkout(): void
    {
        foreach (['buyer', 'seller'] as $slug) {
            Role::create(['name'=>ucfirst($slug), 'slug'=>$slug, 'guard_name'=>'web']);
        }

        $buyer = User::factory()->create(['is_active'=>true, 'phone'=>'+639171234567']);
        $buyer->assignRole('buyer');
        $otherBuyer = User::factory()->create(['is_active'=>true, 'phone'=>'+639181234567']);
        $otherBuyer->assignRole('buyer');
        $otherAddress = $this->address($otherBuyer, 'Other Buyer Address', true);
        $address = $this->address($buyer, 'Old Address', true);

        $page = $this->actingAs($buyer)->get(route('account.addresses'));
        $page->assertOk()->assertSee('name="_token"', false)->assertSee('id="address-method"', false);

        $updated = $this->payload('Alden', 'Brgy. Mahipon, Cavinti, Laguna') + ['is_default'=>1];
        $this->put(route('account.addresses.update', $address), $updated)
            ->assertRedirect()->assertSessionHas('success', 'Address updated successfully.');
        $this->assertDatabaseHas('addresses', ['id'=>$address->id, 'full_name'=>'Alden', 'address_line'=>'Brgy. Mahipon, Cavinti, Laguna', 'is_default'=>true]);

        $updated['full_name'] = 'Alden Updated';
        $this->put(route('account.addresses.update', $address), $updated)->assertRedirect()->assertSessionHasNoErrors();

        $createdPayload = $this->payload('Second Recipient', '22 Second Street');
        $this->post(route('account.addresses.store'), $createdPayload)
            ->assertRedirect()->assertSessionHas('success', 'Address added successfully.');
        $created = $buyer->addresses()->where('address_line', '22 Second Street')->firstOrFail();

        $this->post(route('account.addresses.default', $created))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($address->fresh()->is_default);
        $this->assertTrue($created->fresh()->is_default);
        $this->assertTrue($otherAddress->fresh()->is_default);

        $this->put(route('account.addresses.update', $otherAddress), $updated)->assertForbidden();
        $this->delete(route('account.addresses.destroy', $address))->assertRedirect()->assertSessionHas('success', 'Address deleted.');
        $this->assertDatabaseMissing('addresses', ['id'=>$address->id]);

        $seller = User::factory()->create(['is_active'=>true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id'=>$seller->id, 'status'=>'approved']);
        $store = Store::create(['user_id'=>$seller->id, 'seller_profile_id'=>$profile->id, 'name'=>'Address Test Shop', 'slug'=>'address-test-shop', 'status'=>'active']);
        $category = Category::create(['name'=>'Address Test', 'slug'=>'address-test']);
        $product = Product::create(['store_id'=>$store->id, 'category_id'=>$category->id, 'name'=>'Checkout Address Item', 'slug'=>'checkout-address-item', 'price'=>100, 'stock'=>5, 'is_active'=>true]);
        $this->post(route('cart.add'), ['product_id'=>$product->id, 'quantity'=>1])->assertRedirect(route('cart.index'));
        $this->get(route('checkout'))->assertOk()->assertSee('22 Second Street')->assertSee('MAHIPON');
        $this->post(route('checkout.store'), ['address_id'=>$created->id, 'payment_method'=>'cod'])->assertRedirect()->assertSessionMissing('error');
        $order = $buyer->orders()->latest('id')->firstOrFail();
        $this->assertSame('22 Second Street', data_get($order->shipping_address, 'address_line'));
        $this->assertSame('MAHIPON', data_get($order->shipping_address, 'barangay'));
        $this->assertSame('CAVINTI', data_get($order->shipping_address, 'city'));
        $this->assertSame('LAGUNA', data_get($order->shipping_address, 'province'));
    }

    private function address(User $user, string $line, bool $default): Address
    {
        return Address::create(['user_id'=>$user->id] + $this->payload($user->name, $line) + ['is_default'=>$default]);
    }

    private function payload(string $name, string $line): array
    {
        return ['full_name'=>$name, 'phone'=>'09170000003', 'label'=>'Home', 'province'=>'LAGUNA', 'city'=>'CAVINTI', 'barangay'=>'MAHIPON', 'postal_code'=>'4013', 'address_line'=>$line];
    }
}
