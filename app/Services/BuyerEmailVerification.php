<?php
namespace App\Services;

use App\Mail\BuyerVerificationCode;
use App\Models\{AdminActivityLog,User};
use App\Support\BuyerIdentity;
use Illuminate\Support\Facades\{DB,Hash,Mail};
use Illuminate\Validation\ValidationException;

class BuyerEmailVerification
{
    public function assertEligible(User $user): void
    {
        abort_unless(BuyerIdentity::needsVerification($user)&&$user->isNormalBuyerRegistration()&&!$user->trashed()&&!$user->getAttribute('archived_at')
            &&!$user->registration_reviewed_by&&!$user->registration_reviewed_at&&!$user->registration_review_notes
            &&(AdminActivityLog::whereIn('target_type',['user',User::class])->where('target_id',$user->id)->where('action','user.status')->latest('id')->first()?->details['is_active']??true)!==false,403,'This account is unavailable. Please contact support.');
    }
    public function send(User $actor): void
    {
        DB::transaction(function()use($actor){
            $user=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();$this->assertEligible($user);
            $previous=DB::table('buyer_email_verifications')->where('user_id',$user->id)->lockForUpdate()->first();
            if($previous&&now()->lt(\Illuminate\Support\Carbon::parse($previous->sent_at)->addSeconds(60)))throw ValidationException::withMessages(['code'=>'Please wait 60 seconds between code requests.']);
            do{$code=(string)random_int(100000,999999);}while($previous&&Hash::check($code,$previous->code_hash));
            // Never write OTP email contents to a log transport or its fallback.
            if(in_array(config('mail.default'),['log','failover'],true)||(app()->isProduction()&&config('mail.default')==='array'))throw new \App\Exceptions\BuyerVerificationDeliveryException();
            DB::table('buyer_email_verifications')->updateOrInsert(['user_id'=>$user->id],['code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes(5),'sent_at'=>now(),'attempts'=>0]);
            try {Mail::to($user->email)->send(new BuyerVerificationCode($code));}
            catch(\Throwable $error){
                \Illuminate\Support\Facades\Log::warning('Buyer verification email delivery failed.', ['exception_class'=>$error::class]);
                throw new \App\Exceptions\BuyerVerificationDeliveryException();
            }
        });
    }
    public function verify(User $actor,string $code): User
    {
        // Return validation failures from the transaction so invalid-attempt counts are committed.
        $result=DB::transaction(function()use($actor,$code){
            $user=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();$this->assertEligible($user);
            $challenge=DB::table('buyer_email_verifications')->where('user_id',$user->id)->lockForUpdate()->first();
            if(!$challenge||now()->gte(\Illuminate\Support\Carbon::parse($challenge->expires_at)))return 'This verification code has expired. Request a new code.';
            if($challenge->attempts>=5)return 'Too many incorrect attempts. Request a new code.';
            if(!Hash::check($code,$challenge->code_hash)){
                DB::table('buyer_email_verifications')->where('user_id',$user->id)->increment('attempts');return 'Invalid verification code. Please try again.';
            }
            $user->update(['is_active'=>true,'registration_status'=>'approved','email_verified_at'=>now()]);
            DB::table('buyer_email_verifications')->where('user_id',$user->id)->delete();
            return $user;
        });
        if(is_string($result))throw ValidationException::withMessages(['code'=>$result]);
        return $result;
    }
}
