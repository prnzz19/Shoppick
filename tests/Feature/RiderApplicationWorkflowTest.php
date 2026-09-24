<?php

namespace Tests\Feature;

use App\Models\{Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach(['buyer','seller','logistics','rider'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
        $permission=Permission::create(['name'=>'Manage riders','slug'=>'manage_riders','guard_name'=>'web','group'=>'Logistics']);
        Role::where('slug','logistics')->first()->permissions()->attach($permission);
    }

    private function payload(string $email='rider.applicant@example.test'): array
    {
        return ['first_name'=>'Juan','middle_initial'=>'D','last_name'=>'Cruz','sex'=>'male','birthday'=>'1995-06-15','email'=>$email,'phone'=>'09171234567','address_line'=>'12 Main Street','region'=>'Region IV-A (CALABARZON)','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0434000000','city'=>'Cavinti','city_code'=>'0434060000','barangay'=>'Mahipon','barangay_code'=>'0434060150','postal_code'=>'4013','valid_id'=>UploadedFile::fake()->create('valid-id.pdf',20,'application/pdf'),'driver_license_number'=>'N01-23-456789','driver_license_classification'=>'Test classification','driver_license_expires_at'=>now()->addYear()->format('Y-m-d'),'driver_license_front'=>UploadedFile::fake()->create('front.jpg',20,'image/jpeg'),'driver_license_back'=>UploadedFile::fake()->create('back.jpg',20,'image/jpeg'),'preferred_vehicle_type'=>'motorcycle','password'=>'SecurePass123!','password_confirmation'=>'SecurePass123!','terms'=>'1'];
    }

    public function test_buyer_registration_preserves_rider_application_link(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Register as Buyer')->assertDontSee('Register as Seller')->assertSee('Apply as Rider')->assertSee(route('register.rider'),false)->assertDontSee('ðŸ',false);
    }

    public function test_public_rider_application_requires_logistics_approval_and_supports_resubmission(): void
    {
        Storage::fake('local');
        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $this->post(route('register.rider.submit'),$this->payload())->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $rider=User::where('email','rider.applicant@example.test')->firstOrFail();$profile=$rider->riderProfile;
        $this->assertTrue($rider->hasRole('rider'));$this->assertFalse($rider->is_active);$this->assertSame('pending',$rider->registration_status);$this->assertSame('inactive',$profile->account_status);$this->assertSame('unavailable',$profile->availability);$this->assertSame('pending_review',$profile->driver_license_status);
        Storage::disk('local')->assertExists($rider->valid_id_path);Storage::disk('local')->assertExists($profile->driver_license_front_path);Storage::disk('local')->assertExists($profile->driver_license_back_path);

        $this->post(route('login.submit'),['email'=>$rider->email,'password'=>'SecurePass123!'])->assertRedirect(route('rider.application.status'));
        $this->get(route('rider.application.status'))->assertOk()->assertSee('waiting for SHOPPICK Logistics approval');
        $this->get(route('rider.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($logistics)->get(route('logistics.rider-applications',['status'=>'pending','view'=>$profile->id]))->assertOk()->assertSee($rider->name)->assertSee('Rider Application')->assertSee('Approve');
        $this->post(route('logistics.riders.application.review',$profile),['decision'=>'needs_resubmission','reason'=>'Please upload a clearer license front image.'])->assertSessionHasNoErrors();
        $this->assertSame('needs_resubmission',$rider->fresh()->registration_status);
        $this->actingAs($rider->fresh())->post(route('rider.application.resubmit'),['driver_license_front'=>UploadedFile::fake()->create('clear-front.jpg',20,'image/jpeg')])->assertSessionHasNoErrors();
        $this->assertSame('pending',$rider->fresh()->registration_status);

        $this->actingAs($logistics)->post(route('logistics.riders.application.review',$profile),['decision'=>'approved'])->assertSessionHasNoErrors();
        $this->assertTrue($rider->fresh()->is_active);$this->assertSame('approved',$rider->fresh()->registration_status);$this->assertSame('active',$profile->fresh()->account_status);$this->assertSame('off_duty',$profile->fresh()->availability);$this->assertSame('verified',$profile->fresh()->driver_license_status);
        $this->post(route('logout'));$this->post(route('login.submit'),['email'=>$rider->email,'password'=>'SecurePass123!'])->assertRedirect(route('rider.dashboard'));
    }

    public function test_rider_documents_are_not_available_to_buyer_or_another_rider(): void
    {
        Storage::fake('local');$logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');$this->post(route('register.rider.submit'),$this->payload())->assertRedirect();$profile=User::where('email','rider.applicant@example.test')->firstOrFail()->riderProfile;
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');$this->actingAs($buyer)->get(route('logistics.riders.license.file',[$profile,'front']))->assertForbidden();
        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('rider');$this->actingAs($other)->get(route('logistics.riders.license.file',[$profile,'front']))->assertForbidden();
    }
}
