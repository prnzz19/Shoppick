<?php
namespace Tests\Feature;

use App\Exceptions\BuyerVerificationDeliveryException;
use App\Mail\BuyerVerificationCode;
use App\Models\{Role,User};
use App\Services\BuyerEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Log,Mail};
use Tests\TestCase;

class GmailBuyerMailTest extends TestCase
{
    use RefreshDatabase;
    private function buyer(): User {
        Role::create(['name'=>'Buyer','slug'=>'buyer','guard_name'=>'web']);
        return User::factory()->create(['registration_type'=>'buyer','registration_status'=>'unverified','is_active'=>false,'email_verified_at'=>null])->assignRole('buyer');
    }
    public function test_otp_email_has_branding_code_expiry_and_privacy_instruction(): void {
        $mail=new BuyerVerificationCode('482913');$html=$mail->render();
        $this->assertSame('Verify your SHOPPICK email',$mail->subject);$this->assertStringContainsString('SHOPPICK',$html);$this->assertStringContainsString('482913',$html);$this->assertStringContainsString('5 minutes',$html);$this->assertStringContainsString('Do not share this code',$html);
        $text=view('emails.buyer-verification-text',['code'=>'482913'])->render();$this->assertStringContainsString('482913',$text);$this->assertStringContainsString('5 minutes',$text);
    }
    public function test_smtp_transport_requires_tls_and_has_bounded_timeout(): void {
        config(['mail.default'=>'smtp','mail.mailers.smtp.scheme'=>'smtp','mail.mailers.smtp.url'=>null,'mail.mailers.smtp.host'=>'smtp.gmail.com','mail.mailers.smtp.port'=>587,'mail.mailers.smtp.require_tls'=>true,'mail.mailers.smtp.timeout'=>30]);
        $transport=Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertTrue($transport->isTlsRequired());$this->assertTrue($transport->isAutoTls());$this->assertSame(30.0,$transport->getStream()->getTimeout());
        // Transport construction performs no SMTP connection and sends no mail.
    }
    public function test_transport_error_payload_is_not_reported_or_chained(): void {
        $user=$this->buyer();Log::spy();Mail::shouldReceive('to')->andThrow(new \RuntimeException('password=FAKE_SECRET_MARKER; raw SMTP diagnostic'));
        try {app(BuyerEmailVerification::class)->send($user);$this->fail('Delivery must fail.');}
        catch(BuyerVerificationDeliveryException $error){$this->assertNull($error->getPrevious());$this->assertStringNotContainsString('FAKE_SECRET_MARKER',$error->getMessage());report($error);}
        Log::shouldHaveReceived('warning')->once()->with('Buyer verification email delivery failed.',['exception_class'=>\RuntimeException::class]);Log::shouldNotHaveReceived('error');
        $this->assertDatabaseCount('buyer_email_verifications',0);$this->assertFalse($user->fresh()->is_active);
    }
    public function test_log_and_log_fallback_cannot_expose_otp_contents(): void {
        $user=$this->buyer();Mail::fake();
        foreach(['log','failover'] as $mailer){config(['mail.default'=>$mailer]);try {app(BuyerEmailVerification::class)->send($user);$this->fail('Log transport must be rejected.');}catch(BuyerVerificationDeliveryException $error){$this->assertNull($error->getPrevious());}}
        Mail::assertNothingSent();$this->assertDatabaseCount('buyer_email_verifications',0);
    }
}
