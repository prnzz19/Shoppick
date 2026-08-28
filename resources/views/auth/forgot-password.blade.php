@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('auth-card')
<div class="w-full max-w-md">
    @if(session('status'))<div class="alert-success mb-4">{{ session('status') }}</div>@endif
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Forgot Password?</h1>
        <p class="mt-1 text-sm text-slate-500">Enter your email and we'll send you a reset link.</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">Send Reset Link</button>
        </form>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600"><a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Back to login</a></p>
</div>
@endsection
