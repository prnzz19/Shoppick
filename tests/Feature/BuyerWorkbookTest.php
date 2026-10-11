<?php
namespace Tests\Feature;

use App\Mail\BuyerVerificationCode;
use App\Models\{AdminActivityLog,Role,User};
use App\Services\BuyerEmailVerification;
use App\Support\BuyerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,DB,Hash,Mail,Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MocksPhilippineLocations;
use Tests\TestCase;

class BuyerWorkbookTest extends TestCase
{
    use RefreshDatabase,MocksPhilippineLocations;
    protected function setUp(): void {parent::setUp();Storage::fake('local');Mail::fake();foreach(['buyer','seller','admin','logistics','rider'] as $slug)Role::create(['slug'=>$slug,'name'=>$slug,'guard_name'=>'web']);}
    private function payload(): array {return ['first_name'=>'Juan Miguel','last_name'=>'Dela Cruz','email'=>'juanmiguel.delacruz@example.com','phone'=>'09171234567','birthday'=>'1998-05-14','password'=>'Mamimili@2026','password_confirmation'=>'Mamimili@2026','terms'=>'1','sex'=>'male','address_line'=>'12 Test Street','region'=>'Region IV-A (CALABARZON)','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0403400000','city'=>'Santa Cruz','city_code'=>'0403426000','barangay'=>'Bubukal','barangay_code'=>'0403426003','postal_code'=>'4009','country'=>'PH','valid_id'=>UploadedFile::fake()->create('id.pdf',10,'application/pdf')];}
    private function register(array $override=[]): User {$this->post(route('register.submit'),array_replace($this->payload(),$override))->assertRedirect(route('buyer.verification'))->assertSessionHasNoErrors();return User::where('email',mb_strtolower(trim($override['email']??'juanmiguel.delacruz@example.com')))->firstOrFail();}
    private function code(): string {return Mail::sent(BuyerVerificationCode::class)->last()->code;}
    public function test_tc_buy_001_valid_registration_requires_otp_and_retains_private_id_address(): void {
        $user=$this->register();$this->assertFalse($user->is_active);$this->assertNull($user->email_verified_at);$this->assertSame('unverified',$user->registration_status);$this->assertSame(['buyer'],$user->roles()->pluck('slug')->all());$this->assertTrue(Hash::check('Mamimili@2026',$user->password));Storage::disk('local')->assertExists($user->valid_id_path);$this->assertSame(1,$user->addresses()->count());Mail::assertSent(BuyerVerificationCode::class,1);
        $this->get(route('buyer.verification'))->assertOk()->assertSee('j***@example.com')->assertDontSee($this->code());$this->get(route('cart.index'))->assertRedirect(route('buyer.verification'));
    }
    public function test_tc_buy_002_empty_input_and_003_each_mandatory_field(): void {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->post(route('register.submit'),[])->assertSessionHasErrors(['first_name','last_name','email','phone','birthday','password','password_confirmation','terms']);
        foreach(['first_name','last_name','email','phone','birthday','password','password_confirmation','terms','sex','address_line','postal_code','valid_id'] as $field){$data=$this->payload();unset($data[$field]);$this->post(route('register.submit'),$data)->assertSessionHasErrors($field);}
        $this->assertDatabaseCount('users',0);Mail::assertNothingSent();
    }
    public static function invalidInputs(): array {return [
        '006 first name one'=>['first_name','J'],'006 last name one'=>['last_name','D'],'006 first name 51'=>['first_name',str_repeat('A',51)],'006 last name 51'=>['last_name',str_repeat('A',51)],
        '004 numeric name'=>['first_name','Juan123'],'004 symbols'=>['last_name','Dela Cruz@#'],'009 missing at'=>['email','buyer.example.com'],'009 invalid email'=>['email','buyer@'],'009 no dot domain'=>['email','buyer@example'],'009 no local'=>['email','@example.com'],'009 inner whitespace'=>['email','buyer name@example.com'],
        '013 mobile letters'=>['phone','0917ABC4567'],'014 short mobile'=>['phone','0917123456'],'014 long mobile'=>['phone','091712345678'],'013 malformed'=>['phone','09!171234567'],'013 landline'=>['phone','02-8123-4567'],'013 wrong prefix'=>['phone','12345678901'],
        '017 short password'=>['password','Aa1!xxx'],'018 no uppercase'=>['password','mamimili@2026'],'018 no lowercase'=>['password','MAMIMILI@2026'],'018 no number'=>['password','Mamimili@xxxx'],'018 no special'=>['password','Mamimili2026'],'018 whitespace only special'=>['password','Mamimili2026 '],
        '019 mismatched confirmation'=>['password_confirmation','Different@2026'],'021 terms'=>['terms','0'],'postal code length'=>['postal_code','12345'],'malicious email array'=>['email',['bad']],'malicious phone array'=>['phone',['bad']],
    ];}
    #[DataProvider('invalidInputs')]
    public function test_invalid_partitions_and_boundaries(string $field,mixed $value): void {$data=$this->payload();$data[$field]=$value;if($field==='password')$data['password_confirmation']=$value;$this->post(route('register.submit'),$data)->assertSessionHasErrors($field);$this->assertDatabaseCount('users',0);}
    public function test_tc_buy_004_005_006_valid_name_boundaries_and_unicode(): void {
        foreach([['Al','Li'],[str_repeat('A',50),str_repeat('B',50)],['Niño Angelo',"Peñaflor-O'Hara"],['Ana Marie','Reyes Jr.']] as $i=>$names){Auth::logout();$user=$this->register(['first_name'=>$names[0],'last_name'=>$names[1],'email'=>"buyer.test0$i@example.com",'phone'=>'0917000000'.$i]);$this->assertSame($names[0],$user->first_name);$this->assertSame($names[1],$user->last_name);}
    }
    public function test_tc_buy_007_008_dob_17_18_today_and_future(): void {
        $this->post(route('register.submit'),array_replace($this->payload(),['birthday'=>today('Asia/Manila')->subYears(17)->format('Y-m-d')]))->assertSessionHasErrors('birthday');
        $this->post(route('register.submit'),array_replace($this->payload(),['birthday'=>today('Asia/Manila')->addDay()->format('Y-m-d')]))->assertSessionHasErrors('birthday');
        $this->register(['birthday'=>BuyerIdentity::birthdayCutoff()]);
        Auth::logout();$this->register(['birthday'=>today('Asia/Manila')->subYears(18)->subDay()->format('Y-m-d'),'email'=>'buyer.test08@example.com','phone'=>'09170000008']);
    }
    public function test_tc_buy_011_012_013_016_normalization_and_cross_format_duplicate_contacts(): void {
        $user=$this->register(['email'=>'  JUANMIGUEL.DELACRUZ@EXAMPLE.COM  ','phone'=>'+639171234567']);$this->assertSame('juanmiguel.delacruz@example.com',$user->email);$this->assertSame('+639171234567',$user->phone);Auth::logout();
        $this->post(route('register.submit'),$this->payload())->assertSessionHasErrors(['email','phone']);
        $this->post(route('register.submit'),array_replace($this->payload(),['email'=>'other@example.com']))->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('users',1);Mail::assertSent(BuyerVerificationCode::class,1);
    }
    public function test_tc_buy_017_eight_character_valid_password(): void {$this->register(['password'=>'Bili@202','password_confirmation'=>'Bili@202']);}
    public function test_tc_buy_022_backend_unique_keys_and_rapid_submissions(): void {
        $user=$this->register();$this->post(route('register.submit'),$this->payload())->assertRedirect();Auth::logout();$this->post(route('register.submit'),$this->payload())->assertSessionHasErrors(['email','phone']);$this->assertDatabaseCount('users',1);$this->assertDatabaseCount('buyer_email_verifications',1);Mail::assertSent(BuyerVerificationCode::class,1);
        try {DB::transaction(fn()=>User::factory()->create()->forceFill(['buyer_web_phone'=>$user->phone])->save());$this->fail('Unique phone key missing.');}catch(\Illuminate\Database\UniqueConstraintViolationException $error){$this->assertDatabaseCount('users',1);}
    }
    public function test_tc_buy_022_same_browser_registration_token_retries_are_idempotent(): void {
        $this->get(route('register'))->assertOk();$token=session('buyer_signup_token');
        $payload=array_replace($this->payload(),['registration_token'=>$token,'email'=>'anamarie.reyes@example.com','phone'=>'09173456789','first_name'=>'Ana Marie','last_name'=>'Reyes','birthday'=>'2001-11-02','password'=>'Suki#2026Ana','password_confirmation'=>'Suki#2026Ana']);
        foreach(range(1,3) as $i){Auth::logout();$this->post(route('register.submit'),$payload)->assertRedirect(route('buyer.verification'))->assertSessionHasNoErrors();}
        $this->assertDatabaseCount('users',1);$this->assertDatabaseCount('buyer_email_verifications',1);Mail::assertSent(BuyerVerificationCode::class,1);$this->assertCount(1,Storage::disk('local')->files('registration-documents'));
    }
    public function test_new_buyer_profile_email_changes_require_new_verification(): void {
        $user=$this->register();$this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertRedirect(route('home'));
        $this->post(route('account.update'),['name'=>$user->name,'phone'=>$user->phone,'email'=>'buyer.changed@example.com'])->assertRedirect(route('buyer.verification'));
        $this->assertFalse($user->fresh()->is_active);$this->assertNull($user->fresh()->email_verified_at);$this->get(route('checkout'))->assertRedirect(route('buyer.verification'));
        $this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertRedirect(route('home'));
    }
    public function test_tc_buy_023_024_correct_wrong_and_one_time_code(): void {
        $user=$this->register();$code=$this->code();$this->assertMatchesRegularExpression('/^[0-9]{6}$/',$code);$challenge=DB::table('buyer_email_verifications')->first();$this->assertNotSame($code,$challenge->code_hash);$this->assertTrue(Hash::check($code,$challenge->code_hash));
        $wrong=$code==='111111'?'222222':'111111';$this->post(route('buyer.verification.verify'),['code'=>$wrong])->assertSessionHasErrors('code');$this->assertFalse($user->fresh()->is_active);$this->assertSame(1,DB::table('buyer_email_verifications')->first()->attempts);
        $this->post(route('buyer.verification.verify'),['code'=>$code])->assertRedirect(route('home'));$this->assertTrue($user->fresh()->is_active);$this->assertNotNull($user->fresh()->email_verified_at);$this->assertDatabaseCount('buyer_email_verifications',0);$this->post(route('buyer.verification.verify'),['code'=>$code])->assertForbidden();
    }
    public function test_tc_buy_025_otp_format_validation(): void {$this->register();foreach(['12345','1234567','ABCDEF','1234AB','', ['123456']] as $code)$this->post(route('buyer.verification.verify'),['code'=>$code])->assertSessionHasErrors('code');$this->assertSame(0,DB::table('buyer_email_verifications')->first()->attempts);}
    public function test_tc_buy_026_expiry_at_exact_five_minutes(): void {$user=$this->register();$code=$this->code();$this->travel(5)->minutes();$this->post(route('buyer.verification.verify'),['code'=>$code])->assertSessionHasErrors('code');$this->assertFalse($user->fresh()->is_active);}
    public function test_tc_buy_027_resend_cooldown_invalidation_and_attempt_limit(): void {
        $user=$this->register();$old=$this->code();$this->post(route('buyer.verification.resend'))->assertSessionHasErrors('code');Mail::assertSent(BuyerVerificationCode::class,1);$this->travel(61)->seconds();$this->post(route('buyer.verification.resend'))->assertRedirect(route('buyer.verification'));$new=$this->code();$this->assertNotSame($old,$new);$this->post(route('buyer.verification.verify'),['code'=>$old])->assertSessionHasErrors('code');
        foreach(range(1,4) as $i)$this->post(route('buyer.verification.verify'),['code'=>$old])->assertSessionHasErrors('code');$this->post(route('buyer.verification.verify'),['code'=>$new])->assertSessionHasErrors('code');$this->assertFalse($user->fresh()->is_active);
        $this->travel(61)->seconds();$this->post(route('buyer.verification.resend'))->assertRedirect();$this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertRedirect(route('home'));
    }
    public function test_tc_buy_028_029_unverified_login_then_verified_session_and_logout(): void {
        $user=$this->register();$code=$this->code();$this->post(route('logout'));$this->post(route('login.submit'),['email'=>strtoupper($user->email),'password'=>'Mamimili@2026','remember'=>'1'])->assertRedirect(route('buyer.verification'));$this->get(route('checkout'))->assertRedirect(route('buyer.verification'));$this->postJson('/api/v1/login',['email'=>$user->email,'password'=>'Mamimili@2026'])->assertForbidden();
        $this->post(route('buyer.verification.verify'),['code'=>$code])->assertRedirect(route('home'));$this->get(route('account.profile'))->assertOk()->assertSee('Juan Miguel');$this->get(route('cart.index'))->assertOk();$token=session()->token();$this->post(route('logout'));$this->assertGuest();$this->assertNotSame($token,session()->token());
        $before=session()->getId();$this->post(route('login.submit'),['email'=>$user->email,'password'=>'Mamimili@2026'])->assertRedirect(route('home'));$this->assertNotSame($before,session()->getId());
    }
    public function test_tc_buy_030_cross_role_denial_and_administrator_restrictions(): void {
        $user=$this->register();$this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertRedirect(route('home'));foreach(['admin.dashboard','seller.dashboard','logistics.dashboard','rider.dashboard'] as $route)$this->get(route($route))->assertForbidden();
        $this->post(route('logout'));$user=$this->register(['email'=>'anamarie.reyes@example.com','phone'=>'09173456789','first_name'=>'Ana Marie','last_name'=>'Reyes','birthday'=>'2001-11-02','password'=>'Suki#2026Ana','password_confirmation'=>'Suki#2026Ana']);AdminActivityLog::record('user.status','user',$user->id,['is_active'=>false]);$this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertForbidden();$this->assertFalse($user->fresh()->is_active);
    }
    public function test_csrf_mail_failure_and_private_error_responses(): void {
        $user=$this->register();$original=DB::table('buyer_email_verifications')->first()->code_hash;
        $this->travel(61)->seconds();Mail::shouldReceive('to')->andThrow(new \RuntimeException('confidential SMTP internals'));
        $this->post(route('buyer.verification.resend'))->assertSessionHasErrors('code');
        $this->get(route('buyer.verification'))->assertDontSee('confidential SMTP')->assertDontSee($original);
        $this->assertSame($original,DB::table('buyer_email_verifications')->first()->code_hash);$this->assertFalse($user->fresh()->is_active);
        $this->app->instance('env','production');
        foreach(['buyer.verification.verify','buyer.verification.resend','logout'] as $route)$this->post(route($route))->assertStatus(419);
        Auth::logout();$this->post(route('register.submit'),$this->payload())->assertStatus(419);$this->post(route('login.submit'),['email'=>$user->email,'password'=>'Mamimili@2026'])->assertStatus(419);
        $this->app->instance('env','testing');
    }
    public function test_legacy_other_role_contacts_cannot_be_claimed_by_new_buyer(): void {
        $admin=User::factory()->create(['email'=>'JUANMIGUEL.DELACRUZ@EXAMPLE.COM','phone'=>'0917 123-4567']);$admin->assignRole('admin');
        $this->post(route('register.submit'),$this->payload())->assertSessionHasErrors(['email','phone']);$this->assertDatabaseCount('users',1);Mail::assertNothingSent();
    }
    public function test_login_attempt_limits_and_unexpected_errors_are_private(): void {
        $user=$this->register();$this->post(route('logout'));
        foreach(range(1,5) as $i)$this->post(route('login.submit'),['email'=>$user->email,'password'=>'Wrong@2026'])->assertSessionHasErrors('email');
        $this->post(route('login.submit'),['email'=>$user->email,'password'=>'Mamimili@2026'])->assertSessionHasErrors(['email'=>'Too many sign-in attempts. Please try again shortly.']);
        $this->travel(61)->seconds();$this->post(route('login.submit'),['email'=>$user->email,'password'=>'Mamimili@2026'])->assertRedirect(route('buyer.verification'));
        $this->mock(BuyerEmailVerification::class,fn($mock)=>$mock->shouldReceive('verify')->andThrow(new \RuntimeException('SQL confidential details')));
        $this->post(route('buyer.verification.verify'),['code'=>$this->code()])->assertStatus(503)->assertDontSee('SQL confidential')->assertSee('Unable to complete your request');
        $this->assertFalse($user->fresh()->is_active);
    }
    public function test_opt_in_browser_fixtures_use_only_in_memory_test_records(): void {
        if(!getenv('BUYER_WORKBOOK_FIXTURES'))$this->markTestSkipped('Optional isolated browser fixtures.');
        $dir=storage_path('app/buyer-workbook-fixtures');if(!is_dir($dir))mkdir($dir,0777,true);
        foreach(['register','login','terms','privacy'] as $route)file_put_contents($dir.'/'.$route.'.html',$this->get(url('/'.$route))->assertOk()->getContent());
        $this->post(route('register.submit'),array_replace($this->payload(),['first_name'=>'Juan123']))->assertSessionHasErrors('first_name');
        file_put_contents($dir.'/errors.html',$this->get(route('register'))->assertOk()->getContent());
        $this->register();file_put_contents($dir.'/verification.html',$this->get(route('buyer.verification'))->assertOk()->getContent());
        $this->travel(5)->minutes();file_put_contents($dir.'/expired.html',$this->get(route('buyer.verification'))->assertOk()->getContent());
        $this->assertFileExists($dir.'/register.html');
    }
}
