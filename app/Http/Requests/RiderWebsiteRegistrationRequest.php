<?php
namespace App\Http\Requests;

use App\Support\BuyerIdentity;
use App\Models\LogisticsHub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiderWebsiteRegistrationRequest extends FormRequest
{
    public function authorize(): bool {return true;}
    protected function prepareForValidation(): void
    {
        $this->request->remove('age');
        foreach(['first_name','last_name','middle_initial','email','driver_license_number'] as $field)if(is_string($this->input($field)))$this->merge([$field=>trim($this->input($field))]);
        if(is_string($this->input('email')))$this->merge(['email'=>mb_strtolower($this->input('email'))]);
        if(is_string($this->input('phone')))$this->merge(['phone'=>BuyerIdentity::phone($this->input('phone'))]);
        if(is_string($this->input('driver_license_number')))$this->merge(['driver_license_number'=>strtoupper($this->input('driver_license_number'))]);
    }
    public function rules(): array
    {
        $document=['required','file','mimes:pdf,jpg,jpeg,png','max:'.config('riders.document_max_kilobytes',5120)];
        return [
            'first_name'=>BuyerIdentity::nameRules(),'last_name'=>BuyerIdentity::nameRules(),'middle_initial'=>['nullable','string','max:5'],
            'sex'=>['required','in:male,female'],'birthday'=>['required','date_format:Y-m-d','before_or_equal:today'],'profile_photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:3072'],
            'email'=>['bail','required','string','email','max:255','regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/u',BuyerIdentity::emailRule()],
            'phone'=>['bail','required','string','regex:/^\+639[0-9]{9}$/D',BuyerIdentity::phoneRule()],
            'address_line'=>['required','string','max:255'],'region'=>['required','string','max:100'],'region_code'=>['nullable','string','max:20'],
            'province'=>['nullable','string','max:100'],'province_code'=>['nullable','string','max:20'],'city'=>['required','string','max:100'],'city_code'=>['nullable','string','max:20'],
            'barangay'=>['required','string','max:100'],'barangay_code'=>['nullable','string','max:20'],'postal_code'=>['nullable','string','regex:/^[0-9]{4}$/D'],
            'logistics_provider_id'=>['required', Rule::exists('logistics_providers','id')->where(fn($q)=>$q->where('status','approved')->where('is_active',true))],
            'requested_service_area'=>['required','string','max:160'],
            'requested_hub_id'=>['nullable','integer',function($attribute,$value,$fail){if($value&&!LogisticsHub::whereKey($value)->where('logistics_provider_id',$this->integer('logistics_provider_id'))->where('status','active')->exists())$fail('Choose an active sorting center owned by the selected provider.');}],
            'requested_rider_type'=>['required','in:pickup,delivery,both'],
            'driver_license_number'=>['bail','required','string','min:5','max:80','regex:/^[A-Z0-9 .\/-]+$/','unique:rider_profiles,driver_license_number'],
            'driver_license_classification'=>['required','string','max:100'],'driver_license_expires_at'=>['required','date_format:Y-m-d','after_or_equal:today'],
            'vehicle_type'=>['required','in:motorcycle,scooter,tricycle,car,van,delivery_truck,other'],'vehicle_make_model'=>['required','string','max:160'],
            'plate_number'=>['required','string','max:40','unique:rider_profiles,plate_number'],'vehicle_color'=>['required','string','max:60'],
            'vehicle_registration_number'=>['required','string','max:100'],'vehicle_registration_document'=>$document,
            'valid_id'=>$document,'proof_of_address'=>$document,'driver_license_front'=>$document,'driver_license_back'=>$document,
            'emergency_contact_name'=>['required','string','max:100'],'emergency_contact_relationship'=>['required','string','max:80'],'emergency_contact_phone'=>['required','string','max:30'],
            'password'=>BuyerIdentity::passwordRules(),'password_confirmation'=>['required','string','same:password'],'terms'=>['accepted'],
        ];
    }
    public function messages(): array
    {
        return ['first_name.regex'=>'Enter a valid first name using letters and name punctuation.','last_name.regex'=>'Enter a valid last name using letters and name punctuation.',
            'email.email'=>'Enter a valid email address.','email.regex'=>'Enter a valid email address.','phone.regex'=>'Enter a valid Philippine mobile number.',
            'birthday.before_or_equal'=>'Birthday cannot be in the future.','driver_license_expires_at.after_or_equal'=>'Your driver’s license must not be expired.',
            'password.confirmed'=>'Your passwords do not match.','password_confirmation.same'=>'Your passwords do not match.','terms.accepted'=>'Please accept the Terms and Privacy Policy.','postal_code.regex'=>'Enter a 4-digit postal code.'];
    }
}
