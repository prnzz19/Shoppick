<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModerationDynamicSellerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_newly_registered_sellers_and_additional_images_appear_dynamically(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        config(['services.image_moderation.queued' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'Dynamic Products', 'slug' => 'dynamic-products', 'is_active' => true]);

        $sellerOne = $this->registerAndApproveSeller($admin, 'one');
        $this->actingAs($sellerOne)->post(route('seller.products.store'), $this->productData($category->id, 'Product A'))
            ->assertRedirect(route('seller.products.index'));
        $this->actingAs($sellerOne)->post(route('seller.products.store'), $this->productData($category->id, 'Product B'))
            ->assertRedirect(route('seller.products.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $sellerTwo = $this->registerAndApproveSeller($admin, 'two');
        $this->actingAs($sellerTwo)->post(route('seller.products.store'), $this->productData($category->id, 'Product C'))
            ->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseCount('moderation_scans', 3);
        $this->assertSame(3, $sellerOne->store->products()->whereHas('images.moderationScans')->count() + $sellerTwo->store->products()->whereHas('images.moderationScans')->count());

        $all = $this->actingAs($admin)->get(route('admin.moderation.index'))->assertOk();
        $all->assertSee('Dynamic Shop One')->assertSee('Dynamic Shop Two')
            ->assertSee('Product A')->assertSee('Product B')->assertSee('Product C')
            ->assertSee('2 moderated products')->assertSee('1 moderated product');
        $this->assertSame(1, substr_count($all->getContent(), 'id="shop-moderation-'.$sellerOne->store->id.'"'));
        $this->assertSame(1, substr_count($all->getContent(), 'id="shop-moderation-'.$sellerTwo->store->id.'"'));

        $this->actingAs($admin)->get(route('admin.moderation.index', ['status' => 'pending_scan']))->assertOk()
            ->assertSee('Dynamic Shop One')->assertSee('Dynamic Shop Two')->assertSee('Product C');
        $this->actingAs($admin)->get(route('admin.moderation.index', ['q' => 'Product B']))->assertOk()
            ->assertSee('Dynamic Shop One')->assertSee('Product B')
            ->assertDontSee('id="shop-moderation-'.$sellerTwo->store->id.'"', false);
        $this->actingAs($admin)->get(route('admin.moderation.index', ['shop' => $sellerTwo->store->id]))->assertOk()
            ->assertSee('Product C')->assertDontSee('Product A');

        $this->artisan('moderation:reconcile-product-images')->assertSuccessful();
        $this->assertDatabaseCount('moderation_scans', 3);
    }

    private function registerAndApproveSeller(User $admin, string $suffix): User
    {
        $label = ucfirst($suffix);
        $email = "dynamic.{$suffix}@seller.test";
        $category = Category::where('slug', 'dynamic-products')->firstOrFail();
        $this->post(route('register.submit'), [
            'first_name' => 'Dynamic', 'last_name' => "Seller {$label}", 'sex' => 'male', 'birthday' => '1995-01-01',
            'email' => $email, 'phone' => $suffix === 'one' ? '09171234561' : '09171234562',
            'password' => 'password', 'password_confirmation' => 'password', 'address_line' => '1 Seller Street',
            'barangay' => 'Central', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000',
            'country' => 'PH', 'store_name' => "Dynamic Shop {$label}", 'store_description' => 'A legitimate test marketplace shop.',
            'category_id' => $category->id, 'valid_id' => UploadedFile::fake()->create("seller-{$suffix}-id.jpg", 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create("seller-{$suffix}-permit.pdf", 100, 'application/pdf'),
            'same_address' => '1', 'seller_terms' => '1', 'terms'=>'1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $seller = User::where('email', $email)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.registrations.buyers.review',$seller),['status'=>'approved'])->assertSessionHasNoErrors();
        $this->actingAs($seller->fresh())->post(route('seller.apply.store'),[
            'store_name'=>"Dynamic Shop {$label}",'store_description'=>'A legitimate test marketplace shop.','category_id'=>$category->id,'phone'=>$seller->phone,'address'=>'1 Seller Street, Manila',
            'valid_id'=>UploadedFile::fake()->create('id.pdf',100,'application/pdf'),'business_permit'=>UploadedFile::fake()->create('permit.pdf',100,'application/pdf'),
        ])->assertSessionHasNoErrors();
        $application = SellerApplication::where('user_id', $seller->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.sellers.applications.review', $application), [
            'status' => 'approved', 'review_notes' => 'Application verified for dynamic moderation test.',
        ])->assertSessionHasNoErrors();

        return $seller->fresh('store');
    }

    private function productData(int $categoryId, string $name): array
    {
        return [
            'name' => $name, 'category_id' => $categoryId, 'description' => 'A legitimate seller product.',
            'price' => 499, 'stock' => 10, 'low_stock_threshold' => 2, 'publication_action' => 'publish',
            'images' => [UploadedFile::fake()->create(strtolower(str_replace(' ', '-', $name)).'.jpg', 20, 'image/jpeg')],
        ];
    }
}
