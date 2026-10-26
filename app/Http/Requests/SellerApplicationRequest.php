<?php

namespace App\Http\Requests;

use App\Services\PhilippineGeographyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rule;

class SellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_active
            && $this->user()->isBuyer()
            && ! $this->user()->hasApprovedSellerAccess());
    }

    protected function prepareForValidation(): void
    {
        $fields = ['store_name', 'seller_type', 'tin', 'phone', 'address_line', 'address', 'region', 'region_code', 'province', 'province_code', 'city', 'city_code', 'barangay', 'barangay_code', 'postal_code', 'store_description', 'business_information'];
        $values = [];
        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        if (! isset($values['phone']) || $values['phone'] === '') {
            $values['phone'] = $this->user()?->phone;
        }
        if ((! isset($values['address_line']) || $values['address_line'] === '') && isset($values['address'])) {
            $values['address_line'] = $values['address'];
        }
        if (isset($values['tin'])) {
            $values['tin'] = preg_replace('/\s+/', '', $values['tin']);
        }
        if (isset($values['phone'])) {
            $values['phone'] = self::normalizePhone($values['phone']);
        }
        $this->merge($values);
    }

    public static function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/[\s\-().]/', '', trim((string) $phone));

        return preg_match('/^09\d{9}$/', $phone) ? '+63'.substr($phone, 1) : $phone;
    }

    public function rules(): array
    {
        $file = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $settings = app(\App\Services\SystemSettings::class);
        $logoRequired = (bool) $settings->get('seller.logo_required', false);
        $logoMaxSize = (int) $settings->get('seller.logo_max_size', 2048);
        return [
            'store_name' => ['required', 'string', 'min:3', 'max:30'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'seller_type' => ['required', Rule::in(['Individual', 'Sole Proprietorship', 'Corporation'])],
            'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'store_description' => ['required', 'string', 'max:2000'],
            'business_information' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'region_code' => ['required', 'string', 'regex:/^\d{9,10}$/'],
            'province' => ['nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'regex:/^\d{9,10}$/'],
            'city' => ['required', 'string', 'max:100'],
            'city_code' => ['required', 'string', 'regex:/^\d{9,10}$/'],
            'barangay' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'regex:/^\d{9,10}$/'],
            'postal_code' => ['required', 'digits:4'],
            'valid_id' => array_merge(['required'], $file),
            'business_permit' => array_merge(['nullable'], $file),
            'dti_certificate' => array_merge([Rule::requiredIf(fn () => $this->input('seller_type') === 'Sole Proprietorship')], $file),
            'sec_certificate' => array_merge([Rule::requiredIf(fn () => $this->input('seller_type') === 'Corporation')], $file),
            'bir_2303' => array_merge([Rule::requiredIf(fn () => in_array($this->input('seller_type'), ['Sole Proprietorship', 'Corporation'], true))], $file),
            'logo' => [$logoRequired ? 'required' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.$logoMaxSize],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'seller_terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $userId = $this->user()?->id;
            $storeName = mb_strtolower(trim((string) $this->input('store_name')));
            if (DB::table('stores')->whereRaw('LOWER(name) = ?', [$storeName])->where('user_id', '!=', $userId)->exists()) {
                $validator->errors()->add('store_name', 'That shop name is already in use. Choose another name.');
            }
            if (Schema::hasColumn('seller_applications', 'tin')) {
                $query = DB::table('seller_applications')->where('tin', $this->input('tin'))->where('user_id', '!=', $userId);
                if ($query->exists()) {
                    $validator->errors()->add('tin', 'That TIN is already registered in SHOPPICK.');
                }
            }
            if (Schema::hasTable('logistics_providers') && Schema::hasColumn('logistics_providers', 'tin') && DB::table('logistics_providers')->where('tin', $this->input('tin'))->exists()) {
                $validator->errors()->add('tin', 'That TIN is already registered in SHOPPICK.');
            }
            try {
                $normalized = app(PhilippineGeographyService::class)->normalizeAddress($this->all());
                $this->merge(array_intersect_key($normalized, array_flip(['region', 'region_code', 'province', 'province_code', 'city', 'city_code', 'barangay', 'barangay_code'])));
            } catch (\Throwable $exception) {
                $validator->errors()->add('region', 'Select a valid linked Philippine address.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Use a Philippine mobile number such as 09XXXXXXXXX or +639XXXXXXXXX.',
            'tin.regex' => 'TIN must use the format 000-000-000-000.',
            'postal_code.digits' => 'Postal code must contain exactly 4 digits.',
            'seller_terms.accepted' => 'Accept the Seller terms to continue.',
            'valid_id.mimes' => 'Government ID must be a JPG, PNG, or PDF file.',
            'business_permit.mimes' => 'Business permit must be a JPG, PNG, or PDF file.',
            'dti_certificate.mimes' => 'DTI certificate must be a JPG, PNG, or PDF file.',
            'sec_certificate.mimes' => 'SEC certificate must be a JPG, PNG, or PDF file.',
            'bir_2303.mimes' => 'BIR Form 2303 must be a JPG, PNG, or PDF file.',
        ];
    }
}
