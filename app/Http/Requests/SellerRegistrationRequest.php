<?php

namespace App\Http\Requests;

class SellerRegistrationRequest extends BuyerRegistrationRequest
{
    public function rules(): array
    {
        $rules=parent::rules();
        unset($rules['terms']);
        return $rules+[
            'store_name'=>['required','string','max:120'],
            'store_description'=>['required','string','max:2000'],
            'business_information'=>['nullable','string','max:2000'],
            'same_address'=>['nullable','boolean'],
            'store_address_line'=>['required_unless:same_address,1','nullable','string','max:255'],
            'store_barangay'=>['required_unless:same_address,1','nullable','string','max:100'],
            'store_city'=>['required_unless:same_address,1','nullable','string','max:100'],
            'store_province'=>['required_unless:same_address,1','nullable','string','max:100'],
            'store_postal_code'=>['required_unless:same_address,1','nullable','string','max:20'],
            'logo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'seller_terms'=>['accepted'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(),[
            'email.unique'=>'This email already has a SHOPPICK account. Log in and apply to become a Seller.',
            'store_name.required'=>'Store name is required.',
            'store_description.required'=>'Store description is required.',
            'seller_terms.accepted'=>'Please accept the Seller Policy.',
        ]);
    }
}
