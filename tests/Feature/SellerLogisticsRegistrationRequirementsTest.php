<?php

namespace Tests\Feature;

use App\Models\{Category, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\Support\MocksPhilippineLocations;
use Tests\TestCase;

class SellerLogisticsRegistrationRequirementsTest extends TestCase
{
    use MocksPhilippineLocations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer','seller','logistics','rider','admin'] as $slug) {
            Role::firstOrCreate(['slug'=>$slug], ['name'=>ucfirst($slug),'guard_name'=>'web']);
        }
        Category::create(['name'=>'Home & Living','slug'=>'home-living','is_active'=>true]);
        Storage::fake('local');
        Mail::fake();
    }

    private function buyer(string $email = 'buyer@example.test'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'registration_type' => 'buyer',
            'registration_status' => 'approved',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('buyer');
        return $user;
    }

    public function test_verified_buyer_submits_private_pending_application_without_creating_a_second_user(): void
    {
        $buyer = $this->buyer('mariaclara.santos@example.com');
        $beforeUsers = User::count();

        $response = $this->actingAs($buyer)->post(route('seller.apply.store'), $this->sellerFixture());

        $response->assertRedirect(route('seller.apply'));
        $this->assertSame($beforeUsers, User::count());
        $this->assertTrue($buyer->fresh()->isBuyer());
        $this->assertFalse($buyer->fresh()->isSeller());
        $this->assertDatabaseHas('seller_applications', [
            'user_id' => $buyer->id,
            'status' => 'pending',
            'store_name' => 'Maria Clara Handicrafts',
        ]);
        $this->assertDatabaseHas('seller_profiles', ['user_id' => $buyer->id, 'status' => 'pending']);
        Storage::disk('local')->assertExists($buyer->sellerApplications()->first()->valid_id_path);
    }

    public function test_pending_buyer_seller_applicant_cannot_access_seller_operations(): void
    {
        $buyer = $this->buyer('pending-seller@example.com');
        $this->actingAs($buyer)->post(route('seller.apply.store'), $this->sellerFixture())->assertRedirect(route('seller.apply'));

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->assertTrue($buyer->fresh()->isBuyer());
        $this->assertFalse($buyer->fresh()->hasApprovedSellerAccess());
    }

    public function test_seller_documents_and_contact_validation_fail_closed(): void
    {
        $buyer = $this->buyer('seller-validation@example.com');
        $data = $this->sellerFixture();
        unset($data['valid_id'], $data['business_permit']);

        $this->actingAs($buyer)->post(route('seller.apply.store'), $data)
            ->assertSessionHasErrors(['valid_id']);

        $invalid = $this->sellerFixture();
        $invalid['phone'] = '12345';
        $this->actingAs($buyer)->post(route('seller.apply.store'), $invalid)
            ->assertSessionHasErrors('phone');
        $this->assertSame(1, User::count());
    }

    public function test_excel_logistics_account_1_creates_private_pending_provider_and_otp(): void
    {
        $response=$this->post(route('register.logistics.submit'), $this->logisticsFixture());
        $response->assertRedirect(route('registration.verification'));
        $user=User::where('email','operations@bilishatid.example.com')->firstOrFail();
        $this->assertSame('logistics',$user->registration_type);
        $this->assertSame('pending',$user->registration_status);
        $this->assertDatabaseHas('logistics_providers',['owner_user_id'=>$user->id,'company_name'=>'Bilis Hatid Express Inc.','sec_registration_number'=>'CS202012345','tin'=>'234-567-890-000','status'=>'pending','is_active'=>false]);
        $this->assertDatabaseHas('registration_verifications',['user_id'=>$user->id,'purpose'=>'registration']);
    }

    public function test_shared_tin_and_company_identity_are_rejected(): void
    {
        $this->post(route('register.logistics.submit'),$this->logisticsFixture())->assertRedirect(route('registration.verification'));
        $this->post(route('logout'));
        $duplicate=$this->logisticsFixture(); $duplicate['representative_email']='another@bilishatid.example.com'; $duplicate['company_name']='Another Cargo';
        $this->post(route('register.logistics.submit'),$duplicate)->assertSessionHasErrors('tin');
    }

    private function sellerFixture(): array
    {
        return [
            'store_name'=>'Maria Clara Handicrafts', 'seller_type'=>'Sole Proprietorship', 'tin'=>'123-456-789-000',
            'store_description'=>'Handmade home goods.', 'phone'=>'09189876543',
            'address'=>'Blk 12 Lot 8, Sampaguita St.', 'region'=>'Region IV-A (CALABARZON)', 'region_code'=>'0400000000', 'province'=>'Laguna', 'province_code'=>'0403400000', 'city'=>'Santa Cruz', 'city_code'=>'0403426000', 'barangay'=>'Bubukal', 'barangay_code'=>'0403426003', 'postal_code'=>'4000', 'seller_terms'=>'1',
            'category_id'=>Category::first()->id,
            'business_information'=>'Sole Proprietorship; TIN 123-456-789-000',
            'valid_id'=>UploadedFile::fake()->create('govt_id_front.jpg',10,'image/jpeg'),
            'business_permit'=>UploadedFile::fake()->create('permit.pdf',10,'application/pdf'),
            'dti_certificate'=>UploadedFile::fake()->create('dti_certificate.pdf',10,'application/pdf'),
            'bir_2303'=>UploadedFile::fake()->create('bir_2303.pdf',10,'application/pdf'),
        ];
    }

    private function logisticsFixture(): array
    {
        return ['company_name'=>'Bilis Hatid Express Inc.','trade_name'=>'Bilis Hatid','company_email'=>'company@bilishatid.example.com','company_phone'=>'09285551234','first_name'=>'Carlos Antonio','last_name'=>'Mendoza','representative_position'=>'Operations Manager','representative_email'=>'operations@bilishatid.example.com','representative_phone'=>'09285551234','street'=>'Unit 5B, 88 West Capitol Drive','region'=>'National Capital Region (NCR)','province'=>'Metro Manila','city'=>'Pasig City','barangay'=>'Kapitolyo','postal_code'=>'1603','sec_registration_number'=>'CS202012345','tin'=>'234-567-890-000','representative_id'=>UploadedFile::fake()->create('rep-id.jpg',10,'image/jpeg'),'sec_certificate'=>UploadedFile::fake()->create('sec.pdf',10,'application/pdf'),'bir_certificate'=>UploadedFile::fake()->create('bir.pdf',10,'application/pdf'),'mayors_permit'=>UploadedFile::fake()->create('mayor.pdf',10,'application/pdf'),'service_regions'=>['NCR','Region IV-A (CALABARZON)'],'password'=>'Hatid@2026','password_confirmation'=>'Hatid@2026','terms'=>'1'];
    }
}
