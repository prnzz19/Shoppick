<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerAuthenticationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(string $email): User
    {
        Role::firstOrCreate(['slug' => 'buyer'], ['name' => 'Buyer', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->assignRole('buyer');
        return $user;
    }

    private function payload(int $categoryId, string $store = 'Test Store'): array
    {
        return [
            'store_name' => $store, 'store_description' => 'A local marketplace shop.', 'phone' => '09171234567',
            'address' => 'Manila', 'category_id' => $categoryId,
            'valid_id' => UploadedFile::fake()->create('buyer-id.jpg', 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_seller_application_normalizes_ph_mobile_and_rejects_invalid_format(): void
    {
        Storage::fake('local');
        $category = Category::create(['name' => 'Marketplace', 'slug' => 'marketplace']);
        $buyer = $this->buyer('seller-validation@example.test');
        $this->actingAs($buyer)->post(route('seller.apply.store'), array_merge($this->payload($category->id), ['phone' => '12345']))
            ->assertSessionHasErrors('phone');
        $this->actingAs($buyer)->post(route('seller.apply.store'), $this->payload($category->id))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_applications', ['user_id' => $buyer->id, 'phone' => '+639171234567']);
    }

    public function test_shop_name_is_unique_case_insensitively(): void
    {
        Storage::fake('local');
        $category = Category::create(['name' => 'Marketplace', 'slug' => 'marketplace']);
        $existing = $this->buyer('existing-seller@example.test');
        Store::create(['user_id' => $existing->id, 'name' => 'Panda Picks', 'slug' => 'panda-picks']);
        $applicant = $this->buyer('new-seller@example.test');
        $this->actingAs($applicant)->post(route('seller.apply.store'), $this->payload($category->id, 'panda picks'))
            ->assertSessionHasErrors('store_name');
    }

    public function test_shared_login_entry_point_is_available(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome Back!');
    }
}
