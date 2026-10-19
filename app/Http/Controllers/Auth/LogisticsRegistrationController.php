<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogisticsRegistrationRequest;
use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LogisticsRegistrationController extends Controller
{
    public function create() { return view('auth.register-logistics'); }

    public function store(LogisticsRegistrationRequest $request)
    {
        $data = $request->validated();
        $paths = [];
        $files = ['company_logo'=>'logos','representative_id'=>'representative-ids','business_registration'=>'business-registration','permit'=>'permits','tax_registration_document'=>'tax-registration'];
        try {
            foreach ($files as $field => $folder) {
                $file = $request->file($field);
                $paths[$field] = $file->storeAs('logistics-documents/'.$folder, Str::uuid().'.'.$file->extension(), 'local');
                if (! $paths[$field]) throw new \RuntimeException('Unable to store a required provider document.');
            }
            $user = DB::transaction(function () use ($data, $paths) {
                $user = User::create([
                    'name' => $data['contact_name'], 'email' => $data['representative_email'], 'phone' => $data['representative_phone'],
                    'password' => Hash::make($data['password']), 'registration_type' => 'logistics',
                    'registration_status' => 'pending', 'is_active' => false,
                ]);
                $user->assignRole('logistics');
                $list = fn (string $value) => collect(preg_split('/[,\r\n]+/', $value))->map(fn ($item) => trim($item))->filter()->values()->all();
                $provider = LogisticsProvider::create([
                    'owner_user_id'=>$user->id,'company_name'=>$data['company_name'],'trade_name'=>$data['trade_name'],'logo_path'=>$paths['company_logo'],
                    'contact_name'=>$data['contact_name'],'email'=>$data['company_email'],'phone'=>$data['company_phone'],
                    'address'=>collect([$data['street'],$data['barangay'],$data['city'],$data['province'],$data['postal_code']??null])->filter()->implode(', '),
                    'representative_position'=>$data['representative_position'],'representative_email'=>$data['representative_email'],'representative_phone'=>$data['representative_phone'],'representative_id_path'=>$paths['representative_id'],
                    'street'=>$data['street'],'barangay'=>$data['barangay'],'city'=>$data['city'],'province'=>$data['province'],'postal_code'=>$data['postal_code']??null,
                    'registration_number'=>$data['registration_number'],'business_registration_path'=>$paths['business_registration'],'permit_path'=>$paths['permit'],'tax_registration_info'=>$data['tax_registration_info'],'tax_registration_path'=>$paths['tax_registration_document'],
                    'areas_served'=>$list($data['areas_served']),'pickup_coverage_areas'=>$list($data['pickup_coverage_areas']),'delivery_coverage_areas'=>$list($data['delivery_coverage_areas']),'operating_hours'=>$data['operating_hours'],
                    'number_of_riders'=>$data['number_of_riders'],'number_of_vehicles'=>$data['number_of_vehicles'],'supported_vehicle_types'=>$data['supported_vehicle_types'],
                    'operations_contact'=>['name'=>$data['operations_contact_name'],'phone'=>$data['operations_contact_phone'],'email'=>$data['operations_contact_email']],
                    'customer_support_contact'=>['name'=>$data['support_contact_name'],'phone'=>$data['support_contact_phone'],'email'=>$data['support_contact_email']],
                    'emergency_contact'=>['name'=>$data['emergency_contact_name'],'phone'=>$data['emergency_contact_phone'],'email'=>$data['emergency_contact_email']??null],
                    'status'=>'pending','is_active'=>false,
                ]);
                $provider->users()->attach($user->id, ['membership_role'=>'owner','is_active'=>true]);
                return $user;
            });
            event(new Registered($user));
            Auth::login($user);
            try { app(\App\Services\RegistrationVerification::class)->send($user, 'logistics'); }
            catch (\Throwable $error) { report($error); return redirect()->route('registration.verification')->withErrors(['code'=>'We could not send your verification code. Please use Resend code to try again.']); }
        } catch (\Throwable $e) {
            foreach ($paths as $path) if ($path) Storage::disk('local')->delete($path);
            report($e);
            return back()->withInput($request->except(array_merge(['password','password_confirmation'], array_keys($files))))
                ->withErrors(['representative_email' => 'We could not submit your Logistics application. Please try again.']);
        }
        return redirect()->route('registration.verification')->with('status', 'Check your email for the verification code. Your Provider application remains pending platform approval.');
    }
}
