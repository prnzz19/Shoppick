@extends('layouts.account')

@section('title', 'Change Password')

@section('account-content')
<div class="card max-w-xl p-6 sm:p-7">
    <p class="text-sm font-semibold text-brand-600">Account Security</p>
    <h1 class="mt-1 text-2xl font-extrabold text-navy-900">Change Password</h1>
    <p class="mt-2 text-sm text-slate-500">Use a strong password to keep your SHOPPICK account protected.</p>
    <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label">Current Password</label>
            <input type="password" name="current_password" required class="input">
            @error('current_password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">New Password</label>
            <input type="password" name="password" required class="input">
        </div>
        <div>
            <label class="label">Confirm New Password</label>
            <input type="password" name="password_confirmation" required class="input">
        </div>
        <button type="submit" class="btn-primary">Update Password</button>
    </form>
</div>
@endsection
