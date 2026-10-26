<?php

namespace App\Http\Requests;

use App\Services\PhilippineGeographyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class LogisticsRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $fields = ['company_name','trade_name','company_email','company_phone','first_name','last_name','contact_name','representative_position','representative_email','representative_phone','street','barangay','city','province','postal_code','region','region_code','province_code','city_code','barangay_code','sec_registration_number','registration_number','tin','operating_hours'];
        $values = [];
        foreach ($fields as $field) if (is_string($this->input($field))) $values[$field] = trim($this->input($field));
        foreach (['company_phone','representative_phone'] as $field) if (isset($values[$field])) $values[$field] = self::normalizePhone($values[$field]);
        if (isset($values['company_email'])) $values['company_email'] = mb_strtolower($values['company_email']);
        if (isset($values['representative_email'])) $values['representative_email'] = mb_strtolower($values['representative_email']);
        if (isset($values['tin'])) $values['tin'] = preg_replace('/\s+/', '', $values['tin']);
        if (isset($values['sec_registration_number'])) $values['sec_registration_number'] = mb_strtoupper(preg_replace('/\s+/', '', $values['sec_registration_number']));
        if (isset($values['registration_number']) && ! isset($values['sec_registration_number'])) $values['sec_registration_number'] = mb_strtoupper(preg_replace('/\s+/', '', $values['registration_number']));
        if (! $this->filled('contact_name') && $this->filled('first_name') && $this->filled('last_name')) $values['contact_name'] = trim($this->input('first_name').' '.$this->input('last_name'));
        if (! $this->filled('service_regions') && $this->filled('areas_served')) $values['service_regions'] = collect(preg_split('/[,\r\n]+/', (string) $this->input('areas_served')))->map(fn ($v) => trim($v))->filter()->values()->all();
        if ($values) $this->merge($values);
    }

    private function personName(): array
    {
        return ['required','string','min:2','max:50','regex:/^[\p{L}]+(?:[ \'\-.][\p{L}]+)*$/u'];
    }

    public static function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/[\s\-().]/', '', trim((string) $phone));
        return preg_match('/^09\d{9}$/', $phone) ? '+63'.substr($phone, 1) : $phone;
    }

    public function rules(): array
    {
        $document = ['file','mimes:pdf,jpg,jpeg,png','max:5120'];
        return [
            'company_name' => ['required','string','min:3','max:160'],
            'trade_name' => ['nullable','string','max:160'],
            'company_email' => ['required','email','max:255'], 'company_phone' => ['nullable','string','max:30'],
            'company_logo' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:3072'],
            'first_name' => $this->personName(), 'last_name' => $this->personName(),
            'contact_name' => ['required','string','max:120'], 'representative_position' => ['required','string','max:100'],
            'representative_email' => ['required','email','max:255','unique:users,email'],
            'representative_phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'representative_id' => array_merge(['required'], $document),
            'street' => ['required','string','max:255'], 'region' => ['required','string','max:100'], 'region_code' => ['nullable','string','max:20'],
            'province' => ['required','string','max:100'], 'province_code' => ['nullable','string','max:20'],
            'city' => ['required','string','max:100'], 'city_code' => ['nullable','string','max:20'],
            'barangay' => ['required','string','max:100'], 'barangay_code' => ['nullable','string','max:20'],
            'postal_code' => ['required','digits:4'],
            'sec_registration_number' => ['required','regex:/^[A-Z]{2}\d{9}$/i'],
            'tin' => ['required','regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'sec_certificate' => array_merge(['required_without:business_registration'], $document),
            'business_registration' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'bir_certificate' => array_merge(['required_without:tax_registration_document'], $document),
            'tax_registration_document' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'mayors_permit' => array_merge(['required_without:permit'], $document),
            'permit' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'service_regions' => ['required','array','min:1','distinct'], 'service_regions.*' => ['string','max:100'],
            'areas_served' => ['nullable','string','max:2000'], 'pickup_coverage_areas' => ['nullable','string','max:2000'], 'delivery_coverage_areas' => ['nullable','string','max:2000'],
            'operating_hours' => ['nullable','string','max:1000'],
            'number_of_riders' => ['nullable','integer','min:0','max:100000'], 'number_of_vehicles' => ['nullable','integer','min:0','max:100000'],
            'supported_vehicle_types' => ['nullable','array'], 'supported_vehicle_types.*' => ['in:motorcycle,scooter,tricycle,car,van,delivery_truck,other'],
            'operations_contact_name' => ['nullable','string','max:120'], 'operations_contact_phone' => ['nullable','string','max:30'], 'operations_contact_email' => ['nullable','email','max:255'],
            'support_contact_name' => ['nullable','string','max:120'], 'support_contact_phone' => ['nullable','string','max:30'], 'support_contact_email' => ['nullable','email','max:255'],
            'emergency_contact_name' => ['nullable','string','max:120'], 'emergency_contact_phone' => ['nullable','string','max:30'], 'emergency_contact_email' => ['nullable','email','max:255'],
            'password' => ['required','string',Password::min(8)->mixedCase()->numbers()->symbols(),'confirmed'], 'terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) return;
if (DB::table('users')->where('phone', $this->input('representative_phone'))->exists()) $validator->errors()->add('representative_phone','That mobile number is already registered in SHOPPICK.');
            if (DB::table('logistics_providers')->whereRaw('LOWER(company_name) = ?', [mb_strtolower((string) $this->input('company_name'))])->exists()) $validator->errors()->add('company_name','That Logistics Company is already registered.');
            if (DB::table('logistics_providers')->where('sec_registration_number',$this->input('sec_registration_number'))->exists()) $validator->errors()->add('sec_registration_number','That SEC registration is already registered.');
            if (DB::table('seller_applications')->where('tin',$this->input('tin'))->exists() || DB::table('logistics_providers')->where('tin',$this->input('tin'))->exists()) $validator->errors()->add('tin','That TIN is already registered in SHOPPICK.');
            $codes = ['region_code','province_code','city_code','barangay_code'];
            if (collect($codes)->every(fn ($field) => filled($this->input($field)))) {
                try { $this->replace(app(PhilippineGeographyService::class)->normalizeAddress($this->all())); }
                catch (\Throwable $e) { $validator->errors()->add('region','Select a valid linked Philippine address.'); }
            }
        });
    }

    public function messages(): array
    {
        return ['first_name.regex'=>'Use letters with spaces, hyphens, apostrophes, or periods only.','last_name.regex'=>'Use letters with spaces, hyphens, apostrophes, or periods only.','representative_phone.regex'=>'Use 09XXXXXXXXX or +639XXXXXXXXX.','postal_code.digits'=>'Postal code must contain exactly 4 digits.','terms.accepted'=>'Accept the Logistics Company terms to continue.'];
    }
}
