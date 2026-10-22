<?php

namespace Tests\Feature;

use App\Models\{Category, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\TestCase;

class SellerLogisticsRegistrationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer','seller','logistics','rider','admin'] as $slug) Role::firstOrCreate(['slug'=>$slug], ['name'=>ucfirst($slug),'guard_name'=>'web']);
        Category::create(['name'=>'Home & Living','slug'=>'home-living','is_active'=>true]);
        Storage::fake('local'); Mail::fake();
    }

    public function test_excel_seller_account_1_creates_private_pending_application_and_otp(): void
    {
        $response=$this->post(route('register.seller.submit'), $this->sellerFixture());
        $response->assertRedirect(route('registration.verification'));
        $user=User::where('email','mariaclara.santos@example.com')->firstOrFail();
        $this->assertSame('seller',$user->registration_type);
        $this->assertSame('pending',$user->registration_status);
        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('seller_applications',['user_id'=>$user->id,'seller_type'=>'Sole Proprietorship','tin'=>'123-456-789-000','store_name'=>'Maria Clara Handicrafts']);
        $this->assertDatabaseHas('registration_verifications',['user_id'=>$user->id,'purpose'=>'registration']);
        Storage::disk('local')->assertExists($user->sellerApplications()->first()->valid_id_path);
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

    public function test_invalid_identity_and_document_rules_fail_closed(): void
    {
        $data=$this->sellerFixture();
        $data['first_name']='Maria123'; $data['postal_code']='12345'; $data['password']='weakpassword'; $data['password_confirmation']='weakpassword';
        unset($data['valid_id']);
        $this->post(route('register.seller.submit'),$data)->assertSessionHasErrors(['first_name','postal_code','password','valid_id']);
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
        return ['first_name'=>'Maria Clara','last_name'=>'Santos','email'=>'mariaclara.santos@example.com','phone'=>'09189876543','store_name'=>'Maria Clara Handicrafts','category_id'=>Category::first()->id,'seller_type'=>'Sole Proprietorship','tin'=>'123-456-789-000','address_line'=>'Blk 12 Lot 8, Sampaguita St.','region'=>'National Capital Region (NCR)','province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Commonwealth','postal_code'=>'1121','store_description'=>'Handmade home goods.','valid_id'=>UploadedFile::fake()->create('govt_id_front.jpg',10,'image/jpeg'),'dti_certificate'=>UploadedFile::fake()->create('dti_certificate.pdf',10,'application/pdf'),'bir_2303'=>UploadedFile::fake()->create('bir_2303.pdf',10,'application/pdf'),'password'=>'Tindahan@2026','password_confirmation'=>'Tindahan@2026','seller_terms'=>'1'];
    }

    private function logisticsFixture(): array
    {
        return ['company_name'=>'Bilis Hatid Express Inc.','trade_name'=>'Bilis Hatid','company_email'=>'company@bilishatid.example.com','company_phone'=>'09285551234','first_name'=>'Carlos Antonio','last_name'=>'Mendoza','representative_position'=>'Operations Manager','representative_email'=>'operations@bilishatid.example.com','representative_phone'=>'09285551234','street'=>'Unit 5B, 88 West Capitol Drive','region'=>'National Capital Region (NCR)','province'=>'Metro Manila','city'=>'Pasig City','barangay'=>'Kapitolyo','postal_code'=>'1603','sec_registration_number'=>'CS202012345','tin'=>'234-567-890-000','representative_id'=>UploadedFile::fake()->create('rep-id.jpg',10,'image/jpeg'),'sec_certificate'=>UploadedFile::fake()->create('sec.pdf',10,'application/pdf'),'bir_certificate'=>UploadedFile::fake()->create('bir.pdf',10,'application/pdf'),'mayors_permit'=>UploadedFile::fake()->create('mayor.pdf',10,'application/pdf'),'service_regions'=>['NCR','Region IV-A (CALABARZON)'],'password'=>'Hatid@2026','password_confirmation'=>'Hatid@2026','terms'=>'1'];
    }
}
