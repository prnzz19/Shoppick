<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerMarketingProductPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_page_keeps_the_full_product_list_out_of_the_voucher_form(): void
    {
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        [$seller, $store] = $this->seller('Picker Shop', 'picker-shop');
        $category = Category::create(['name' => 'Picker Category', 'slug' => 'picker-category']);
        $product = $this->product($store, $category, 'Aurora Smart Watch Series 5');

        $this->actingAs($seller)
            ->get(route('seller.marketing.index'))
            ->assertOk()
            ->assertSee('Manage Products')
            ->assertSee('voucherProductPicker')
            ->assertDontSee('Aurora Smart Watch Series 5');

        $this->actingAs($seller)
            ->getJson(route('seller.marketing.products'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.category', 'Picker Category');
    }

    public function test_product_picker_search_category_pagination_and_ownership_are_server_scoped(): void
    {
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        [$sellerA, $storeA] = $this->seller('First Picker Shop', 'first-picker-shop');
        [, $storeB] = $this->seller('Second Picker Shop', 'second-picker-shop');
        $categoryA = Category::create(['name' => 'First Category', 'slug' => 'first-category']);
        $categoryB = Category::create(['name' => 'Second Category', 'slug' => 'second-category']);

        for ($index = 1; $index <= 12; $index++) {
            $this->product($storeA, $categoryA, sprintf('Product %02d', $index));
        }
        $matchedProduct = $this->product($storeA, $categoryB, 'Wireless Earbuds', 'SKU-123');
        $this->product($storeB, $categoryB, 'Other Seller Product', 'SKU-OTHER');

        $this->actingAs($sellerA)
            ->getJson(route('seller.marketing.products', ['q' => '  sku-123  ']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchedProduct->id);

        $this->getJson(route('seller.marketing.products', ['category' => $categoryA->id]))
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('last_page', 2);

        $this->getJson(route('seller.marketing.products', ['category' => $categoryA->id, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2);

        $this->getJson(route('seller.marketing.products', ['category' => $categoryB->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchedProduct->id);

        $this->getJson(route('seller.marketing.products', ['q' => 'SKU-OTHER']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_existing_selected_product_voucher_prefills_its_product_picker(): void
    {
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        [$seller, $store] = $this->seller('Existing Voucher Shop', 'existing-voucher-shop');
        $category = Category::create(['name' => 'Existing Category', 'slug' => 'existing-category']);
        $selected = $this->product($store, $category, 'Wireless Earbuds');
        $this->product($store, $category, 'Unselected Product');
        $voucher = $store->vouchers()->create([
            'source_type' => 'shop',
            'code' => 'EARBUDSVOUCHER',
            'title' => 'Earbuds Voucher',
            'type' => 'fixed',
            'value' => 10,
            'min_purchase' => 0,
            'per_user_limit' => 1,
            'application_scope' => 'selected_products',
            'status' => 'active',
        ]);
        $voucher->products()->attach($selected->id);

        $this->actingAs($seller)
            ->get(route('seller.marketing.index'))
            ->assertOk()
            ->assertSee('Wireless Earbuds')
            ->assertDontSee('Unselected Product')
            ->assertSee('Manage Products');
    }

    public function test_selected_product_vouchers_validate_ownership_duplicates_and_entire_shop_clears_associations(): void
    {
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        [$sellerA, $storeA] = $this->seller('Voucher Owner Shop', 'voucher-owner-shop');
        [, $storeB] = $this->seller('Voucher Foreign Shop', 'voucher-foreign-shop');
        $category = Category::create(['name' => 'Voucher Category', 'slug' => 'voucher-category']);
        $ownedProduct = $this->product($storeA, $category, 'Owned Product');
        $foreignProduct = $this->product($storeB, $category, 'Foreign Product');

        $this->actingAs($sellerA)
            ->post(route('seller.marketing.vouchers.store'), $this->voucherInput([
                'application_scope' => 'selected_products',
                'product_ids' => [$ownedProduct->id, $ownedProduct->id],
            ]))
            ->assertSessionHasErrors('product_ids.1');
        $this->assertDatabaseMissing('vouchers', ['code' => 'PICKERTEST']);

        $this->post(route('seller.marketing.vouchers.store'), $this->voucherInput([
            'application_scope' => 'selected_products',
            'product_ids' => [$foreignProduct->id],
        ]))->assertSessionHasErrors('product_ids.0');
        $this->assertDatabaseMissing('vouchers', ['code' => 'PICKERTEST']);

        $this->post(route('seller.marketing.vouchers.store'), $this->voucherInput([
            'application_scope' => 'entire_shop',
            'product_ids' => [$foreignProduct->id],
        ]))->assertSessionHasNoErrors();
        $voucher = Voucher::where('code', 'PICKERTEST')->firstOrFail();
        $this->assertDatabaseCount('product_voucher', 0);

        $voucher->products()->attach($ownedProduct->id);
        $this->put(route('seller.marketing.vouchers.update', $voucher), $this->voucherInput([
            'application_scope' => 'entire_shop',
            'product_ids' => [$foreignProduct->id],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_voucher', 0);

        $this->put(route('seller.marketing.vouchers.update', $voucher), $this->voucherInput([
            'code' => 'PICKERTEST',
            'application_scope' => 'selected_products',
            'product_ids' => [$ownedProduct->id],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_voucher', [
            'voucher_id' => $voucher->id,
            'product_id' => $ownedProduct->id,
        ]);
    }

    private function seller(string $name, string $slug): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $user->id, 'status' => 'approved']);
        $store = Store::create([
            'user_id' => $user->id,
            'seller_profile_id' => $profile->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        return [$user, $store];
    }

    private function product(Store $store, Category $category, string $name, ?string $sku = null): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug() . '-' . str()->random(5),
            'sku' => $sku,
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
            'moderation_status' => 'clean',
        ]);
    }

    private function voucherInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Picker Test Voucher',
            'code' => 'PICKERTEST',
            'type' => 'fixed',
            'value' => 10,
            'min_purchase' => 0,
            'per_user_limit' => 1,
            'application_scope' => 'entire_shop',
            'status' => 'active',
        ], $overrides);
    }
}
