<?php
namespace App\Http\Requests;

use App\Support\BuyerIdentity;
use Illuminate\Foundation\Http\FormRequest;

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
            'sex'=>['required','in:male,female'],'birthday'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'email'=>['bail','required','string','email','max:255','regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/u',BuyerIdentity::emailRule()],
            'phone'=>['bail','required','string','regex:/^\+639[0-9]{9}$/D',BuyerIdentity::phoneRule()],
            'address_line'=>['required','string','max:255'],'region'=>['required','string','max:100'],'region_code'=>['nullable','string','max:20'],
            'province'=>['nullable','string','max:100'],'province_code'=>['nullable','string','max:20'],'city'=>['required','string','max:100'],'city_code'=>['nullable','string','max:20'],
            'barangay'=>['required','string','max:100'],'barangay_code'=>['nullable','string','max:20'],'postal_code'=>['nullable','string','regex:/^[0-9]{4}$/D'],
            'preferred_vehicle_type'=>['nullable','in:motorcycle,scooter,car,van'],'emergency_contact_name'=>['nullable','string','max:100'],'emergency_contact_phone'=>['nullable','string','max:30'],
            'driver_license_number'=>['bail','required','string','min:5','max:80','regex:/^[A-Z0-9 .\/-]+$/','unique:rider_profiles,driver_license_number'],
            'driver_license_classification'=>['required','string','max:100'],'driver_license_expires_at'=>['required','date_format:Y-m-d','after_or_equal:today'],
            'valid_id'=>$document,'driver_license_front'=>$document,'driver_license_back'=>$document,
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
