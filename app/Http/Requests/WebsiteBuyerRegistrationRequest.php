<?php
namespace App\Http\Requests;

use App\Support\BuyerIdentity;

class WebsiteBuyerRegistrationRequest extends BuyerRegistrationRequest
{
    protected function prepareForValidation(): void
    {
        $this->request->remove('age');
        foreach(['first_name','last_name','middle_initial'] as $field)if(is_string($this->input($field)))$this->merge([$field=>trim($this->input($field))]);
        if(is_string($this->input('email')))$this->merge(['email'=>mb_strtolower(trim($this->input('email')))]);
        if(is_string($this->input('phone')))$this->merge(['phone'=>BuyerIdentity::phone($this->input('phone'))]);
        if(is_string($this->input('country','PH')))$this->merge(['country'=>strtoupper($this->input('country','PH'))]);
    }
    public function rules(): array
    {
        return array_replace(parent::rules(),[
            'first_name'=>BuyerIdentity::nameRules(),'last_name'=>BuyerIdentity::nameRules(),
            'birthday'=>['required','date_format:Y-m-d','before_or_equal:'.BuyerIdentity::birthdayCutoff()],
            'email'=>['bail','required','string','email','regex:/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/u','max:255',BuyerIdentity::emailRule($this->retryUser()?->id)],
            'phone'=>['bail','required','string','regex:/^\\+639[0-9]{9}$/D',BuyerIdentity::phoneRule($this->retryUser()?->id)],
            'postal_code'=>['required','string','regex:/^[0-9]{4}$/D'],
            'password'=>BuyerIdentity::passwordRules(),
            'password_confirmation'=>['required','string','same:password'],
            'registration_token'=>['bail','nullable','string','size:64',function($field,$value,$fail){if(!hash_equals((string)$this->session()->get('buyer_signup_token',''),$value))$fail('Your registration form has expired. Reload it and try again.');}],
        ]);
    }
    public function messages(): array
    {
        return array_merge(parent::messages(),[
            'first_name.min'=>'First name must contain 2–50 valid characters.','first_name.max'=>'First name must contain 2–50 valid characters.','first_name.regex'=>'First name must contain 2–50 valid characters.',
            'last_name.min'=>'Last name must contain 2–50 valid characters.','last_name.max'=>'Last name must contain 2–50 valid characters.','last_name.regex'=>'Last name must contain 2–50 valid characters.',
            'birthday.before_or_equal'=>'You must be at least 18 years old.','email.email'=>'Enter a valid email address.',
            'email.regex'=>'Enter a valid email address.',
            'postal_code.regex'=>'Postal code must contain exactly 4 digits.','password.confirmed'=>'Passwords do not match.',
            'password_confirmation.same'=>'Passwords do not match.',
            'password.min'=>'Password must contain at least 8 characters, uppercase, lowercase, a number and a special character.',
        ]);
    }
    public function retryUser(): ?\App\Models\User
    {
        $token=$this->input('registration_token');
        if(!is_string($token)||strlen($token)!==64||!hash_equals((string)$this->session()->get('buyer_signup_token',''),$token))return null;
        $user=\App\Models\User::where('buyer_web_signup_key',hash('sha256',$token))->first();
        // A fresh form can also resume a pending account after proving its credentials.
        if(!$user&&is_string($this->input('email'))&&is_string($this->input('phone')))$user=\App\Models\User::where('email',$this->input('email'))->where('phone',$this->input('phone'))->where('registration_type','buyer')->where('registration_status','unverified')->first();
        return $user&&BuyerIdentity::needsVerification($user)&&$user->email===$this->input('email')&&$user->phone===$this->input('phone')
            &&is_string($this->input('password'))&&\Illuminate\Support\Facades\Hash::check($this->input('password'),$user->password)?$user:null;
    }
}
