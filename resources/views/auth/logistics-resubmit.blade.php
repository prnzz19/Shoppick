@extends('layouts.auth')
@section('title','Resubmit Logistics Application')
@section('auth-card')
<div class="w-full max-w-4xl"><section class="card p-6 sm:p-8">
<p class="text-xs font-bold uppercase tracking-widest text-brand-700">SHOPPICK Logistics</p>
<h1 class="mt-2 text-2xl font-black text-navy-900">Update your Logistics application</h1>
<p class="mt-2 text-sm text-slate-500">Correct the requested information and resubmit this existing company application for Admin review.</p>
@if(session('status'))<div class="alert-success mt-5" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert-error mt-5" role="alert">{{ $errors->first() }}</div>@endif
@if($provider->status === 'pending')<div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">Your updated Logistics Company application is pending Admin review. Operational access remains unavailable until approval.</div>@else
<form method="POST" action="{{ route('logistics.application.resubmit.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
<div><label class="label">Legal company name *</label><input class="input" name="company_name" value="{{ old('company_name',$provider->company_name) }}" required></div>
<div><label class="label">Trade name</label><input class="input" name="trade_name" value="{{ old('trade_name',$provider->trade_name) }}"></div>
<div><label class="label">Company email *</label><input class="input" type="email" name="company_email" value="{{ old('company_email',$provider->email) }}" required></div>
<div><label class="label">Company phone</label><input class="input" name="company_phone" value="{{ old('company_phone',$provider->phone) }}"></div>
<div><label class="label">SEC registration number *</label><input class="input" name="sec_registration_number" value="{{ old('sec_registration_number',$provider->sec_registration_number ?: $provider->registration_number) }}" required></div>
<div><label class="label">TIN *</label><input class="input" name="tin" value="{{ old('tin',$provider->tin) }}" required></div>
<div><label class="label">Representative position *</label><input class="input" name="representative_position" value="{{ old('representative_position',$provider->representative_position) }}" required></div>
<div><label class="label">Representative phone *</label><input class="input" name="representative_phone" value="{{ old('representative_phone',$provider->representative_phone) }}" required></div>
<div class="sm:col-span-2"><label class="label">Street address *</label><input class="input" name="street" value="{{ old('street',$provider->address) }}" required></div>
<div><label class="label">Region *</label><input class="input" name="region" value="{{ old('region',$provider->region) }}" required></div><div><label class="label">Region code</label><input class="input" name="region_code" value="{{ old('region_code',$provider->region_code) }}"></div>
<div><label class="label">Province *</label><input class="input" name="province" value="{{ old('province',$provider->province) }}" required></div><div><label class="label">Province code</label><input class="input" name="province_code" value="{{ old('province_code',$provider->province_code) }}"></div>
<div><label class="label">City / Municipality *</label><input class="input" name="city" value="{{ old('city',$provider->city) }}" required></div><div><label class="label">City code</label><input class="input" name="city_code" value="{{ old('city_code',$provider->city_code) }}"></div>
<div><label class="label">Barangay *</label><input class="input" name="barangay" value="{{ old('barangay',$provider->barangay) }}" required></div><div><label class="label">Barangay code</label><input class="input" name="barangay_code" value="{{ old('barangay_code',$provider->barangay_code) }}"></div>
<div><label class="label">Postal code *</label><input class="input" name="postal_code" value="{{ old('postal_code',$provider->postal_code) }}" required></div>
<div><label class="label">Service region *</label><select class="input" name="service_regions[]" required><option value="NCR" @selected(in_array('NCR',old('service_regions',$provider->service_regions ?: []),true))>NCR</option><option value="Region IV-A" @selected(in_array('Region IV-A',old('service_regions',$provider->service_regions ?: []),true))>Region IV-A</option><option value="Region VII" @selected(in_array('Region VII',old('service_regions',$provider->service_regions ?: []),true))>Region VII</option></select></div>
<div><label class="label">Representative ID (optional)</label><input class="input" type="file" name="representative_id" accept="image/jpeg,image/png,application/pdf"></div><div><label class="label">SEC certificate (optional)</label><input class="input" type="file" name="sec_certificate" accept="image/jpeg,image/png,application/pdf"></div><div><label class="label">BIR certificate (optional)</label><input class="input" type="file" name="bir_certificate" accept="image/jpeg,image/png,application/pdf"></div><div><label class="label">Mayor's permit (optional)</label><input class="input" type="file" name="mayors_permit" accept="image/jpeg,image/png,application/pdf"></div>
<div class="sm:col-span-2"><button class="btn-primary w-full" type="submit">Resubmit Application</button></div>
</form>@endif</section></div>
@endsection
