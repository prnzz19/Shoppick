@extends('layouts.auth')

@section('title', 'Register')

@section('auth-card')
<div class="w-full max-w-2xl">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Create your SHOPPICK account</h1>
        <p class="mt-1 text-sm text-slate-500">Register as a Buyer and shop securely from trusted sellers.</p>

        <a href="{{ route('auth.google.redirect',['account_type'=>'buyer']) }}" class="btn-outline mt-6 flex w-full items-center justify-center gap-3"><span class="text-lg font-bold text-brand-600">G</span>Continue with Google</a>
        <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>Or<span class="h-px flex-1 bg-slate-200"></span></div>

        <form method="POST" action="{{ route('register.submit') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus class="input">
                @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
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
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['barangay'=>'Barangay','city'=>'City / Municipality','province'=>'Province','postal_code'=>'Postal Code'] as $field=>$label)
                    <div><label class="label">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field) }}" required class="input">@error($field)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                @endforeach
            </div>
            <input type="hidden" name="country" value="PH">
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
        </form>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600"><a href="{{ route('register') }}" class="font-semibold text-slate-500">← Choose account type</a> · Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Login</a></p>
</div>
@endsection
