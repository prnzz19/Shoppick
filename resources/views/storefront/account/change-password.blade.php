@extends('layouts.account')

@section('title', 'Change Password')

@section('account-content')
<div class="card p-6 max-w-lg">
    <h1 class="text-xl font-bold text-navy-800">Change Password</h1>
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
