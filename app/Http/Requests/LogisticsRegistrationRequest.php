<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class LogisticsRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        foreach (['company_name','trade_name','company_email','company_phone','contact_name','representative_position','representative_email','representative_phone','street','barangay','city','province','postal_code','registration_number','tax_registration_info','operating_hours'] as $field) {
            if (is_string($this->input($field))) $this->merge([$field => trim($this->input($field))]);
        }
        if (is_string($this->input('representative_email'))) $this->merge(['representative_email' => mb_strtolower($this->input('representative_email'))]);
    }

    public function rules(): array
    {
        $document = ['required','file','mimes:pdf,jpg,jpeg,png','max:5120'];
        return [
            'company_name'=>'required|string|min:2|max:160', 'trade_name'=>'required|string|max:160',
            'company_email'=>'required|email|max:255', 'company_phone'=>'required|string|max:30',
            'company_logo'=>'required|image|mimes:jpg,jpeg,png,webp|max:3072',
            'contact_name'=>'required|string|max:120', 'representative_position'=>'required|string|max:100',
            'representative_email'=>'required|email|max:255|unique:users,email', 'representative_phone'=>'required|string|max:30',
            'representative_id'=>$document,
            'street'=>'required|string|max:255', 'barangay'=>'required|string|max:100', 'city'=>'required|string|max:100',
            'province'=>'required|string|max:100', 'postal_code'=>'nullable|string|max:12',
            'registration_number'=>'required|string|max:100', 'business_registration'=>$document, 'permit'=>$document,
            'tax_registration_info'=>'required|string|max:1000', 'tax_registration_document'=>$document,
            'areas_served'=>'required|string|max:2000', 'pickup_coverage_areas'=>'required|string|max:2000',
            'delivery_coverage_areas'=>'required|string|max:2000', 'operating_hours'=>'required|string|max:1000',
            'number_of_riders'=>'required|integer|min:0|max:100000', 'number_of_vehicles'=>'required|integer|min:0|max:100000',
            'supported_vehicle_types'=>'required|array|min:1', 'supported_vehicle_types.*'=>'in:motorcycle,scooter,tricycle,car,van,delivery_truck,other',
            'operations_contact_name'=>'required|string|max:120', 'operations_contact_phone'=>'required|string|max:30', 'operations_contact_email'=>'required|email|max:255',
            'support_contact_name'=>'required|string|max:120', 'support_contact_phone'=>'required|string|max:30', 'support_contact_email'=>'required|email|max:255',
            'emergency_contact_name'=>'required|string|max:120', 'emergency_contact_phone'=>'required|string|max:30', 'emergency_contact_email'=>'nullable|email|max:255',
            'password'=>'required|string|min:8|confirmed', 'terms'=>'accepted',
        ];
    }
}
