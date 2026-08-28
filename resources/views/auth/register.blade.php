@extends('layouts.auth')

@section('title', 'Register')

@section('auth-card')
<div class="w-full max-w-md">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Create Account</h1>
        <p class="mt-1 text-sm text-slate-500">Join SHOPPICK and start shopping today.</p>

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
                <label class="label">Phone (optional)</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="input">
            </div>
            <div>
                <label class="label">Password</label>
                <input type="password" name="password" required class="input">
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="input">
            </div>
            <button type="submit" class="btn-primary w-full">Register</button>
        </form>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Login</a></p>
</div>
@endsection
