<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class BuyerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Age is derived from Birthday and is never trusted from the browser.
        $this->request->remove('age');
        $phone = preg_replace('/[^0-9+]/', '', (string) $this->input('phone'));
        if (preg_match('/^09\d{9}$/', $phone)) {
            $phone = '+63'.substr($phone, 1);
        } elseif (preg_match('/^639\d{9}$/', $phone)) {
            $phone = '+'.$phone;
        }
        $this->merge(['phone' => $phone, 'country' => strtoupper((string) $this->input('country', 'PH'))]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', 'in:male,female'],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'region_code' => ['nullable', 'string', 'max:20'],
            'province' => ['required_without:region_code', 'nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'city_code' => ['nullable', 'string', 'max:20'],
            'barangay_code' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'terms' => ['accepted'],
            'valid_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email is already registered.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Enter a valid Philippine mobile number.',
            'birthday.before_or_equal' => 'Birthday cannot be in the future.',
            'address_line.required' => 'Address is required.',
            'barangay.required' => 'Barangay is required.',
            'city.required' => 'City/Municipality is required.',
            'province.required_without' => 'Province is required.',
            'postal_code.required' => 'Postal code is required.',
            'terms.accepted' => 'You must agree to the Terms and Privacy Policy.',
        ];
    }
}
