@extends('layouts.auth')
@section('title', 'Complete Profile')
@section('auth-card')
<div class="w-full max-w-xl"><div class="card p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-navy-800">Complete Your SHOPPICK Profile</h1>
    <p class="mt-1 text-sm text-slate-500">Google provided your name and email. Add the delivery details required for checkout.</p>
    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><p class="font-semibold text-navy-800">{{ auth()->user()->name }}</p><p class="text-slate-500">{{ auth()->user()->email }}</p></div>
    <form method="POST" action="{{ route('profile.complete.update') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
        <div><label class="label">First Name *</label><input class="input" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" required></div><div><label class="label">Middle Initial</label><input class="input" name="middle_initial" value="{{ old('middle_initial', auth()->user()->middle_initial) }}" maxlength="5"></div><div><label class="label">Last Name *</label><input class="input" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" required></div><div><label class="label">Sex *</label><select class="input" name="sex" required><option value="">Select</option><option value="female" @selected(old('sex',auth()->user()->sex)==='female')>Female</option><option value="male" @selected(old('sex',auth()->user()->sex)==='male')>Male</option></select></div><x-birthday-age-fields :birthday="old('birthday', auth()->user()->birthday?->toDateString())" />
        <div class="sm:col-span-2"><label class="label">Mobile Number</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="09171234567" required class="input">@error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label class="label">House No. / Street</label><input name="address_line" value="{{ old('address_line') }}" required class="input">@error('address_line')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <x-philippine-location-fields class="sm:col-span-2" :region="old('region')" :region-code="old('region_code')" :province="old('province')" :province-code="old('province_code')" :city="old('city')" :city-code="old('city_code')" :barangay="old('barangay')" :barangay-code="old('barangay_code')" />
        <div><label class="label">Postal Code</label><input name="postal_code" value="{{ old('postal_code') }}" required class="input">@error('postal_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <input type="hidden" name="country" value="PH">
        <div class="sm:col-span-2"><label class="label">Valid ID *</label><input class="input" type="file" name="valid_id" accept="image/jpeg,image/png,application/pdf" required><p class="mt-1 text-xs text-slate-500">Stored privately for Admin approval.</p></div>
        <button class="btn-primary sm:col-span-2">Complete Registration</button>
    </form>
</div></div>
@endsection
