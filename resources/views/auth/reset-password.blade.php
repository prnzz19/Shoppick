@extends('layouts.auth')

@section('title', 'Reset Password')

@section('auth-card')
<div class="w-full max-w-md">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Reset Password</h1>
        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus class="input">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">New Password</label>
                <input type="password" name="password" required class="input">
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="input">
            </div>
            <button type="submit" class="btn-primary w-full">Reset Password</button>
        </form>
    </div>
</div>
@endsection
