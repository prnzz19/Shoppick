<?php
// Opt-in local MySQL smoke: real HTTP kernel and schema, rolled-back test records.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('RUN_LOCAL_BUYER_SMOKE')!=='1'||!$app->environment('local')||config('database.default')!=='mysql'){
    fwrite(STDERR,"Requires explicit opt-in, local environment and MySQL.\n");exit(1);
}
use App\Models\User;
use App\Mail\BuyerVerificationCode;
use Illuminate\Support\Facades\{DB,Mail,Storage};
use Illuminate\Http\{Request,UploadedFile};

echo json_encode(['google_client_id_configured'=>filled(config('services.google.client_id')),'google_client_secret_configured'=>filled(config('services.google.client_secret')),'google_redirect_configured'=>filled(config('services.google.redirect')),'mail_transport'=>config('mail.default'),'mail_sender_configured'=>filled(config('mail.from.address'))],JSON_THROW_ON_ERROR)."\n";
// Test-only settings apply to this CLI process, never the running local website.
$app->instance('env','testing');config(['session.driver'=>'array','cache.default'=>'array']);Mail::fake();Storage::fake('local');
$locations=new class {use Tests\Support\MocksPhilippineLocations;public function fake():void{$this->setUpMocksPhilippineLocations();}};$locations->fake();
$before=User::withTrashed()->count();$cookies=[];$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$send=function(string $method,string $path,array $data=[])use($kernel,&$cookies){
    $request=Request::create('http://127.0.0.1:8000'.$path,$method,$data,$cookies);
    if(isset($data['valid_id'])){$request->request->remove('valid_id');$request->files->set('valid_id',$data['valid_id']);}
    $response=$kernel->handle($request);foreach($response->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();$kernel->terminate($request,$response);return $response;
};
$assert=function(bool $ok,string $message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";};
$path=null;$success=false;DB::beginTransaction();
try {
    $assert($send('GET','/register/buyer')->getStatusCode()===200,'local MySQL registration page');
    do{$phone='0917'.random_int(1000000,9999999);}while(User::withTrashed()->whereIn('phone',[$phone,'+63'.substr($phone,1)])->exists());
    $email='local-smoke-'.bin2hex(random_bytes(6)).'@example.com';
    $data=['first_name'=>'Local','last_name'=>'Smoke Buyer','sex'=>'male','birthday'=>'1998-05-14','email'=>$email,'phone'=>$phone,'password'=>'Smoke@2026','password_confirmation'=>'Smoke@2026','terms'=>'1','registration_token'=>session('buyer_signup_token'),'address_line'=>'12 Test Street','region'=>'Region IV-A (CALABARZON)','province'=>'Laguna','city'=>'Santa Cruz','barangay'=>'Bubukal','postal_code'=>'4009','country'=>'PH','valid_id'=>UploadedFile::fake()->create('smoke-id.pdf',10,'application/pdf')];
    $response=$send('POST','/register/buyer',$data);$assert($response->getStatusCode()===302&&str_ends_with($response->headers->get('Location',''),'/verify-buyer-email'),'registration redirects to verification, not unavailable page');
    $user=User::where('email',$email)->firstOrFail();$path=$user->valid_id_path;$assert(!$user->is_active&&$user->registration_status==='unverified'&&$user->isBuyer(),'one unverified Buyer created');
    $assert($send('GET','/verify-buyer-email')->getStatusCode()===200,'verification page renders with MySQL challenge');
    $code=Mail::sent(BuyerVerificationCode::class)->last()->code;
    $response=$send('POST','/verify-buyer-email',['code'=>$code]);$assert($response->getStatusCode()===302&&$user->fresh()->is_active,'correct OTP activates Buyer');
    $assert($send('GET','/account')->getStatusCode()===200,'verified Buyer account access');$success=true;
}catch(Throwable $error){fwrite(STDERR,'FAIL local smoke: '.$error::class."\n");}
finally {DB::rollBack();if($path)Storage::disk('local')->delete($path);}
$assert(User::withTrashed()->count()===$before,'transaction rolled back; existing account count unchanged');
exit($success?0:1);
