<?php

namespace App\Http\Requests;

use App\Services\PhilippineGeographyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SellerRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $trim = ['first_name','last_name','email','phone','store_name','seller_type','tin','address_line','region','region_code','province','province_code','city','city_code','barangay','barangay_code','postal_code','store_description','business_information'];
        $values = [];
        foreach ($trim as $field) if (is_string($this->input($field))) $values[$field] = trim($this->input($field));
        if (isset($values['email'])) $values['email'] = mb_strtolower($values['email']);
        if (isset($values['tin'])) $values['tin'] = preg_replace('/\s+/', '', $values['tin']);
        if (isset($values['phone'])) $values['phone'] = self::normalizePhone($values['phone']);
        if ($values) $this->merge($values);
    }

    public static function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/[\s\-().]/', '', trim((string) $phone));
        return preg_match('/^09\d{9}$/', $phone) ? '+63'.substr($phone, 1) : $phone;
    }

    private function personName(): array
    {
        return ['required','string','min:2','max:50','regex:/^[\p{L}]+(?:[ \'\-.][\p{L}]+)*$/u'];
    }

    public function rules(): array
    {
        $file = ['file','mimes:jpg,jpeg,png,pdf','max:5120'];
        return [
            'first_name' => $this->personName(), 'last_name' => $this->personName(),
            'email' => ['required','email','max:255','unique:users,email'], 'phone' => ['required','regex:/^\+639\d{9}$/'],
            'store_name' => ['required','string','min:3','max:30'], 'category_id' => ['required','integer','exists:categories,id'],
            'seller_type' => ['required',Rule::in(['Individual','Sole Proprietorship','Corporation'])], 'tin' => ['required','regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'address_line' => ['required','string','max:255'], 'region' => ['required','string','max:100'], 'region_code' => ['nullable','string','max:20'],
            'province' => ['required','string','max:100'], 'province_code' => ['nullable','string','max:20'], 'city' => ['required','string','max:100'], 'city_code' => ['nullable','string','max:20'],
            'barangay' => ['required','string','max:100'], 'barangay_code' => ['nullable','string','max:20'], 'postal_code' => ['required','digits:4'],
            'store_description' => ['required','string','max:2000'], 'business_information' => ['nullable','string','max:2000'],
            'valid_id' => array_merge(['required'], $file), 'business_permit' => array_merge(['nullable'], $file),
            'dti_certificate' => array_merge([Rule::requiredIf(fn () => $this->input('seller_type') === 'Sole Proprietorship')], $file),
            'bir_2303' => array_merge([Rule::requiredIf(fn () => in_array($this->input('seller_type'), ['Sole Proprietorship','Corporation'], true))], $file),
            'sec_certificate' => array_merge([Rule::requiredIf(fn () => $this->input('seller_type') === 'Corporation')], $file),
            'password' => ['required','string',Password::min(8)->mixedCase()->numbers()->symbols(),'confirmed'], 'seller_terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) return;
if (DB::table('users')->where('phone', $this->input('phone'))->exists()) $validator->errors()->add('phone','That mobile number is already registered in SHOPPICK.');
            $tin = $this->input('tin');
            if ($tin && ((Schema::hasColumn('seller_applications','tin') && DB::table('seller_applications')->where('tin',$tin)->exists()) || (Schema::hasTable('logistics_providers') && Schema::hasColumn('logistics_providers','tin') && DB::table('logistics_providers')->where('tin',$tin)->exists()))) $validator->errors()->add('tin','That TIN is already registered in SHOPPICK.');
            $name = mb_strtolower((string) $this->input('store_name'));
            if (DB::table('stores')->whereRaw('LOWER(name) = ?', [$name])->exists()) $validator->errors()->add('store_name','That shop name is already in use.');
            $codes = ['region_code','province_code','city_code','barangay_code'];
            if (collect($codes)->every(fn ($field) => filled($this->input($field)))) {
                try { $this->replace(app(PhilippineGeographyService::class)->normalizeAddress($this->all())); }
                catch (\Throwable $e) { $validator->errors()->add('region','Select a valid linked Philippine address.'); }
            }
        });
    }

    public function messages(): array
    {
        return ['first_name.regex'=>'Use letters with spaces, hyphens, apostrophes, or periods only.','last_name.regex'=>'Use letters with spaces, hyphens, apostrophes, or periods only.','phone.regex'=>'Use a Philippine mobile number such as 09XXXXXXXXX or +639XXXXXXXXX.','postal_code.digits'=>'Postal code must contain exactly 4 digits.','seller_terms.accepted'=>'Accept the Seller terms to continue.'];
    }
}
