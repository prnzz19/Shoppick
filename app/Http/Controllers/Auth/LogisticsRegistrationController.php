<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogisticsRegistrationRequest;
use App\Models\{LogisticsProvider, User};
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Schema, Storage};
use Illuminate\Support\Str;

class LogisticsRegistrationController extends Controller
{
    public function create() { return view('auth.register-logistics'); }

    public function resubmitForm(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->registration_type === 'logistics' && in_array($user->registration_status, ['rejected', 'needs_resubmission', 'pending'], true), 403);
        $provider = $user->ownedLogisticsProvider;
        abort_unless($provider && in_array($provider->status, ['rejected', 'needs_resubmission', 'pending'], true), 404);
        return view('auth.logistics-resubmit', compact('provider'));
    }

    public function resubmit(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->registration_type === 'logistics' && in_array($user->registration_status, ['rejected', 'needs_resubmission', 'pending'], true), 403);
        $provider = $user->ownedLogisticsProvider;
        abort_unless($provider && in_array($provider->status, ['rejected', 'needs_resubmission', 'pending'], true), 404);
        abort_unless(Schema::hasColumn('logistics_providers', 'sec_registration_number') && Schema::hasColumn('logistics_providers', 'tin'), 503, 'Logistics registration is temporarily unavailable while the registration schema is prepared.');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'], 'trade_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['required', 'email', 'max:255'], 'company_phone' => ['nullable', 'string', 'max:30'],
            'sec_registration_number' => ['required', 'string', 'max:11'], 'tin' => ['required', 'string', 'max:15'],
            'representative_position' => ['required', 'string', 'max:255'], 'representative_phone' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'], 'region' => ['required', 'string', 'max:255'], 'region_code' => ['nullable', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:255'], 'province_code' => ['nullable', 'string', 'max:20'], 'city' => ['required', 'string', 'max:255'], 'city_code' => ['nullable', 'string', 'max:20'],
            'barangay' => ['required', 'string', 'max:255'], 'barangay_code' => ['nullable', 'string', 'max:20'], 'postal_code' => ['required', 'string', 'max:4'],
            'service_regions' => ['required', 'array', 'min:1'], 'service_regions.*' => ['string', 'max:100'],
            'representative_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'sec_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bir_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'mayors_permit' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $paths = [];
        try {
            foreach ([
                'representative_id' => ['representative_id_path', 'representative-ids'],
                'sec_certificate' => ['business_registration_path', 'sec'],
                'bir_certificate' => ['tax_registration_path', 'bir'],
                'mayors_permit' => ['permit_path', 'mayors-permit'],
            ] as $field => [$column, $folder]) {
                if ($request->hasFile($field)) { $paths[$column] = $request->file($field)->store('logistics-documents/'.$folder, 'local'); }
            }
            $areas = array_values(array_unique($data['service_regions']));
            DB::transaction(function () use ($provider, $user, $data, $areas, $paths) {
                $provider->update(array_merge([
                    'company_name' => $data['company_name'], 'trade_name' => $data['trade_name'] ?? null, 'email' => $data['company_email'], 'phone' => $data['company_phone'] ?? null,
                    'address' => implode(', ', array_filter([$data['street'], $data['barangay'], $data['city'], $data['province'], $data['region'], $data['postal_code']])),
                    'representative_position' => $data['representative_position'], 'representative_email' => $user->email, 'representative_phone' => $data['representative_phone'],
                    'registration_number' => $data['sec_registration_number'], 'sec_registration_number' => $data['sec_registration_number'], 'tin' => $data['tin'],
                    'region' => $data['region'], 'region_code' => $data['region_code'] ?? null, 'province' => $data['province'], 'province_code' => $data['province_code'] ?? null,
                    'city' => $data['city'], 'city_code' => $data['city_code'] ?? null, 'barangay' => $data['barangay'], 'barangay_code' => $data['barangay_code'] ?? null,
                    'postal_code' => $data['postal_code'], 'service_regions' => $areas, 'areas_served' => $areas, 'pickup_coverage_areas' => $areas, 'delivery_coverage_areas' => $areas,
                    'status' => 'pending', 'is_active' => false, 'review_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                ], $paths));
                $user->update(['registration_status' => 'pending', 'is_active' => false, 'registration_review_notes' => null, 'registration_reviewed_by' => null, 'registration_reviewed_at' => null]);
            });
        } catch (\Throwable $error) { foreach ($paths as $path) Storage::disk('local')->delete($path); report($error); return back()->withInput()->withErrors(['company_name' => 'We could not resubmit the Logistics application. Please check the details and try again.']); }
        return redirect()->route('logistics.application.resubmit')->with('status', 'Your Logistics Company application was resubmitted for Admin review.');
    }
    public function store(LogisticsRegistrationRequest $request)
    {
        $data = $request->validated();
        abort_unless(Schema::hasColumn('logistics_providers','sec_registration_number') && Schema::hasColumn('logistics_providers','tin'), 503, 'Logistics registration is temporarily unavailable while the registration schema is prepared.');
        $paths = [];
        $fileFields = [
            'company_logo'=>'logos', 'representative_id'=>'representative-ids',
            'sec_certificate'=>'sec', 'business_registration'=>'sec',
            'bir_certificate'=>'bir', 'tax_registration_document'=>'bir',
            'mayors_permit'=>'mayors-permit', 'permit'=>'mayors-permit',
        ];
        try {
            foreach ($fileFields as $field => $folder) {
                if (isset($paths[$folder]) || ! $request->hasFile($field)) continue;
                $file = $request->file($field);
                $paths[$folder] = $file->storeAs('logistics-documents/'.$folder, Str::uuid().'.'.$file->extension(), 'local');
                if (! $paths[$folder]) throw new \RuntimeException('Unable to store a required provider document.');
            }
            $user = DB::transaction(function () use ($data, $paths) {
                $first = $data['first_name']; $last = $data['last_name'];
                $user = User::create([
                    'name'=>trim($first.' '.$last),'first_name'=>$first,'last_name'=>$last,
                    'email'=>$data['representative_email'],'phone'=>$data['representative_phone'],
                    'password'=>Hash::make($data['password']),'registration_type'=>'logistics',
                    'registration_status'=>'pending','is_active'=>false,'email_verified_at'=>null,
                ]);
                $user->assignRole('logistics');
                $areas = array_values(array_unique($data['service_regions'] ?? []));
                $provider = LogisticsProvider::create([
                    'owner_user_id'=>$user->id,'company_name'=>$data['company_name'],'trade_name'=>$data['trade_name'] ?? null,
                    'logo_path'=>$paths['logos'] ?? null,'contact_name'=>$data['contact_name'],'email'=>$data['company_email'],'phone'=>$data['company_phone'] ?? null,
                    'address'=>implode(', ',array_filter([$data['street'],$data['barangay'],$data['city'],$data['province'],$data['region'],$data['postal_code']])),
                    'region'=>$data['region'],'region_code'=>$data['region_code'] ?? null,'province'=>$data['province'],'province_code'=>$data['province_code'] ?? null,
                    'city'=>$data['city'],'city_code'=>$data['city_code'] ?? null,'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code'] ?? null,'postal_code'=>$data['postal_code'],
                    'representative_position'=>$data['representative_position'],'representative_email'=>$data['representative_email'],'representative_phone'=>$data['representative_phone'],
                    'representative_id_path'=>$paths['representative-ids'] ?? null,'registration_number'=>$data['sec_registration_number'],'sec_registration_number'=>$data['sec_registration_number'],'tin'=>$data['tin'],
                    'business_registration_path'=>$paths['sec'] ?? null,'permit_path'=>$paths['mayors-permit'] ?? null,'tax_registration_info'=>$data['tax_registration_info'] ?? null,'tax_registration_path'=>$paths['bir'] ?? null,
                    'service_regions'=>$areas,'areas_served'=>$areas,'pickup_coverage_areas'=>$areas,'delivery_coverage_areas'=>$areas,
                    'operating_hours'=>$data['operating_hours'] ?? null,'number_of_riders'=>$data['number_of_riders'] ?? 0,'number_of_vehicles'=>$data['number_of_vehicles'] ?? 0,
                    'supported_vehicle_types'=>$data['supported_vehicle_types'] ?? [],
                    'operations_contact'=>array_filter(['name'=>$data['operations_contact_name'] ?? null,'phone'=>$data['operations_contact_phone'] ?? null,'email'=>$data['operations_contact_email'] ?? null]),
                    'customer_support_contact'=>array_filter(['name'=>$data['support_contact_name'] ?? null,'phone'=>$data['support_contact_phone'] ?? null,'email'=>$data['support_contact_email'] ?? null]),
                    'emergency_contact'=>array_filter(['name'=>$data['emergency_contact_name'] ?? null,'phone'=>$data['emergency_contact_phone'] ?? null,'email'=>$data['emergency_contact_email'] ?? null]),
                    'status'=>'pending','is_active'=>false,
                ]);
                $provider->users()->attach($user->id,['membership_role'=>'owner','is_active'=>true]);
                return $user;
            });
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
            try { app(\App\Services\RegistrationVerification::class)->send($user,'logistics'); }
            catch (\Throwable $error) { report($error); return redirect()->route('registration.verification')->withErrors(['code'=>'We could not send your verification code. Please use Resend code to try again.']); }
        } catch (\Throwable $error) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            report($error);
            return back()->withInput($request->except(array_merge(['password','password_confirmation'], array_keys($fileFields))))->withErrors(['representative_email'=>'We could not submit your Logistics application. Please check the details and try again.']);
        }
        return redirect()->route('registration.verification')->with('status','Check your email for the verification code. Your Logistics Company application remains pending Admin approval.');
    }
}
