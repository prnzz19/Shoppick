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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'terms' => ['accepted'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email is already registered.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Enter a valid Philippine mobile number.',
            'address_line.required' => 'Address is required.',
            'barangay.required' => 'Barangay is required.',
            'city.required' => 'City/Municipality is required.',
            'province.required' => 'Province is required.',
            'postal_code.required' => 'Postal code is required.',
            'terms.accepted' => 'You must agree to the Terms and Privacy Policy.',
        ];
    }
}
