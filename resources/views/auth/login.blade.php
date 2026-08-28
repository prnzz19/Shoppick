@extends('layouts.auth')

@section('title', 'Login')

@section('auth-card')
<div class="w-full max-w-md">
    @if(session('status'))<div class="alert-success mb-4">{{ session('status') }}</div>@endif
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-navy-800">Welcome Back!</h1>
        <p class="mt-1 text-sm text-slate-500">Log in to continue shopping with SHOPPICK.</p>

        <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label class="label !mb-0">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Forgot password?</a>
                </div>
                <input type="password" name="password" required class="input">
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-navy-700">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-300">
                Remember me
            </label>
            <button type="submit" class="btn-primary w-full">Login</button>
        </form>

        <div class="mt-6 flex flex-col items-center gap-2 rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-500">
            <p>Demo accounts:</p>
            <div class="grid grid-cols-1 gap-1 text-left">
                <p><span class="font-mono font-semibold text-navy-700">superadmin@shoppick.test</span> / password</p>
                <p><span class="font-mono font-semibold text-navy-700">admin@shoppick.test</span> / password</p>
                <p><span class="font-mono font-semibold text-navy-700">buyer@shoppick.test</span> / password</p>
            </div>
        </div>
    </div>
    <p class="mt-4 text-center text-sm text-slate-600">Don't have an account? <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Register</a></p>
</div>
@endsection
