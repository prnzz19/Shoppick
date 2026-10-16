<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BuyerEmailVerification;
use App\Support\BuyerIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};

class BuyerVerificationController extends Controller
{
    public function show(Request $request)
    {
        if(!BuyerIdentity::needsVerification($request->user()))return redirect()->route('home');
        $challenge=DB::table('buyer_email_verifications')->where('user_id',$request->user()->id)->first();
        [$local,$domain]=explode('@',$request->user()->email,2);
        return view('auth.buyer-verification',['maskedEmail'=>mb_substr($local,0,1).'***@'.$domain,'expiresAt'=>$challenge?\Illuminate\Support\Carbon::parse($challenge->expires_at)->toIso8601String():null,'resendAt'=>$challenge?\Illuminate\Support\Carbon::parse($challenge->sent_at)->addSeconds(60)->toIso8601String():null]);
    }
    public function verify(Request $request,BuyerEmailVerification $service)
    {
        $data=$request->validate(['code'=>['required','string','regex:/^[0-9]{6}$/D']],['code.required'=>'Enter the 6-digit verification code.','code.regex'=>'Enter the 6-digit verification code.']);
        $user=$service->verify($request->user(),$data['code']);
        Auth::login($user,(bool)$request->session()->pull('buyer_verification_remember',false));
        $request->session()->regenerate();$request->session()->forget('url.intended');
        return redirect()->route('home')->with('success','Email verified. Your Buyer account is active.');
    }
    public function resend(Request $request,BuyerEmailVerification $service)
    {
        try{$service->send($request->user());}
        catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){throw $error;}
        catch(\Throwable $error){report($error);return back()->withErrors(['code'=>'We could not send your code. Please try again.']);}
        return redirect()->route('buyer.verification')->with('status','A new verification code was sent. Your previous code is no longer valid.');
    }
}
