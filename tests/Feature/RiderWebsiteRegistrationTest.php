<?php
namespace Tests\Feature;

use App\Models\{NotificationModel,Permission,RiderProfile,Role,SystemSetting,User};
use App\Services\RiderWebsiteRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,DB,Event,Hash,Mail,Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MocksPhilippineLocations;
use Tests\TestCase;

class RiderWebsiteRegistrationTest extends TestCase
{
    use RefreshDatabase,MocksPhilippineLocations;
    protected function setUp(): void {
        parent::setUp();Storage::fake('local');Mail::fake();
        foreach(['buyer','seller','admin','logistics','rider'] as $slug)Role::create(['name'=>$slug,'slug'=>$slug,'guard_name'=>'web']);
        $permission=Permission::create(['name'=>'Manage riders','slug'=>'manage_riders','guard_name'=>'web']);Role::where('slug','logistics')->first()->permissions()->attach($permission);
    }
    private function payload(): array {
        return ['first_name'=>'Niño Angelo','last_name'=>"Peñaflor-O'Hara",'sex'=>'male','birthday'=>'1995-06-15','email'=>'rider.signup@example.com','phone'=>'09171234567','address_line'=>'12 Test Street','region'=>'Region IV-A (CALABARZON)','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0403400000','city'=>'Cavinti','city_code'=>'0403407000','barangay'=>'Mahipon','barangay_code'=>'0403407012','postal_code'=>'4013','valid_id'=>UploadedFile::fake()->create('id.pdf',10,'application/pdf'),'driver_license_number'=>'N01-23-456789','driver_license_classification'=>'Test classification','driver_license_expires_at'=>today()->addYear()->format('Y-m-d'),'driver_license_front'=>UploadedFile::fake()->create('front.pdf',10,'application/pdf'),'driver_license_back'=>UploadedFile::fake()->create('back.pdf',10,'application/pdf'),'preferred_vehicle_type'=>'motorcycle','password'=>'RiderPass123!','password_confirmation'=>'RiderPass123!','terms'=>'1'];
    }
    private function register(array $override=[]): User {
        $this->post(route('rider.register.submit'),array_replace($this->payload(),$override))->assertRedirect(route('rider.register.submitted'))->assertSessionHasNoErrors();
        return User::where('email',mb_strtolower(trim($override['email']??'rider.signup@example.com')))->firstOrFail();
    }
    private function manager(): User {$user=User::factory()->create(['is_active'=>true]);$user->assignRole('logistics');return $user;}
    public function test_guest_pages_and_legacy_alias_share_one_rider_registration(): void {
        foreach(['rider.register','register.rider'] as $route)$this->get(route($route))->assertOk()->assertSee('Become a SHOPPICK Rider')->assertSee('No Buyer account required')->assertSee(route('rider.register.submit'),false);
        $this->get(route('rider.login'))->assertOk()->assertSee('Register as Rider')->assertSee(route('rider.register'),false);
        $this->get(route('rider.register.submitted'))->assertRedirect(route('rider.register'));
        $this->post(route('register.rider.submit'),$this->payload())->assertRedirect(route('rider.register.submitted'));$this->assertDatabaseCount('users',1);
    }
    public function test_direct_registration_is_rider_only_pending_private_and_normalized(): void {
        $manager=$this->manager();$rider=$this->register(['email'=>'  RIDER.SIGNUP@EXAMPLE.COM  ','role'=>'admin','roles'=>['admin','logistics','buyer'],'is_active'=>true,'registration_status'=>'approved','user_id'=>$manager->id,'account_status'=>'active','availability'=>'available','hub_id'=>999]);
        $this->assertSame(['rider'],$rider->roles()->pluck('slug')->all());$this->assertFalse($rider->hasRole('buyer'));$this->assertSame('rider',$rider->registration_type);$this->assertSame('pending',$rider->registration_status);$this->assertFalse($rider->is_active);$this->assertTrue(Hash::check('RiderPass123!',$rider->password));$this->assertSame('+639171234567',$rider->phone);
        $profile=$rider->riderProfile;$this->assertSame('inactive',$profile->account_status);$this->assertSame('unavailable',$profile->availability);$this->assertSame('pending_review',$profile->driver_license_status);$this->assertNull($profile->hub_id);$this->assertNull($profile->vehicle_id);$this->assertSame(1,$rider->addresses()->count());
        foreach([$rider->valid_id_path,$profile->driver_license_front_path,$profile->driver_license_back_path] as $path)Storage::disk('local')->assertExists($path);
        $this->assertSame(1,$profile->licenseAudits()->count());$this->assertTrue(NotificationModel::where('user_id',$manager->id)->where('type','rider_application')->exists());$this->assertDatabaseCount('buyer_email_verifications',0);Mail::assertNothingSent();$this->assertGuest();
        $this->get(route('rider.register.submitted'))->assertOk()->assertSee('Registration submitted')->assertSee('Check Application Status')->assertSee('Back to Rider Login');
    }
    public function test_pending_login_approval_and_rider_only_dashboard_acceptance_flow(): void {
        $manager=$this->manager();$rider=$this->register();$before=session()->getId();
        $this->post(route('rider.login.submit'),['email'=>'  RIDER.SIGNUP@EXAMPLE.COM  ','password'=>'RiderPass123!','remember'=>'1'])->assertRedirect(route('rider.application.status'));$this->assertAuthenticatedAs($rider);$this->assertNotSame($before,session()->getId());$this->assertNull($rider->fresh()->remember_token);
        $this->get(route('rider.application.status'))->assertOk()->assertSee('waiting for SHOPPICK Logistics approval');$this->get(route('rider.dashboard'))->assertRedirect(route('rider.application.status'));
        $this->actingAs($manager)->get(route('logistics.rider-applications',['status'=>'pending']))->assertOk()->assertSee($rider->name);
        $this->post(route('logistics.riders.application.review',$rider->riderProfile),['decision'=>'approved'])->assertSessionHasNoErrors();$this->assertTrue($rider->fresh()->is_active);$this->assertSame('approved',$rider->fresh()->registration_status);$this->assertSame('active',$rider->riderProfile->fresh()->account_status);$this->assertSame(['rider'],$rider->roles()->pluck('slug')->all());
        $this->post(route('rider.logout'))->assertRedirect(route('rider.login'));$this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'RiderPass123!'])->assertRedirect(route('rider.dashboard'));
        foreach(['rider.dashboard','rider.jobs','rider.profile','rider.notifications'] as $route)$this->get(route($route))->assertOk();
    }
    public function test_rejected_and_resubmission_statuses_stay_restricted(): void {
        $rider=$this->register();$rider->update(['registration_status'=>'rejected','registration_review_notes'=>'Test rejection note']);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'RiderPass123!'])->assertRedirect(route('rider.application.status'));$this->get(route('rider.application.status'))->assertOk()->assertSee('not approved')->assertSee('Test rejection note');$this->get(route('rider.jobs'))->assertRedirect(route('rider.application.status'));
        $this->post(route('rider.application.resubmit'),[])->assertForbidden();$rider->update(['registration_status'=>'needs_resubmission']);$this->actingAs($rider->fresh())->get(route('rider.application.status'))->assertOk()->assertSee('Resubmit Application');
    }
    public function test_inactive_suspended_and_archived_riders_cannot_login_to_portal(): void {
        $rider=$this->register();$rider->update(['is_active'=>true,'registration_status'=>'approved']);$rider->riderProfile->update(['account_status'=>'suspended']);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'RiderPass123!'])->assertRedirect(route('rider.access-unavailable'));$this->assertGuest();$this->get(route('rider.access-unavailable'))->assertOk()->assertSee('Rider access unavailable');
        $this->actingAs($rider->fresh())->get(route('rider.dashboard'))->assertForbidden()->assertSee('Rider access unavailable');Auth::logout();$rider->riderProfile->update(['account_status'=>'active']);$rider->update(['is_active'=>false]);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'RiderPass123!'])->assertRedirect(route('rider.access-unavailable'));$this->assertGuest();
    }
    public function test_buyer_only_and_existing_accounts_cannot_gain_rider_privileges(): void {
        $buyer=User::factory()->create(['email'=>'RIDER.SIGNUP@EXAMPLE.COM','phone'=>'0917 123-4567','is_active'=>true]);$buyer->assignRole('buyer');
        $this->post(route('rider.register.submit'),$this->payload())->assertSessionHasErrors(['email','phone']);$this->assertSame(['buyer'],$buyer->roles()->pluck('slug')->all());$this->assertDatabaseCount('rider_profiles',0);
        $this->actingAs($buyer)->get(route('rider.dashboard'))->assertForbidden();Auth::logout();$this->post(route('rider.login.submit'),['email'=>$buyer->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
    }
    public function test_duplicate_email_mobile_license_and_rapid_posts_create_one_application(): void {
        $rider=$this->register();$this->post(route('rider.register.submit'),$this->payload())->assertSessionHasErrors(['email','phone','driver_license_number']);
        $this->post(route('rider.register.submit'),array_replace($this->payload(),['email'=>'other@example.com','phone'=>'+639171234567','driver_license_number'=>'OTHER-12345']))->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('users',1);$this->assertDatabaseCount('rider_profiles',1);$this->assertCount(3,Storage::disk('local')->allFiles('rider-documents'));
    }
    public static function invalidInput(): array {return [
        'numeric name'=>['first_name','Juan123'],'short name'=>['last_name','A'],'bad email'=>['email','buyer@'],'email array'=>['email',['bad']],'bad phone'=>['phone','09!171234567'],'phone length'=>['phone','0917123456'],
        'future DOB'=>['birthday','2099-01-01'],'weak password'=>['password','password'],'mismatch'=>['password_confirmation','Mismatch@2026'],'missing terms'=>['terms','0'],'expired license'=>['driver_license_expires_at','2000-01-01'],'invalid vehicle'=>['preferred_vehicle_type','spaceship'],'bad postal'=>['postal_code','12345'],
    ];}
    #[DataProvider('invalidInput')]
    public function test_invalid_input_never_creates_an_application(string $field,mixed $value): void {
        $data=$this->payload();$data[$field]=$value;if($field==='password')$data['password_confirmation']=$value;$this->post(route('rider.register.submit'),$data)->assertSessionHasErrors($field);$this->assertDatabaseCount('users',0);$this->assertDatabaseCount('rider_profiles',0);
    }
    public function test_empty_and_missing_documents_are_rejected(): void {
        $this->post(route('rider.register.submit'),[])->assertSessionHasErrors(['first_name','last_name','email','phone','birthday','password','password_confirmation','valid_id','driver_license_front','driver_license_back']);$data=$this->payload();unset($data['driver_license_front']);$this->post(route('rider.register.submit'),$data)->assertSessionHasErrors('driver_license_front');$this->assertDatabaseCount('users',0);
    }
    public function test_database_keys_and_failed_creation_do_not_leave_duplicate_or_orphan_records(): void {
        $rider=$this->register();$data=array_replace($this->payload(),['email'=>'race-other@example.com','phone'=>$rider->phone,'driver_license_number'=>'OTHER-98765']);
        try {app(RiderWebsiteRegistration::class)->register($data,collect($data)->only(['valid_id','driver_license_front','driver_license_back'])->all());$this->fail('Canonical phone must be unique.');}catch(\Illuminate\Database\UniqueConstraintViolationException $error){$this->assertDatabaseCount('users',1);$this->assertCount(3,Storage::disk('local')->allFiles('rider-documents'));}
        foreach([$rider->valid_id_path,$rider->riderProfile->driver_license_front_path,$rider->riderProfile->driver_license_back_path] as $path)Storage::disk('local')->assertExists($path);
    }
    public function test_failed_transaction_cleans_documents_and_shows_safe_inline_error(): void {
        Event::listen(Registered::class,fn()=>throw new \RuntimeException('private SQL exception details'));
        $this->from(route('rider.register'))->post(route('rider.register.submit'),$this->payload())->assertRedirect(route('rider.register'))->assertSessionHasErrors('email');$this->assertDatabaseCount('users',0);$this->assertDatabaseCount('rider_profiles',0);$this->assertCount(0,Storage::disk('local')->allFiles('rider-documents'));
        $this->get(route('rider.register'))->assertOk()->assertDontSee('private SQL')->assertSee('rider.signup@example.com');
    }
    public function test_existing_manually_created_rider_without_buyer_still_works(): void {
        $rider=User::factory()->create(['is_active'=>true,'registration_type'=>'rider','registration_status'=>'approved']);$rider->assignRole('rider');RiderProfile::create(['user_id'=>$rider->id,'account_status'=>'active','availability'=>'off_duty']);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password'])->assertRedirect(route('rider.dashboard'));$this->get(route('rider.dashboard'))->assertOk();$this->assertFalse($rider->hasRole('buyer'));
    }
    public function test_application_setting_and_csrf_protect_new_and_legacy_routes(): void {
        SystemSetting::create(['key'=>'logistics.rider_applications','value'=>'0','type'=>'boolean','group'=>'logistics']);
        $this->get(route('rider.register'))->assertForbidden();$this->post(route('rider.register.submit'),$this->payload())->assertForbidden();
        SystemSetting::where('key','logistics.rider_applications')->delete();$this->app->instance('env','production');$this->post(route('rider.register.submit'),$this->payload())->assertStatus(419);$this->post(route('register.rider.submit'),$this->payload())->assertStatus(419);$this->app->instance('env','testing');
    }
    public function test_opt_in_isolated_browser_fixtures(): void {
        if(!getenv('RIDER_REGISTRATION_FIXTURES'))$this->markTestSkipped('Optional isolated onboarding browser fixtures.');
        $dir=storage_path('app/rider-registration-fixtures');if(!is_dir($dir))mkdir($dir,0777,true);
        foreach(['register'=>'rider.register','login'=>'rider.login','unavailable'=>'rider.access-unavailable'] as $key=>$route)file_put_contents($dir.'/'.$key.'.html',$this->get(route($route))->assertOk()->getContent());
        $this->from(route('rider.register'))->post(route('rider.register.submit'),array_replace($this->payload(),['password_confirmation'=>'Mismatch@2026']))->assertSessionHasErrors('password_confirmation');file_put_contents($dir.'/errors.html',$this->get(route('rider.register'))->assertOk()->getContent());
        $rider=$this->register();file_put_contents($dir.'/submitted.html',$this->get(route('rider.register.submitted'))->assertOk()->getContent());$this->actingAs($rider);file_put_contents($dir.'/pending.html',$this->get(route('rider.application.status'))->assertOk()->getContent());$rider->update(['registration_status'=>'rejected']);file_put_contents($dir.'/rejected.html',$this->get(route('rider.application.status'))->assertOk()->getContent());$this->assertFileExists($dir.'/register.html');
    }
    public function test_new_registration_respects_maintenance_while_rider_login_remains_available(): void {
        SystemSetting::create(['key'=>'marketplace.status','value'=>'maintenance','type'=>'string','group'=>'marketplace']);
        foreach(['rider.register','register.rider'] as $route)$this->get(route($route))->assertStatus(503);
        $this->post(route('rider.register.submit'),$this->payload())->assertStatus(503);$this->get(route('rider.login'))->assertOk();$this->assertDatabaseCount('users',0);
    }
}
