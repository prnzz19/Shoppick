<?php
namespace App\Services;

use App\Mail\BuyerVerificationCode;
use App\Models\{AdminActivityLog,User};
use App\Support\BuyerIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BuyerEmailVerification
{
    public function __construct(private RegistrationVerification $shared) {}
    public function assertEligible(User $user): void
    {
        abort_unless(BuyerIdentity::needsVerification($user)&&$user->isNormalBuyerRegistration()&&!$user->trashed()&&!$user->getAttribute('archived_at')
            &&!$user->registration_reviewed_by&&!$user->registration_reviewed_at&&!$user->registration_review_notes
            &&(AdminActivityLog::whereIn('target_type',['user',User::class])->where('target_id',$user->id)->where('action','user.status')->latest('id')->first()?->details['is_active']??true)!==false,403,'This account is unavailable. Please contact support.');
    }
    public function send(User $actor): void
    {
        $user=User::whereKey($actor->id)->firstOrFail();$this->assertEligible($user);
        try { $this->shared->sendUsing($user,'buyer','buyer_email_verifications',BuyerVerificationCode::class); }
        catch (ValidationException $error) { throw $error; }
        catch (\Throwable $error) {
            Log::warning('Buyer verification email delivery failed.', ['exception_class'=>$error::class]);
            throw new \App\Exceptions\BuyerVerificationDeliveryException();
        }
    }
    public function verify(User $actor,string $code): User
    {
        $user=User::whereKey($actor->id)->firstOrFail();$this->assertEligible($user);
        return $this->shared->verifyUsing($user,$code,'buyer','buyer_email_verifications',false,true);
    }
}
