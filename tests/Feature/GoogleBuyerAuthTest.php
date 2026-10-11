<?php
namespace Tests\Feature;

use App\Models\{Role,SocialAccount,User};
use App\Mail\BuyerVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Http,Mail,Storage};
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\{ProviderInterface,User as GoogleUser,InvalidStateException};
use Mockery;
use Tests\TestCase;
use Tests\Support\MocksPhilippineLocations;

class GoogleBuyerAuthTest extends TestCase
{
    use RefreshDatabase,MocksPhilippineLocations;
    protected function setUp(): void {
        parent::setUp();Storage::fake('local');Mail::fake();
        foreach(['buyer','seller','admin','logistics','rider'] as $role)Role::create(['name'=>$role,'slug'=>$role,'guard_name'=>'web']);
        config(['services.google.client_id'=>'fake-client','services.google.client_secret'=>'fake-secret','services.google.redirect'=>'http://127.0.0.1:8000/auth/google/callback']);
    }
    private function provider(string $email='googlebuyer@gmail.com',string $id='google-subject',array $raw=[]): void {
        $user=(new GoogleUser)->setRaw(array_replace(['email_verified'=>true,'given_name'=>'Google','family_name'=>'Buyer'],$raw))->map(['id'=>$id,'email'=>$email,'name'=>'Google Buyer']);
        $provider=Mockery::mock(ProviderInterface::class);$provider->shouldReceive('user')->once()->andReturn($user);Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
    private function buyer(array $attributes=[]): User {
        $user=User::factory()->create($attributes+['email'=>'googlebuyer@gmail.com','phone'=>'+639171234567','is_active'=>true,'registration_type'=>'buyer','registration_status'=>'approved']);$user->assignRole('buyer');
        $user->addresses()->create(['full_name'=>$user->name,'phone'=>$user->phone,'address_line'=>'1 Test Street','barangay'=>'Bubukal','city'=>'Santa Cruz','province'=>'Laguna','postal_code'=>'4009','country'=>'PH','is_default'=>true]);return $user;
    }
    private function profile(): array {return ['first_name'=>'Google','last_name'=>'Buyer','sex'=>'female','birthday'=>'1998-05-14','phone'=>'09171234567','address_line'=>'1 Test Street','region'=>'Region IV-A (CALABARZON)','province'=>'Laguna','city'=>'Santa Cruz','barangay'=>'Bubukal','postal_code'=>'4009','country'=>'PH','terms'=>'1','valid_id'=>UploadedFile::fake()->create('id.pdf',10,'application/pdf')];}
    public function test_real_socialite_redirect_contains_state_and_only_identity_scopes(): void {
        $response=$this->get(route('auth.google.redirect',['role'=>'admin']))->assertRedirect();parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY),$query);
        $this->assertNotEmpty($query['state']);$this->assertSame(session('state'),$query['state']);$this->assertSame('http://127.0.0.1:8000/auth/google/callback',$query['redirect_uri']);$this->assertEqualsCanonicalizing(['openid','email','profile'],explode(' ',$query['scope']));
    }
    public function test_real_socialite_rejects_mismatched_state_without_http_call(): void {
        Http::preventStrayRequests();$this->withSession(['state'=>'expected'])->get(route('auth.google.callback',['state'=>'wrong','code'=>'fake']))->assertRedirect(route('login'))->assertSessionHas('authentication_error','Your Google sign-in session expired. Please try again.');$this->assertDatabaseCount('users',0);$this->assertGuest();
    }
    public function test_first_google_buyer_gets_only_buyer_role_and_regenerated_session(): void {
        $this->get(route('login'));$before=session()->getId();$this->provider();$this->get(route('auth.google.callback',['role'=>'admin','registration_type'=>'seller']))->assertRedirect(route('profile.complete'));
        $user=User::firstOrFail();$this->assertSame(['buyer'],$user->roles()->pluck('slug')->all());$this->assertSame('incomplete',$user->registration_status);$this->assertSame('Google',$user->first_name);$this->assertNotNull($user->email_verified_at);$this->assertNotSame($before,session()->getId());$this->get(route('checkout'))->assertRedirect(route('profile.complete'));Mail::assertNothingSent();
    }
    public function test_existing_email_is_trimmed_case_insensitive_and_never_duplicates(): void {
        $user=$this->buyer(['email'=>'GOOGLEBUYER@GMAIL.COM']);$this->provider('  googlebuyer@gmail.com  ');$this->get(route('auth.google.callback'))->assertRedirect(route('home'));$this->assertAuthenticatedAs($user);$this->assertDatabaseCount('users',1);$this->assertDatabaseCount('social_accounts',1);
    }
    public function test_linked_subject_requires_provider_verified_email_every_time(): void {
        $user=$this->buyer();$user->socialAccounts()->create(['provider'=>'google','provider_id'=>'google-subject']);$this->provider(raw:['email_verified'=>false]);$this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('authentication_error','Google did not provide a verified email address.');$this->assertGuest();
    }
    public function test_second_google_subject_cannot_claim_an_existing_link(): void {
        $user=$this->buyer();$user->socialAccounts()->create(['provider'=>'google','provider_id'=>'first-subject']);$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('login'));$this->assertDatabaseCount('social_accounts',1);$this->assertGuest();
    }
    public function test_external_domain_existing_account_needs_password_proof_before_linking(): void {
        $this->buyer(['email'=>'googlebuyer@example.com']);$this->provider('googlebuyer@example.com');$this->get(route('auth.google.callback'))->assertRedirect(route('login'));$this->assertDatabaseCount('social_accounts',0);$this->assertGuest();
    }
    public function test_deleted_or_disabled_accounts_are_not_recreated_or_linked(): void {
        $user=$this->buyer(['is_active'=>false]);$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('login'));$this->assertDatabaseCount('users',1);$this->assertDatabaseCount('social_accounts',0);$this->assertGuest();
    }
    public function test_deleted_account_is_not_recreated(): void {
        $user=$this->buyer();$user->delete();$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('login'));$this->assertSame(1,User::withTrashed()->count());$this->assertDatabaseCount('social_accounts',0);
    }
    public function test_existing_privileged_provider_link_does_not_bypass_public_buyer_rules(): void {
        $user=$this->buyer();$user->assignRole('admin');$user->socialAccounts()->create(['provider'=>'google','provider_id'=>'google-subject']);$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('login'));$this->assertGuest();
    }
    public function test_buyer_seller_roles_are_retained(): void {
        $user=$this->buyer();$user->assignRole('seller');$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('home'));$this->assertTrue($user->fresh()->isBuyer());$this->assertTrue($user->fresh()->isSeller());
    }
    public function test_password_registered_unverified_buyer_still_requires_otp_after_google(): void {
        $user=$this->buyer(['registration_status'=>'unverified','is_active'=>false,'email_verified_at'=>null]);$this->provider();$this->get(route('auth.google.callback'))->assertRedirect(route('buyer.verification'));$this->assertFalse($user->fresh()->is_active);$this->get(route('checkout'))->assertRedirect(route('buyer.verification'));
    }
    public function test_profile_requires_terms_adulthood_unique_phone_and_completes_without_otp(): void {
        $this->provider();$this->get(route('auth.google.callback'));$user=User::firstOrFail();$data=$this->profile();
        $this->post(route('profile.complete.update'),array_replace($data,['terms'=>'0']))->assertSessionHasErrors('terms');
        $this->post(route('profile.complete.update'),array_replace($data,['birthday'=>today('Asia/Manila')->subYears(17)->format('Y-m-d')]))->assertSessionHasErrors('birthday');
        $other=$this->buyer(['email'=>'other@gmail.com']);$this->post(route('profile.complete.update'),$data)->assertSessionHasErrors('phone');$other->update(['phone'=>'+639170000001']);
        $before=session()->getId();$this->post(route('profile.complete.update'),$this->profile())->assertRedirect(route('home'))->assertSessionHasNoErrors();$this->assertAuthenticatedAs($user);$this->assertNotSame($before,session()->getId());$this->assertSame('approved',$user->fresh()->registration_status);$this->assertTrue($user->fresh()->hasCompleteBuyerProfile());$this->get(route('cart.index'))->assertOk();Mail::assertNothingSent();
    }
    public function test_oauth_failure_and_missing_config_are_safe(): void {
        $provider=Mockery::mock(ProviderInterface::class);$provider->shouldReceive('user')->andThrow(new \RuntimeException('private token details'));Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('authentication_error',"We couldn't complete Google sign-in. Please try again.");$this->assertGuest();config(['services.google.client_id'=>null,'services.google.client_secret'=>null]);$this->get(route('login'))->assertOk()->assertDontSee('private token details')->assertSee('Google sign-in is currently unavailable.');$this->get(route('auth.google.redirect'))->assertRedirect(route('login'));
    }
    public function test_fresh_registration_form_resumes_pending_account_only_with_matching_credentials(): void {
        $data=$this->profile()+['email'=>'resume@gmail.com','password'=>'Buyer@2026','password_confirmation'=>'Buyer@2026'];$this->get(route('register'));$data['registration_token']=session('buyer_signup_token');$this->post(route('register.submit'),$data)->assertRedirect(route('buyer.verification'));Auth::logout();
        $this->get(route('register'));$data['registration_token']=session('buyer_signup_token');$this->post(route('register.submit'),$data)->assertRedirect(route('buyer.verification'))->assertSessionHas('status','This account is waiting for email verification. Enter your code or request a new one.');$this->assertDatabaseCount('users',1);Mail::assertSent(BuyerVerificationCode::class,1);
        Auth::logout();$this->post(route('register.submit'),array_replace($data,['password'=>'Wrong@2026','password_confirmation'=>'Wrong@2026']))->assertSessionHasErrors(['email','phone']);$this->assertGuest();
    }
    public function test_initial_email_failure_keeps_account_and_allows_verification_retry(): void {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('private SMTP configuration failure'));
        $data=$this->profile()+['email'=>'mail-failure@example.com','password'=>'Buyer@2026','password_confirmation'=>'Buyer@2026'];$this->get(route('register'));$data['registration_token']=session('buyer_signup_token');
        $this->post(route('register.submit'),$data)->assertRedirect(route('buyer.verification'))->assertSessionHasErrors('code');$user=User::firstOrFail();$this->assertFalse($user->is_active);$this->assertDatabaseCount('buyer_email_verifications',0);
        $this->get(route('buyer.verification'))->assertOk()->assertSee('Resend code')->assertDontSee('private SMTP');Auth::logout();$this->post(route('register.submit'),$data)->assertRedirect(route('buyer.verification'));$this->assertDatabaseCount('users',1);
    }
}
