@extends('layouts.auth')

@section('title', 'Register')

@section('auth-card')
<div class="w-full max-w-2xl">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Create your SHOPPICK account</h1>
        <p class="mt-1 text-sm text-slate-500">Register as a Buyer and shop securely from trusted sellers.</p>

        @if(\App\Support\GoogleOAuth::isAvailable())
            <a href="{{ route('auth.google.redirect',['account_type'=>'buyer']) }}" class="btn-outline mt-6 flex w-full items-center justify-center gap-3"><span class="text-lg font-bold text-brand-600">G</span>Continue with Google</a>
        @else
            <button type="button" disabled class="btn-outline mt-6 flex w-full cursor-not-allowed items-center justify-center gap-3 opacity-55"><span class="text-lg font-bold text-brand-600">G</span>Continue with Google</button>
            <p class="mt-2 text-center text-xs text-slate-400">Google registration is currently unavailable.</p>
        @endif
        <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>Or<span class="h-px flex-1 bg-slate-200"></span></div>

        <form method="POST" action="{{ route('register.submit') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf
            <p class="pt-2 text-xs font-bold uppercase tracking-wider text-brand-600">Personal Information</p>
            <div class="grid gap-4 sm:grid-cols-3"><div><label class="label">First Name *</label><input name="first_name" value="{{ old('first_name') }}" required autofocus class="input"></div><div><label class="label">Middle Initial</label><input name="middle_initial" maxlength="5" value="{{ old('middle_initial') }}" class="input"></div><div><label class="label">Last Name *</label><input name="last_name" value="{{ old('last_name') }}" required class="input"></div></div>
            <div class="grid gap-4 sm:grid-cols-3"><div><label class="label">Sex *</label><select name="sex" required class="input"><option value="">Select</option><option value="female" @selected(old('sex')==='female')>Female</option><option value="male" @selected(old('sex')==='male')>Male</option></select></div><x-birthday-age-fields :birthday="old('birthday')" /></div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="input">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Mobile Number</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="09171234567" required class="input">
                @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <p class="pt-2 text-xs font-bold uppercase tracking-wider text-brand-600">Address</p>
            <div><label class="label">House No. / Street</label><input name="address_line" value="{{ old('address_line') }}" required class="input">@error('address_line')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <x-philippine-location-fields
                :region="old('region')" :region-code="old('region_code')"
                :province="old('province')" :province-code="old('province_code')"
                :city="old('city')" :city-code="old('city_code')"
                :barangay="old('barangay')" :barangay-code="old('barangay_code')"
            />
            <div><label class="label">Postal Code</label><input name="postal_code" value="{{ old('postal_code') }}" required class="input">@error('postal_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <input type="hidden" name="country" value="PH">
            <div><label class="label">Upload Valid ID *</label><input type="file" name="valid_id" accept="image/jpeg,image/png,application/pdf" required class="input"><p class="mt-1 text-xs text-slate-500">JPG, PNG, or PDF up to 5 MB. Stored privately for Admin review.</p>@error('valid_id')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <p class="pt-2 text-xs font-bold uppercase tracking-wider text-brand-600">Security</p>
            <div>
                <label class="label">Password</label>
                <input type="password" name="password" required class="input">
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="input">
            </div>
            <label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-500">I agree to the Terms and Privacy Policy.</label>
            @error('terms')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full">Register as Buyer</button>
        </form><p class="mt-4 text-sm text-slate-600">All new accounts start as Buyers. Apply to become a Seller from your account after registration approval.</p><a href="{{ route('register.rider') }}" class="mt-3 block text-sm text-brand-700">Apply as Rider</a>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Login</a></p>
</div>
@endsection
