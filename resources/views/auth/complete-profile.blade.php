@extends('layouts.auth')
@section('title', 'Complete Profile')
@section('auth-card')
<div class="w-full max-w-xl"><div class="card p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-navy-800">Complete Your SHOPPICK Profile</h1>
    <p class="mt-1 text-sm text-slate-500">Google provided your name and email. Add the delivery details required for checkout.</p>
    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><p class="font-semibold text-navy-800">{{ auth()->user()->name }}</p><p class="text-slate-500">{{ auth()->user()->email }}</p></div>
    <form method="POST" action="{{ route('profile.complete.update') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
        <div class="sm:col-span-2"><label class="label">Mobile Number</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="09171234567" required class="input">@error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label class="label">House No. / Street</label><input name="address_line" value="{{ old('address_line') }}" required class="input">@error('address_line')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        @foreach(['barangay'=>'Barangay','city'=>'City / Municipality','province'=>'Province','postal_code'=>'Postal Code'] as $field=>$label)<div><label class="label">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field) }}" required class="input">@error($field)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>@endforeach
        <input type="hidden" name="country" value="PH">
        <button class="btn-primary sm:col-span-2">Complete Registration</button>
    </form>
</div></div>
@endsection
