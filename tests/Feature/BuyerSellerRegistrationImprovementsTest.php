<?php

namespace Tests\Feature;

use App\Models\{Category, Role, SellerApplication, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\Support\MocksPhilippineLocations;
use Tests\TestCase;

class BuyerSellerRegistrationImprovementsTest extends TestCase
{
    use MocksPhilippineLocations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'admin'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'guard_name' => 'web']);
        }
        Category::create(['name' => 'Home & Living', 'slug' => 'home-living', 'is_active' => true]);
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
    }

    public function test_corporation_requires_sec_and_bir_documents_and_reuses_buyer_account(): void
    {
        $buyer = $this->buyer('corporation@example.test');
        $payload = $this->payload($buyer, [
            'seller_type' => 'Corporation',
            'tin' => '456-789-012-000',
            'store_name' => 'Corporate Hub',
        ]);
        unset($payload['sec_certificate'], $payload['bir_2303']);

        $this->actingAs($buyer)->post(route('seller.apply.store'), $payload)
            ->assertSessionHasErrors(['sec_certificate', 'bir_2303']);
        $this->assertDatabaseCount('seller_applications', 0);

        $payload = $this->payload($buyer, [
            'seller_type' => 'Corporation',
            'tin' => '456-789-012-000',
            'store_name' => 'Corporate Hub',
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
        ]);
        $this->actingAs($buyer)->post(route('seller.apply.store'), $payload)
            ->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();

        $application = SellerApplication::firstOrFail();
        $this->assertSame($buyer->id, $application->user_id);
        $this->assertSame('Corporation', $application->seller_type);
        $this->assertSame('456-789-012-000', $application->tin);
        Storage::disk('local')->assertExists($application->valid_id_path);
        Storage::disk('local')->assertExists($application->sec_certificate_path);
        Storage::disk('local')->assertExists($application->bir_2303_path);
        $this->assertSame(1, User::where('email', $buyer->email)->count());
    }

    public function test_exact_five_megabyte_document_is_accepted_and_oversize_is_rejected(): void
    {
        $buyer = $this->buyer('five-mb@example.test');
        $accepted = $this->payload($buyer, [
            'store_name' => 'Five MB Shop',
            'tin' => '789-012-345-000',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 5120, 'image/jpeg'),
        ]);
        $this->actingAs($buyer)->post(route('seller.apply.store'), $accepted)
            ->assertRedirect(route('seller.apply'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('seller_applications', 1);

        $second = $this->buyer('oversize@example.test');
        $rejected = $this->payload($second, [
            'store_name' => 'Oversize Shop',
            'tin' => '890-123-456-000',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 5121, 'image/jpeg'),
        ]);
        $this->actingAs($second)->post(route('seller.apply.store'), $rejected)
            ->assertSessionHasErrors('valid_id');
        $this->assertDatabaseCount('seller_applications', 1);
    }

    private function buyer(string $email): User
    {
        $buyer = User::factory()->create([
            'email' => $email,
            'phone' => '+639171234567',
            'is_active' => true,
            'registration_type' => 'buyer',
            'registration_status' => 'approved',
            'email_verified_at' => now(),
        ]);
        $buyer->assignRole('buyer');
        return $buyer;
    }

    private function payload(User $buyer, array $overrides = []): array
    {
        return array_replace([
            'store_name' => 'Individual Shop',
            'category_id' => Category::first()->id,
            'seller_type' => 'Individual',
            'tin' => '123-456-789-000',
            'store_description' => 'A verified Buyer shop.',
            'business_information' => null,
            'phone' => $buyer->phone,
            'address_line' => '1 Seller Street',
            'region' => 'Region IV-A (CALABARZON)',
            'region_code' => '0400000000',
            'province' => 'Laguna',
            'province_code' => '0403400000',
            'city' => 'Santa Cruz',
            'city_code' => '0403426000',
            'barangay' => 'Bubukal',
            'barangay_code' => '0403426003',
            'postal_code' => '4000',
            'valid_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'seller_terms' => '1',
        ], $overrides);
    }
}